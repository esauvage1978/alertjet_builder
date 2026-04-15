<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\IngestResult;
use App\Entity\Project;
use App\Entity\Ticket;
use App\Entity\TicketLog;
use App\Enum\TicketPriority;
use App\Enum\TicketSource;
use App\Enum\TicketStatus;
use App\Enum\TicketType;
use App\Repository\OptionRepository;
use App\Repository\TicketRepository;
use Doctrine\ORM\EntityManagerInterface;

final class TicketIngestionService
{
    private const PJ_MIME_WHITELIST_CATEGORY = 'security';
    private const PJ_MIME_WHITELIST_OPTION = 'pj_white_list_mime';
    private const PJ_MIME_DEFAULT = "application/pdf\nimage/jpeg\nimage/png\nimage/webp\ntext/plain\ntext/csv\napplication/vnd.openxmlformats-officedocument.wordprocessingml.document\napplication/vnd.openxmlformats-officedocument.spreadsheetml.sheet\napplication/vnd.openxmlformats-officedocument.presentationml.presentation";

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly TicketRepository $ticketRepository,
        private readonly TicketNotificationService $notificationService,
        private readonly TicketAttachmentStorageService $ticketAttachmentStorageService,
        private readonly OrganizationContactService $organizationContactService,
        private readonly OptionRepository $optionRepository,
    ) {
    }

    /**
     * @param array<string, mixed>|null $json decoded JSON body or null
     */
    public function ingestFromWebhook(Project $project, string $rawBody, ?array $json): IngestResult
    {
        $title = $this->stringFromPayload($json, ['title', 'subject', 'summary'], 'Incident');
        $title = mb_substr(trim($title), 0, 255);
        if ($title === '') {
            $title = 'Incident';
        }

        $description = $this->stringFromPayload($json, ['message', 'description', 'body', 'detail'], $rawBody);
        $dedupeSource = $this->stringFromPayload($json, ['dedupe_key', 'fingerprint', 'error_id', 'incident_key'], $title);
        $fingerprint = hash('sha256', (string) $project->getId().'|'.mb_strtolower(trim($dedupeSource)));

        $priority = $this->parsePriority($json['priority'] ?? null);

        $existing = $this->ticketRepository->findOpenByFingerprint($project, $fingerprint);
        if ($existing !== null) {
            $existing->incrementEventCount();
            $log = (new TicketLog())
                ->setType('event')
                ->setMessage(sprintf('Événement reçu (#%d)', $existing->getEventCount()))
                ->setContext($json ?? ['raw' => mb_substr($rawBody, 0, 5000)]);

            $existing->addLog($log);
            $this->em->flush();
            $this->notificationService->notifyTicketEventMerged($existing);

            return new IngestResult($existing, true);
        }

        $ticket = (new Ticket())
            ->setProject($project)
            ->setTitle($title)
            ->setDescription(mb_substr($description, 0, 65000))
            ->setType(TicketType::Incident)
            ->setStatus(TicketStatus::New)
            ->setPriority($priority)
            ->setSource(TicketSource::Webhook)
            ->setFingerprint($fingerprint)
            ->setEventCount(1);

        $ticket->addLog(
            (new TicketLog())
                ->setType('created')
                ->setMessage('Ticket créé depuis le webhook')
                ->setContext($json ?? ['raw' => mb_substr($rawBody, 0, 5000)]),
        );

        $this->em->persist($ticket);
        $this->em->flush();

        $this->notificationService->notifyNewTicket($ticket);

        return new IngestResult($ticket, false);
    }

    /**
     * @param list<array{filename: string, content: string, mime: string}> $attachments
     */
    public function ingestFromEmail(
        Project $project,
        string $subject,
        string $body,
        ?string $messageId,
        ?string $fromEmail = null,
        ?string $fromDisplayName = null,
        array $attachments = [],
    ): IngestResult {
        $title = mb_substr(trim($subject), 0, 255);
        if ($title === '') {
            $title = 'E-mail';
        }

        $description = trim(strip_tags($body));
        if (strlen($description) > 65000) {
            $description = mb_substr($description, 0, 65000);
        }

        $fpSource = $messageId ?? (mb_strtolower($subject.'|'.($fromEmail ?? '').'|'.sha1($body)));
        $fingerprint = hash('sha256', (string) $project->getId().'|email|'.mb_strtolower(trim($fpSource)));

        $existing = $this->ticketRepository->findOpenByFingerprint($project, $fingerprint);
        if ($existing !== null) {
            $existing->incrementEventCount();
            $log = (new TicketLog())
                ->setType('event')
                ->setMessage(sprintf('Message e-mail fusionné (#%d)', $existing->getEventCount()))
                ->setContext(array_filter([
                    'messageId' => $messageId,
                    'fromEmail' => $fromEmail,
                    'fromDisplayName' => $fromDisplayName,
                ]));

            $existing->addLog($log);
            $this->applyEmailSenderToTicket($existing, $project, $fromEmail, $fromDisplayName);
            $this->em->flush();
            $this->storeEmailAttachments($existing, $attachments);
            $this->em->flush();
            $this->notificationService->notifyTicketEventMerged($existing);

            return new IngestResult($existing, true);
        }

        $ticket = (new Ticket())
            ->setProject($project)
            ->setTitle($title)
            ->setDescription($description !== '' ? $description : null)
            ->setType(TicketType::Incident)
            ->setStatus(TicketStatus::New)
            ->setPriority(TicketPriority::Medium)
            ->setSource(TicketSource::Email)
            ->setFingerprint($fingerprint)
            ->setEventCount(1);

        if ($messageId !== null && $messageId !== '') {
            $ticket->setIncomingEmailMessageId(mb_substr($messageId, 0, 255));
        }
        $this->applyEmailSenderToTicket($ticket, $project, $fromEmail, $fromDisplayName);

        $ticket->addLog(
            (new TicketLog())
                ->setType('created')
                ->setMessage('Ticket créé depuis la messagerie du projet')
                ->setContext(array_filter([
                    'messageId' => $messageId,
                    'fromEmail' => $fromEmail,
                    'fromDisplayName' => $fromDisplayName,
                ])),
        );

        $this->em->persist($ticket);
        $this->em->flush();

        $this->storeEmailAttachments($ticket, $attachments);
        $this->em->flush();

        $this->notificationService->notifyNewTicket($ticket);

        return new IngestResult($ticket, false);
    }

    private function applyEmailSenderToTicket(Ticket $ticket, Project $project, ?string $fromEmail, ?string $fromDisplayName): void
    {
        $org = $project->getOrganization();
        if ($org === null || $fromEmail === null || trim($fromEmail) === '') {
            return;
        }

        $contact = $this->organizationContactService->findOrCreateForOrganization($org, $fromEmail, $fromDisplayName);
        if ($contact !== null && $ticket->getOrganizationContact() === null) {
            $ticket->setOrganizationContact($contact);
        }
    }

    /**
     * @param list<array{filename: string, content: string, mime: string}> $attachments
     */
    private function storeEmailAttachments(Ticket $ticket, array $attachments): void
    {
        $allowed = $this->parseMimeWhitelist(
            $this->optionRepository->getTextValue(self::PJ_MIME_WHITELIST_CATEGORY, self::PJ_MIME_WHITELIST_OPTION, null, self::PJ_MIME_DEFAULT),
        );
        foreach ($attachments as $att) {
            $content = $att['content'] ?? '';
            if ($content === '') {
                continue;
            }
            $filename = isset($att['filename']) && \is_string($att['filename']) && $att['filename'] !== ''
                ? $att['filename']
                : 'fichier';
            $mime = isset($att['mime']) && \is_string($att['mime']) ? $att['mime'] : null;
            if (!$this->isMimeAllowed($mime, $allowed)) {
                continue;
            }
            try {
                $this->ticketAttachmentStorageService->storeForTicket($ticket, $content, $filename, $mime);
            } catch (\Throwable) {
                // Ne bloque pas la création du ticket si une PJ échoue (disque plein, etc.)
            }
        }
    }

    /**
     * @return list<string>
     */
    private function parseMimeWhitelist(string $raw): array
    {
        $parts = preg_split('/[,\r\n]+/', $raw) ?: [];
        $out = [];
        foreach ($parts as $p) {
            $v = strtolower(trim($p));
            if ($v === '') continue;
            $out[] = $v;
        }
        $out = array_values(array_unique($out));
        sort($out);

        return $out;
    }

    /**
     * @param list<string> $allowed
     */
    private function isMimeAllowed(?string $mime, array $allowed): bool
    {
        if ($mime === null) return false;
        $m = strtolower(trim($mime));
        if ($m === '') return false;

        return \in_array($m, $allowed, true);
    }

    /** @param list<string> $keys */
    private function stringFromPayload(?array $json, array $keys, string $fallback): string
    {
        if ($json !== null) {
            foreach ($keys as $key) {
                if (!\array_key_exists($key, $json)) {
                    continue;
                }
                $v = $json[$key];
                if (\is_string($v) || \is_int($v) || \is_float($v)) {
                    return trim((string) $v);
                }
                if (\is_array($v)) {
                    return trim(json_encode($v, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
                }
            }
        }

        return trim($fallback);
    }

    private function parsePriority(mixed $value): TicketPriority
    {
        if (!\is_string($value) && !\is_int($value)) {
            return TicketPriority::Medium;
        }

        $v = strtolower(trim((string) $value));
        $map = [
            'low' => TicketPriority::Low,
            '1' => TicketPriority::Low,
            'medium' => TicketPriority::Medium,
            'med' => TicketPriority::Medium,
            '2' => TicketPriority::Medium,
            'normal' => TicketPriority::Medium,
            'high' => TicketPriority::High,
            '3' => TicketPriority::High,
            'critical' => TicketPriority::Critical,
            'urgent' => TicketPriority::Critical,
            '4' => TicketPriority::Critical,
        ];

        return $map[$v] ?? TicketPriority::Medium;
    }
}
