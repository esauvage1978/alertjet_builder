<?php

declare(strict_types=1);

namespace App\Controller;

use App\Api\TicketApiPresenter;
use App\Entity\Project;
use App\Entity\Ticket;
use App\Entity\TicketLog;
use App\Entity\User;
use App\Enum\TicketPriority;
use App\Enum\TicketSource;
use App\Enum\TicketType;
use App\Http\AcceptJson;
use App\Repository\OrganizationClientAccessRepository;
use App\Repository\OptionRepository;
use App\Repository\ProjectRepository;
use App\Service\CurrentOrganizationService;
use App\Service\InternalTicketAccessPolicy;
use App\Service\TicketAttachmentStorageService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('IS_AUTHENTICATED_FULLY')]
final class OrganizationTicketCreateController extends AbstractController
{
    private const PJ_MIME_WHITELIST_CATEGORY = 'security';
    private const PJ_MIME_WHITELIST_OPTION = 'pj_white_list_mime';

    private const PJ_MIME_DEFAULT = "application/pdf\nimage/jpeg\nimage/png\nimage/webp\ntext/plain\ntext/csv\napplication/vnd.openxmlformats-officedocument.wordprocessingml.document\napplication/vnd.openxmlformats-officedocument.spreadsheetml.sheet\napplication/vnd.openxmlformats-officedocument.presentationml.presentation";

    #[Route('/mon-organisation/tickets/nouveau', name: 'app_organization_ticket_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        CurrentOrganizationService $currentOrganizationService,
        ProjectRepository $projectRepository,
        OrganizationClientAccessRepository $organizationClientAccessRepository,
        InternalTicketAccessPolicy $internalTicketAccessPolicy,
        CsrfTokenManagerInterface $csrfTokenManager,
        TicketAttachmentStorageService $ticketAttachmentStorageService,
        OptionRepository $optionRepository,
        EntityManagerInterface $entityManager,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw new \LogicException();
        }

        $organization = $currentOrganizationService->getCurrentOrganization();
        if ($organization === null) {
            return $this->redirectToRoute('app_home');
        }

        if (!$user->belongsToOrganization($organization)) {
            throw $this->createAccessDeniedException();
        }

        $hasInternalForm = $projectRepository->organizationHasInternalFormIntegrationEnabled($organization);
        if (!$internalTicketAccessPolicy->canCreateInternalTicket($user, $organization, $hasInternalForm, $organizationClientAccessRepository)) {
            if (AcceptJson::wants($request) || $request->isXmlHttpRequest()) {
                return $this->json([
                    'ok' => false,
                    'error' => 'forbidden',
                    'message' => $this->trans('ticket.create.forbidden'),
                ], 403);
            }
            throw $this->createAccessDeniedException();
        }

        if (!AcceptJson::wants($request) && !$request->isXmlHttpRequest()) {
            return $this->redirect('/app/tickets/new');
        }

        $projects = $projectRepository->findByOrganizationWithInternalFormEnabled($organization);

        if ($request->isMethod('GET')) {
            return $this->json([
                'migrated' => true,
                'organization' => [
                    'id' => $organization->getId(),
                    'name' => $organization->getName(),
                    'publicToken' => $organization->getPublicToken(),
                ],
                'hasInternalFormProject' => $hasInternalForm,
                'projects' => array_map(static fn (Project $p) => [
                    'publicToken' => $p->getPublicToken(),
                    'name' => $p->getName(),
                ], $projects),
                'formCsrf' => $csrfTokenManager->getToken('internal_ticket_create')->getValue(),
            ]);
        }

        $data = null;
        if (str_contains((string) $request->headers->get('Content-Type', ''), 'application/json')) {
            $decoded = json_decode((string) $request->getContent(), true);
            if (!\is_array($decoded)) {
                return $this->json(['ok' => false, 'error' => 'invalid_json'], 400);
            }
            $data = $decoded;
        } else {
            $data = $request->request->all();
            if (!\is_array($data)) {
                $data = [];
            }
        }

        $token = new CsrfToken('internal_ticket_create', (string) ($data['_token'] ?? ''));
        if (!$csrfTokenManager->isTokenValid($token)) {
            return $this->json(['ok' => false, 'error' => 'csrf', 'message' => $this->trans('error.invalid_csrf')], 403);
        }

        $projectToken = isset($data['projectToken']) && \is_string($data['projectToken']) ? trim($data['projectToken']) : '';
        if ($projectToken === '') {
            return $this->json(['ok' => false, 'error' => 'project_required'], 422);
        }

        $project = null;
        foreach ($projects as $p) {
            if ($p->getPublicToken() === $projectToken) {
                $project = $p;
                break;
            }
        }
        if (!$project instanceof Project) {
            return $this->json(['ok' => false, 'error' => 'project_not_found'], 404);
        }

        $title = isset($data['title']) && \is_string($data['title']) ? trim($data['title']) : '';
        if ($title === '') {
            return $this->json(['ok' => false, 'error' => 'title_required'], 422);
        }
        $description = isset($data['description']) && \is_string($data['description']) ? trim($data['description']) : '';
        if ($description === '') {
            return $this->json(['ok' => false, 'error' => 'description_required'], 422);
        }

        $priorityRaw = isset($data['priority']) && \is_string($data['priority']) ? $data['priority'] : TicketPriority::Medium->value;
        $typeRaw = isset($data['type']) && \is_string($data['type']) ? $data['type'] : TicketType::Incident->value;
        try {
            $priority = TicketPriority::from($priorityRaw);
        } catch (\ValueError) {
            return $this->json(['ok' => false, 'error' => 'invalid_priority'], 422);
        }
        try {
            $type = TicketType::from($typeRaw);
        } catch (\ValueError) {
            return $this->json(['ok' => false, 'error' => 'invalid_type'], 422);
        }

        $ticket = (new Ticket())
            ->setProject($project)
            ->setTitle($title)
            ->setDescription($description)
            ->setPriority($priority)
            ->setType($type)
            ->setSource(TicketSource::InternalForm)
            ->setFingerprint(hash('sha256', $project->getPublicToken().'|'.(string) microtime(true).'|'.bin2hex(random_bytes(8))));

        $files = $request->files->all('attachments');
        if (!\is_array($files)) {
            $files = [];
        }
        $files = array_values(array_filter($files, static fn ($f) => $f !== null));
        if (\count($files) > 10) {
            return $this->json(['ok' => false, 'error' => 'too_many_attachments', 'message' => 'Trop de pièces jointes (max 10).'], 422);
        }

        $allowed = $this->parseMimeWhitelist(
            $optionRepository->getTextValue(self::PJ_MIME_WHITELIST_CATEGORY, self::PJ_MIME_WHITELIST_OPTION, null, self::PJ_MIME_DEFAULT),
        );

        foreach ($files as $f) {
            if (!$f instanceof \Symfony\Component\HttpFoundation\File\UploadedFile) {
                continue;
            }
            if (!$f->isValid()) {
                return $this->json(['ok' => false, 'error' => 'invalid_attachment', 'message' => 'Pièce jointe invalide.'], 422);
            }
            if ($f->getSize() !== null && $f->getSize() > 15 * 1024 * 1024) {
                return $this->json(['ok' => false, 'error' => 'attachment_too_large', 'message' => 'Une pièce jointe dépasse 15 Mo.'], 422);
            }
            $mime = $f->getMimeType() ?: $f->getClientMimeType();
            if (!$this->isMimeAllowed($mime, $allowed)) {
                return $this->json([
                    'ok' => false,
                    'error' => 'attachment_mime_not_allowed',
                    'message' => sprintf('Type de fichier non autorisé (%s).', $mime ?: 'inconnu'),
                ], 422);
            }
            $content = @file_get_contents($f->getPathname());
            if (!\is_string($content)) {
                return $this->json(['ok' => false, 'error' => 'attachment_read_failed', 'message' => 'Impossible de lire une pièce jointe.'], 422);
            }
            $ticketAttachmentStorageService->storeForTicket(
                $ticket,
                $content,
                (string) $f->getClientOriginalName(),
                $mime,
            );
        }

        $ticket->addLog(
            (new TicketLog())
                ->setType('create')
                ->setMessage('Ticket créé via formulaire interne')
                ->setContext(['actorEmail' => $user->getEmail()]),
        );

        $entityManager->persist($ticket);
        $entityManager->flush();

        return $this->json([
            'ok' => true,
            'ticketId' => $ticket->getId(),
            'ticket' => TicketApiPresenter::one($ticket),
        ], 201);
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
}
