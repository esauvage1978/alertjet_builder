<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\IngestResult;
use App\Entity\Project;
use App\Entity\Ticket;
use App\Entity\TicketLog;
use App\Enum\TicketPriority;
use App\Enum\TicketStatus;
use App\Repository\TicketRepository;
use Doctrine\ORM\EntityManagerInterface;

final class TicketIngestionService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly TicketRepository $ticketRepository,
        private readonly TicketNotificationService $notificationService,
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
            ->setStatus(TicketStatus::Open)
            ->setPriority($priority)
            ->setSource('webhook')
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
