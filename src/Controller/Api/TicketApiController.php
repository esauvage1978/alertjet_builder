<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Api\TicketApiPresenter;
use App\Entity\Ticket;
use App\Entity\TicketLog;
use App\Entity\User;
use App\Enum\TicketPriority;
use App\Enum\TicketSource;
use App\Enum\TicketStatus;
use App\Enum\TicketType;
use App\Repository\ProjectRepository;
use App\Repository\TicketRepository;
use App\Repository\UserRepository;
use App\Repository\OrganizationClientAccessRepository;
use App\Repository\OptionRepository;
use App\Service\OrganizationContactService;
use App\Service\TicketAttachmentStorageService;
use Doctrine\ORM\EntityManagerInterface;
use App\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Attribute\Route;

final class TicketApiController extends AbstractController
{
    private const PJ_MIME_WHITELIST_CATEGORY = 'security';
    private const PJ_MIME_WHITELIST_OPTION = 'pj_white_list_mime';
    private const PJ_MIME_DEFAULT = "application/pdf\nimage/jpeg\nimage/png\nimage/webp\ntext/plain\ntext/csv\napplication/vnd.openxmlformats-officedocument.wordprocessingml.document\napplication/vnd.openxmlformats-officedocument.spreadsheetml.sheet\napplication/vnd.openxmlformats-officedocument.presentationml.presentation";

    public function __construct(
        private readonly ProjectRepository $projectRepository,
        private readonly TicketRepository $ticketRepository,
        private readonly UserRepository $userRepository,
        private readonly OrganizationClientAccessRepository $organizationClientAccessRepository,
        private readonly OrganizationContactService $organizationContactService,
        private readonly TicketAttachmentStorageService $ticketAttachmentStorageService,
        private readonly OptionRepository $optionRepository,
        private readonly EntityManagerInterface $em,
        private readonly MailerInterface $mailer,
        #[Autowire('%env(ALERTJET_MAIL_FROM)%')]
        private readonly string $mailFrom,
    ) {
    }

    #[Route('/api/projects/{id}/tickets', name: 'api_tickets_list', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function listForProject(int $id, Request $request): Response
    {
        $project = $this->projectRepository->find($id);
        if ($project === null) {
            return $this->json(['error' => 'project_not_found'], Response::HTTP_NOT_FOUND);
        }

        $status = $request->query->get('status');
        $qb = $this->ticketRepository->createQueryBuilder('t')
            ->leftJoin('t.attachments', 'att')->addSelect('att')
            ->andWhere('t.project = :p')
            ->setParameter('p', $project)
            ->orderBy('t.createdAt', 'DESC');

        if (\is_string($status) && $status !== '') {
            try {
                $st = TicketStatus::from($status);
                $qb->andWhere('t.status = :st')->setParameter('st', $st);
            } catch (\ValueError) {
                return $this->json(['error' => 'invalid_status'], Response::HTTP_BAD_REQUEST);
            }
        }

        $tickets = $qb->getQuery()->getResult();

        return $this->json(array_map(static fn ($t) => TicketApiPresenter::one($t), $tickets));
    }

    #[Route('/api/tickets/{id}', name: 'api_ticket_one', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function one(int $id): Response
    {
        $ticket = $this->ticketRepository->findWithAttachments($id);
        if ($ticket === null) {
            return $this->json(['error' => 'ticket_not_found'], Response::HTTP_NOT_FOUND);
        }

        $deny = $this->denyUnlessTicketInUserOrganization($ticket);
        if ($deny !== null) {
            return $deny;
        }

        $payload = TicketApiPresenter::one($ticket);
        $payload['clientPortalAccess'] = $this->clientPortalAccessForTicket($ticket);

        return $this->json($payload);
    }

    #[Route('/api/tickets/{id}/attachments', name: 'api_ticket_attachments_add', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function addAttachments(int $id, Request $request): Response
    {
        $ticket = $this->ticketRepository->find($id);
        if ($ticket === null) {
            return $this->json(['error' => 'ticket_not_found'], Response::HTTP_NOT_FOUND);
        }

        $deny = $this->denyUnlessTicketInUserOrganization($ticket);
        if ($deny !== null) {
            return $deny;
        }

        $files = $request->files->all('attachments');
        if (!\is_array($files)) {
            $files = [];
        }
        $files = array_values(array_filter($files, static fn ($f) => $f !== null));
        if (\count($files) === 0) {
            return $this->json(['error' => 'attachments_required'], 422);
        }
        if (\count($files) > 10) {
            return $this->json(['error' => 'too_many_attachments', 'message' => 'Trop de pièces jointes (max 10).'], 422);
        }

        $allowed = $this->parseMimeWhitelist(
            $this->optionRepository->getTextValue(self::PJ_MIME_WHITELIST_CATEGORY, self::PJ_MIME_WHITELIST_OPTION, null, self::PJ_MIME_DEFAULT),
        );

        foreach ($files as $f) {
            if (!$f instanceof \Symfony\Component\HttpFoundation\File\UploadedFile) {
                continue;
            }
            if (!$f->isValid()) {
                return $this->json(['error' => 'invalid_attachment', 'message' => 'Pièce jointe invalide.'], 422);
            }
            if ($f->getSize() !== null && $f->getSize() > 15 * 1024 * 1024) {
                return $this->json(['error' => 'attachment_too_large', 'message' => 'Une pièce jointe dépasse 15 Mo.'], 422);
            }
            $mime = $f->getMimeType() ?: $f->getClientMimeType();
            if (!$this->isMimeAllowed($mime, $allowed)) {
                return $this->json([
                    'error' => 'attachment_mime_not_allowed',
                    'message' => sprintf('Type de fichier non autorisé (%s).', $mime ?: 'inconnu'),
                ], 422);
            }
            $content = @file_get_contents($f->getPathname());
            if (!\is_string($content)) {
                return $this->json(['error' => 'attachment_read_failed', 'message' => 'Impossible de lire une pièce jointe.'], 422);
            }
            $this->ticketAttachmentStorageService->storeForTicket(
                $ticket,
                $content,
                (string) $f->getClientOriginalName(),
                $mime,
            );
        }

        $actor = $this->getUser();
        $actorCtx = $actor instanceof User ? self::actorContext($actor) : null;
        $ticket->addLog(
            (new TicketLog())
                ->setType('attachment')
                ->setMessage('Pièce(s) jointe(s) ajoutée(s)')
                ->setContext($actorCtx !== null ? ['actor' => $actorCtx] : null),
        );

        $this->em->flush();

        $fresh = $this->ticketRepository->findWithAttachments($id);
        if ($fresh === null) {
            return $this->json(['error' => 'ticket_not_found'], Response::HTTP_NOT_FOUND);
        }

        $payload = TicketApiPresenter::one($fresh);
        $payload['clientPortalAccess'] = $this->clientPortalAccessForTicket($fresh);

        return $this->json($payload, 201);
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

    #[Route('/api/tickets/{id}', name: 'api_ticket_patch', methods: ['PATCH'], requirements: ['id' => '\d+'])]
    public function patch(int $id, Request $request): Response
    {
        $ticket = $this->ticketRepository->find($id);
        if ($ticket === null) {
            return $this->json(['error' => 'ticket_not_found'], Response::HTTP_NOT_FOUND);
        }

        $deny = $this->denyUnlessTicketInUserOrganization($ticket);
        if ($deny !== null) {
            return $deny;
        }

        $data = json_decode($request->getContent(), true);
        if (!\is_array($data)) {
            return $this->json(['error' => 'invalid_json'], Response::HTTP_BAD_REQUEST);
        }

        $before = $ticket->getStatus()->value;

        if (isset($data['status']) && \is_string($data['status'])) {
            try {
                $ticket->setStatus(TicketStatus::from($data['status']));
            } catch (\ValueError) {
                return $this->json(['error' => 'invalid_status'], Response::HTTP_BAD_REQUEST);
            }
        }

        if (isset($data['type']) && \is_string($data['type'])) {
            try {
                $ticket->setType(TicketType::from($data['type']));
            } catch (\ValueError) {
                return $this->json(['error' => 'invalid_type'], Response::HTTP_BAD_REQUEST);
            }
        }

        if (\array_key_exists('source', $data)) {
            return $this->json(['error' => 'source_read_only'], Response::HTTP_BAD_REQUEST);
        }

        if (isset($data['priority']) && \is_string($data['priority'])) {
            try {
                $ticket->setPriority(TicketPriority::from($data['priority']));
            } catch (\ValueError) {
                return $this->json(['error' => 'invalid_priority'], Response::HTTP_BAD_REQUEST);
            }
        }

        if (\array_key_exists('onHoldReason', $data)) {
            $ticket->setOnHoldReason(\is_string($data['onHoldReason']) ? trim($data['onHoldReason']) : null);
        }

        if (\array_key_exists('cancelReason', $data)) {
            $ticket->setCancelReason(\is_string($data['cancelReason']) ? trim($data['cancelReason']) : null);
        }

        if (\array_key_exists('silenced', $data)) {
            return $this->json(['error' => 'silenced_read_only'], Response::HTTP_BAD_REQUEST);
        }

        if (\array_key_exists('assigneeUserId', $data)) {
            $beforeAssignee = $ticket->getAssignee();
            $raw = $data['assigneeUserId'];
            if ($raw === null || $raw === '') {
                $ticket->setAssignee(null);
            } else {
                $uid = filter_var($raw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
                if ($uid === false) {
                    return $this->json(['error' => 'invalid_assignee_user_id'], Response::HTTP_BAD_REQUEST);
                }
                $candidate = $this->userRepository->find($uid);
                if ($candidate === null) {
                    return $this->json(['error' => 'assignee_not_found'], Response::HTTP_BAD_REQUEST);
                }
                if (!$this->userMayBeAssignedToTicket($ticket, $candidate)) {
                    return $this->json(['error' => 'invalid_assignee'], Response::HTTP_BAD_REQUEST);
                }
                $ticket->setAssignee($candidate);
            }

            $afterAssignee = $ticket->getAssignee();
            if (($beforeAssignee?->getId()) !== ($afterAssignee?->getId())) {
                $actor = $this->getUser();
                $actorCtx = $actor instanceof User ? self::actorContext($actor) : null;
                $ticket->addLog(
                    (new TicketLog())
                        ->setType('assignment')
                        ->setMessage(self::assigneeChangeMessage($beforeAssignee, $afterAssignee))
                        ->setContext($actorCtx !== null ? ['actor' => $actorCtx] : null),
                );
            }

            // ITIL: affectation = prise en compte (si le ticket était nouveau et qu'aucun statut explicite n'est demandé).
            if (!isset($data['status']) && $before === TicketStatus::New->value && $afterAssignee !== null) {
                $ticket->setStatus(TicketStatus::Acknowledged);
            }
        }

        if (isset($data['note']) && \is_string($data['note']) && trim($data['note']) !== '') {
            $actor = $this->getUser();
            $actorCtx = $actor instanceof User ? self::actorContext($actor) : null;
            $ticket->addLog(
                (new TicketLog())
                    ->setType('note')
                    ->setMessage(trim($data['note']))
                    ->setContext($actorCtx !== null ? ['actor' => $actorCtx] : null),
            );
        }

        if ($ticket->getStatus()->value !== $before) {
            $actor = $this->getUser();
            $actorCtx = $actor instanceof User ? self::actorContext($actor) : null;
            $ticket->addLog(
                (new TicketLog())
                    ->setType('status')
                    ->setMessage(sprintf('Statut : %s → %s', $before, $ticket->getStatus()->value))
                    ->setContext($actorCtx !== null ? ['actor' => $actorCtx] : null),
            );
        }

        $this->em->flush();

        $fresh = $this->ticketRepository->findWithAttachments($id);
        if ($fresh === null) {
            return $this->json(['error' => 'ticket_not_found'], Response::HTTP_NOT_FOUND);
        }

        $payload = TicketApiPresenter::one($fresh);
        $payload['clientPortalAccess'] = $this->clientPortalAccessForTicket($fresh);

        return $this->json($payload);
    }

    #[Route('/api/projects/{id}/stats', name: 'api_project_stats', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function stats(int $id): Response
    {
        $project = $this->projectRepository->find($id);
        if ($project === null) {
            return $this->json(['error' => 'project_not_found'], Response::HTTP_NOT_FOUND);
        }

        $conn = $this->em->getConnection();
        $pid = $project->getId();

        $total = (int) $conn->fetchOne('SELECT COUNT(*) FROM tickets WHERE project_id = ?', [$pid]);
        $criticalOpen = (int) $conn->fetchOne(
            "SELECT COUNT(*) FROM tickets WHERE project_id = ? AND priority = 'critical' AND status IN ('open','new','acknowledged','in_progress','on_hold')",
            [$pid],
        );

        $mts = $conn->fetchOne(
            "SELECT AVG((julianday(resolved_at) - julianday(created_at)) * 86400) FROM tickets WHERE project_id = ? AND resolved_at IS NOT NULL",
            [$pid],
        );
        $avgResolutionSeconds = $mts !== null ? (float) $mts : null;

        return $this->json([
            'totalTickets' => $total,
            'openCritical' => $criticalOpen,
            'avgResolutionSeconds' => $avgResolutionSeconds,
        ]);
    }

    #[Route('/api/tickets/{id}/client-message', name: 'api_ticket_client_message', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function sendClientMessage(int $id, Request $request): Response
    {
        $ticket = $this->ticketRepository->find($id);
        if ($ticket === null) {
            return $this->json(['error' => 'ticket_not_found'], Response::HTTP_NOT_FOUND);
        }

        $deny = $this->denyUnlessTicketInUserOrganization($ticket);
        if ($deny !== null) {
            return $deny;
        }

        $actor = $this->getUser();
        if (!$actor instanceof User) {
            return $this->json(['error' => 'unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $contactEmail = $ticket->getOrganizationContact()?->getEmail();
        if ($contactEmail === null || trim($contactEmail) === '') {
            return $this->json(['error' => 'no_client_contact'], 422);
        }

        $data = json_decode($request->getContent(), true);
        if (!\is_array($data)) {
            return $this->json(['error' => 'invalid_json'], Response::HTTP_BAD_REQUEST);
        }

        $subject = isset($data['subject']) && \is_string($data['subject']) ? trim($data['subject']) : '';
        $body = isset($data['body']) && \is_string($data['body']) ? trim($data['body']) : '';
        if ($subject === '' || $body === '') {
            return $this->json(['error' => 'invalid_message'], 422);
        }

        $email = (new Email())
            ->from($this->mailFrom)
            ->to($contactEmail)
            ->replyTo($actor->getEmail())
            ->subject($subject)
            ->text($body);

        try {
            $this->mailer->send($email);
        } catch (\Throwable $e) {
            return $this->json(['error' => 'mail_send_failed', 'message' => $e->getMessage()], 500);
        }

        $beforeStatus = $ticket->getStatus()->value;

        $ticket->incrementEventCount();
        $ticket->addLog(
            (new TicketLog())
                ->setType('client_message')
                ->setMessage('Message envoyé au client')
                ->setContext([
                    'to' => $contactEmail,
                    'subject' => $subject,
                    'bodyExcerpt' => mb_substr($body, 0, 800),
                    'actorEmail' => $actor->getEmail(),
                    'actor' => self::actorContext($actor),
                ]),
        );

        // ITIL: après sollicitation client, le ticket passe en attente "retour client" tant qu'il n'est pas finalisé.
        if (!\in_array($ticket->getStatus(), [TicketStatus::Resolved, TicketStatus::Closed, TicketStatus::Cancelled], true)) {
            $ticket->setStatus(TicketStatus::OnHold);
            if ($ticket->getOnHoldReason() === null || trim($ticket->getOnHoldReason() ?? '') === '') {
                $ticket->setOnHoldReason('Attente retour client');
            }
        }

        if ($ticket->getStatus()->value !== $beforeStatus) {
            $ticket->addLog(
                (new TicketLog())
                    ->setType('status')
                    ->setMessage(sprintf('Statut : %s → %s', $beforeStatus, $ticket->getStatus()->value))
                    ->setContext(['actor' => self::actorContext($actor)]),
            );
        }

        $this->em->flush();

        $fresh = $this->ticketRepository->findWithAttachments($id);
        if ($fresh === null) {
            return $this->json(['error' => 'ticket_not_found'], Response::HTTP_NOT_FOUND);
        }

        $payload = TicketApiPresenter::one($fresh);
        $payload['clientPortalAccess'] = $this->clientPortalAccessForTicket($fresh);

        return $this->json($payload);
    }

    #[Route('/api/tickets/{id}/validate-client', name: 'api_ticket_validate_client', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function validateClient(int $id): Response
    {
        $ticket = $this->ticketRepository->find($id);
        if ($ticket === null) {
            return $this->json(['error' => 'ticket_not_found'], Response::HTTP_NOT_FOUND);
        }

        $deny = $this->denyUnlessTicketInUserOrganization($ticket);
        if ($deny !== null) {
            return $deny;
        }

        $actor = $this->getUser();
        if (!$actor instanceof User) {
            return $this->json(['error' => 'unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $contact = $ticket->getOrganizationContact();
        if ($contact === null || $contact->getId() === null) {
            return $this->json(['error' => 'no_client_contact'], 422);
        }

        $organization = $ticket->getProject()?->getOrganization();
        if ($organization !== null) {
            $this->organizationContactService->ensureClientUserExistsForOrganization(
                $organization,
                $contact->getEmail(),
                $contact->getDisplayName(),
            );
        }

        if ($contact->getValidatedAt() === null) {
            $contact->setValidatedAt(new \DateTimeImmutable());
            $contact->setUpdatedAt(new \DateTimeImmutable());
        }

        $ticket->incrementEventCount();
        $ticket->addLog(
            (new TicketLog())
                ->setType('client_validation')
                ->setMessage('Client validé')
                ->setContext(['actor' => self::actorContext($actor), 'contactEmail' => $contact->getEmail()]),
        );

        $this->em->flush();

        $fresh = $this->ticketRepository->findWithAttachments($id);
        if ($fresh === null) {
            return $this->json(['error' => 'ticket_not_found'], Response::HTTP_NOT_FOUND);
        }

        $payload = TicketApiPresenter::one($fresh);
        $payload['clientPortalAccess'] = $this->clientPortalAccessForTicket($fresh);

        return $this->json($payload);
    }

    private function denyUnlessTicketInUserOrganization(Ticket $ticket): ?Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $project = $ticket->getProject();
        $organization = $project?->getOrganization();
        if ($organization === null || !$user->belongsToOrganization($organization)) {
            return $this->json(['error' => 'forbidden'], Response::HTTP_FORBIDDEN);
        }

        return null;
    }

    private function userMayBeAssignedToTicket(Ticket $ticket, User $candidate): bool
    {
        $project = $ticket->getProject();
        if ($project === null) {
            return false;
        }
        if ($project->getTicketHandlers()->contains($candidate)) {
            return true;
        }
        $current = $ticket->getAssignee();

        return $current !== null && $current->getId() === $candidate->getId();
    }

    private static function assigneeChangeMessage(?User $before, ?User $after): string
    {
        $label = static fn (?User $u): string => $u === null ? 'personne' : $u->getDisplayNameForGreeting();

        if ($after === null) {
            return sprintf('Assignation retirée (était : %s).', $label($before));
        }
        if ($before === null) {
            return sprintf('Assigné à %s.', $label($after));
        }

        return sprintf('Réassigné : %s → %s.', $label($before), $label($after));
    }

    /** @return array{id: int, name: string, email: string}|null */
    private static function actorContext(User $u): ?array
    {
        $id = $u->getId();
        if ($id === null) {
            return null;
        }

        return [
            'id' => $id,
            'name' => $u->getDisplayNameForGreeting(),
            'email' => $u->getEmail(),
        ];
    }

    private function clientPortalAccessForTicket(Ticket $ticket): bool
    {
        $org = $ticket->getProject()?->getOrganization();
        $email = $ticket->getOrganizationContact()?->getEmail();
        if ($org === null || $email === null || trim($email) === '') {
            return false;
        }

        $user = $this->userRepository->findOneByEmailLowercase(mb_strtolower(trim($email)));
        if ($user === null) {
            return false;
        }

        return $this->organizationClientAccessRepository->userHasAccess($user, $org);
    }
}
