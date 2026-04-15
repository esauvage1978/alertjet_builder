<?php

declare(strict_types=1);

namespace App\Controller;

use App\Api\TicketApiPresenter;
use App\Entity\Project;
use App\Entity\User;
use App\Enum\TicketPriority;
use App\Enum\TicketSource;
use App\Enum\TicketStatus;
use App\Enum\TicketType;
use App\Http\AcceptJson;
use App\Repository\ProjectRepository;
use App\Repository\TicketRepository;
use App\Repository\UserRepository;
use App\Service\CurrentOrganizationService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('IS_AUTHENTICATED_FULLY')]
final class OrganizationTicketsController extends AbstractController
{
    private const PER_PAGE_ALLOWED = [10, 15, 25, 50];

    public function __construct(
        private readonly CurrentOrganizationService $currentOrganizationService,
        private readonly TicketRepository $ticketRepository,
        private readonly ProjectRepository $projectRepository,
        private readonly UserRepository $userRepository,
    ) {
    }

    #[Route('/mon-organisation/tickets', name: 'app_organization_tickets', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw new \LogicException();
        }

        $uid = $user->getId();
        if ($uid === null) {
            throw new \LogicException();
        }

        $organization = $this->currentOrganizationService->getCurrentOrganization();
        if ($organization === null) {
            return $this->redirectToRoute('app_home');
        }

        if (!$user->belongsToOrganization($organization)) {
            throw $this->createAccessDeniedException();
        }

        if (!AcceptJson::wants($request)) {
            return $this->redirect('/app/tickets');
        }

        $page = max(1, (int) $request->query->get('page', '1'));
        $perPageRaw = (int) $request->query->get('perPage', '15');
        $perPage = \in_array($perPageRaw, self::PER_PAGE_ALLOWED, true) ? $perPageRaw : 15;

        $q = $request->query->get('q');
        $search = \is_string($q) ? mb_substr(trim($q), 0, 200) : null;
        if ($search === '') {
            $search = null;
        }

        $status = $this->parseEnumQuery($request->query->get('status'), TicketStatus::class);
        $priority = $this->parseEnumQuery($request->query->get('priority'), TicketPriority::class);
        $source = $this->parseEnumQuery($request->query->get('source'), TicketSource::class);
        $type = $this->parseEnumQuery($request->query->get('type'), TicketType::class);

        $projectId = null;
        $projectRaw = $request->query->get('project');
        if ($projectRaw !== null && $projectRaw !== '' && ctype_digit((string) $projectRaw)) {
            $projectId = (int) $projectRaw;
        }

        $assigneeFilter = null;
        $assigneeRaw = $request->query->get('assignee');
        if (\is_string($assigneeRaw) && $assigneeRaw !== '') {
            if (\in_array($assigneeRaw, ['unassigned', 'me'], true) || ctype_digit($assigneeRaw)) {
                $assigneeFilter = $assigneeRaw;
            }
        }

        $result = $this->ticketRepository->paginateForOrganization(
            $organization,
            $search,
            $status,
            $priority,
            $source,
            $type,
            $projectId,
            $assigneeFilter,
            $uid,
            $page,
            $perPage,
        );

        $total = $result['total'];
        $totalPages = $total > 0 ? (int) max(1, (int) ceil($total / $perPage)) : 1;

        $projects = $this->projectRepository->findOrderedForOrganization($organization);
        $projectOptions = array_map(static function (Project $p) {
            return [
                'id' => $p->getId(),
                'name' => $p->getName(),
                'publicToken' => $p->getPublicToken(),
            ];
        }, $projects);

        $assigneeIds = $this->ticketRepository->assigneeUserIdsInOrganization($organization);
        $assigneeOptions = [];
        if ($assigneeIds !== []) {
            $assignees = $this->userRepository->findBy(['id' => $assigneeIds]);
            usort(
                $assignees,
                static fn (User $a, User $b): int => strcasecmp(
                    $a->getDisplayNameForGreeting(),
                    $b->getDisplayNameForGreeting(),
                ),
            );
            foreach ($assignees as $a) {
                $aid = $a->getId();
                if ($aid === null) {
                    continue;
                }
                $assigneeOptions[] = [
                    'id' => $aid,
                    'label' => $a->getDisplayNameForGreeting(),
                    'initials' => $a->getAvatarInitials(),
                ];
            }
        }

        return $this->json([
            'migrated' => true,
            'organization' => [
                'id' => $organization->getId(),
                'name' => $organization->getName(),
                'publicToken' => $organization->getPublicToken(),
            ],
            'tickets' => array_map(static fn ($t) => TicketApiPresenter::listSummary($t), $result['items']),
            'pagination' => [
                'page' => $page,
                'perPage' => $perPage,
                'total' => $total,
                'totalPages' => $totalPages,
            ],
            'filterOptions' => [
                'projects' => $projectOptions,
                'assignees' => $assigneeOptions,
                'perPageChoices' => self::PER_PAGE_ALLOWED,
            ],
        ]);
    }

    /**
     * @template T of \BackedEnum
     *
     * @param class-string<T> $enumClass
     *
     * @return T|null
     */
    private function parseEnumQuery(mixed $raw, string $enumClass): ?\BackedEnum
    {
        if (!\is_string($raw) || $raw === '') {
            return null;
        }
        try {
            return $enumClass::from($raw);
        } catch (\ValueError) {
            return null;
        }
    }
}
