<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Controller\AbstractController;
use App\Entity\ApplicationErrorLog;
use App\Entity\ImapFetchRun;
use App\Entity\Option;
use App\Entity\User;
use App\Repository\ApplicationErrorLogRepository;
use App\Repository\ImapFetchRunRepository;
use App\Repository\OptionRepository;
use App\Repository\OrganizationRepository;
use App\Repository\UserActionLogRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/admin')]
#[IsGranted('ROLE_ADMIN')]
final class AdminJsonApiController extends AbstractController
{
    private const USERS_PER_PAGE = 20;

    private const AUDIT_PER_PAGE = 40;

    private const IMAP_RUNS_PER_PAGE = 50;

    #[Route('/organisations', name: 'api_admin_organisations', methods: ['GET'])]
    public function organizations(OrganizationRepository $organizationRepository): JsonResponse
    {
        $items = [];
        foreach ($organizationRepository->findAllOrderedByName() as $org) {
            $items[] = [
                'id' => $org->getId(),
                'name' => $org->getName(),
                'publicToken' => $org->getPublicToken(),
            ];
        }

        return $this->json(['items' => $items]);
    }

    #[Route('/utilisateurs', name: 'api_admin_utilisateurs', methods: ['GET'])]
    public function users(
        Request $request,
        UserRepository $userRepository,
        OrganizationRepository $organizationRepository,
    ): JsonResponse {
        $orgId = $request->query->getInt('organization', 0);
        $organizationFilter = $orgId > 0 ? $orgId : null;
        $q = trim((string) $request->query->get('q', ''));
        $search = $q !== '' ? $q : null;
        $page = max(1, $request->query->getInt('page', 1));

        $qb = $userRepository->createAdminFilteredQueryBuilder($organizationFilter, $search);
        $query = $qb->getQuery()
            ->setFirstResult(($page - 1) * self::USERS_PER_PAGE)
            ->setMaxResults(self::USERS_PER_PAGE);

        $paginator = new Paginator($query, $organizationFilter !== null);
        $total = $paginator->count();
        $pageCount = max(1, (int) ceil($total / self::USERS_PER_PAGE));
        $users = iterator_to_array($paginator, false);

        $rows = [];
        foreach ($users as $u) {
            if (!$u instanceof User) {
                continue;
            }
            $rows[] = [
                'id' => $u->getId(),
                'email' => $u->getEmail(),
                'displayName' => $u->getDisplayName(),
                'primaryRole' => $u->getPrimaryRoleCatalogKey(),
            ];
        }

        $orgs = [];
        foreach ($organizationRepository->findAllOrderedByName() as $o) {
            $orgs[] = ['id' => $o->getId(), 'name' => $o->getName()];
        }

        return $this->json([
            'users' => $rows,
            'organizations' => $orgs,
            'pagination' => [
                'page' => $page,
                'pageCount' => $pageCount,
                'total' => $total,
                'perPage' => self::USERS_PER_PAGE,
            ],
            'filters' => [
                'organization' => $organizationFilter,
                'q' => $q,
            ],
        ]);
    }

    #[Route('/audit/actions', name: 'api_admin_audit_actions', methods: ['GET'])]
    public function auditActions(Request $request, UserActionLogRepository $userActionLogRepository): JsonResponse
    {
        $page = max(1, $request->query->getInt('page', 1));
        $filterAction = trim((string) $request->query->get('action', ''));
        $filterActor = trim((string) $request->query->get('q', ''));

        $paginator = $userActionLogRepository->createAdminPaginator(
            $page,
            self::AUDIT_PER_PAGE,
            $filterAction !== '' ? $filterAction : null,
            $filterActor !== '' ? $filterActor : null,
        );

        $total = $paginator->count();
        $pageCount = max(1, (int) ceil($total / self::AUDIT_PER_PAGE));
        $items = iterator_to_array($paginator->getIterator());

        $rows = [];
        foreach ($items as $log) {
            $rows[] = [
                'id' => $log->getId(),
                'action' => $log->getAction(),
                'createdAt' => $log->getCreatedAt()->format(\DateTimeInterface::ATOM),
                'details' => $log->getDetails(),
                'actorEmail' => $log->getActorEmail(),
            ];
        }

        return $this->json([
            'logs' => $rows,
            'pagination' => [
                'page' => $page,
                'pageCount' => $pageCount,
                'total' => $total,
                'perPage' => self::AUDIT_PER_PAGE,
            ],
            'filters' => [
                'action' => $filterAction,
                'q' => $filterActor,
            ],
        ]);
    }

    #[Route('/audit/erreurs', name: 'api_admin_audit_errors', methods: ['GET'])]
    public function auditErrors(Request $request, ApplicationErrorLogRepository $applicationErrorLogRepository): JsonResponse
    {
        $page = max(1, $request->query->getInt('page', 1));
        $filterClass = trim((string) $request->query->get('class', ''));
        $filterMessage = trim((string) $request->query->get('q', ''));

        $paginator = $applicationErrorLogRepository->createAdminPaginator(
            $page,
            self::AUDIT_PER_PAGE,
            $filterClass !== '' ? $filterClass : null,
            $filterMessage !== '' ? $filterMessage : null,
        );

        $total = $paginator->count();
        $pageCount = max(1, (int) ceil($total / self::AUDIT_PER_PAGE));
        $items = iterator_to_array($paginator->getIterator());

        $rows = [];
        foreach ($items as $log) {
            if (!$log instanceof ApplicationErrorLog) {
                continue;
            }
            $rows[] = [
                'id' => $log->getId(),
                'exceptionClass' => $log->getExceptionClass(),
                'message' => $log->getMessage(),
                'createdAt' => $log->getCreatedAt()->format(\DateTimeInterface::ATOM),
            ];
        }

        return $this->json([
            'errors' => $rows,
            'pagination' => [
                'page' => $page,
                'pageCount' => $pageCount,
                'total' => $total,
                'perPage' => self::AUDIT_PER_PAGE,
            ],
            'filters' => [
                'class' => $filterClass,
                'q' => $filterMessage,
            ],
        ]);
    }

    #[Route('/audit/erreurs/{id}', name: 'api_admin_audit_error_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function auditErrorShow(int $id, ApplicationErrorLogRepository $applicationErrorLogRepository): JsonResponse
    {
        $log = $applicationErrorLogRepository->find($id);
        if (!$log instanceof ApplicationErrorLog) {
            return $this->json(['error' => 'not_found'], 404);
        }

        return $this->json([
            'id' => $log->getId(),
            'exceptionClass' => $log->getExceptionClass(),
            'message' => $log->getMessage(),
            'code' => $log->getCode(),
            'file' => $log->getFile(),
            'line' => $log->getLine(),
            'trace' => $log->getTrace(),
            'createdAt' => $log->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'route' => $log->getRoute(),
            'requestUri' => $log->getRequestUri(),
            'actorEmail' => $log->getActorEmail(),
            'context' => $log->getContext(),
        ]);
    }

    #[Route('/options', name: 'api_admin_options_list', methods: ['GET'])]
    public function optionsList(Request $request, OptionRepository $optionRepository): JsonResponse
    {
        $category = trim((string) $request->query->get('category', ''));
        $domain = trim((string) $request->query->get('domain', ''));
        $optionName = trim((string) $request->query->get('optionName', ''));

        $items = $optionRepository->findForAdmin(
            $category !== '' ? $category : null,
            $domain !== '' ? $domain : null,
            $optionName !== '' ? $optionName : null,
        );

        $rows = [];
        foreach ($items as $opt) {
            $rows[] = $this->optionToArray($opt);
        }

        return $this->json(['items' => $rows]);
    }

    #[Route('/options/{id}', name: 'api_admin_options_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function optionsShow(int $id, OptionRepository $optionRepository): JsonResponse
    {
        $opt = $optionRepository->find($id);
        if (!$opt instanceof Option) {
            return $this->json(['error' => 'not_found'], 404);
        }

        return $this->json($this->optionToArray($opt));
    }

    #[Route('/options', name: 'api_admin_options_create', methods: ['POST'])]
    public function optionsCreate(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $data = $this->decodeJsonObject($request);
        if ($data === null) {
            return $this->json(['error' => 'invalid_json'], Response::HTTP_BAD_REQUEST);
        }

        $errors = $this->validateOptionPayload($data, false);
        if ($errors !== []) {
            return $this->json(['error' => 'validation', 'details' => $errors], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $opt = new Option();
        $this->applyOptionFromPayload($opt, $data, false);
        $em->persist($opt);
        $em->flush();

        return $this->json($this->optionToArray($opt), Response::HTTP_CREATED);
    }

    #[Route('/options/{id}', name: 'api_admin_options_replace', methods: ['PUT'], requirements: ['id' => '\d+'])]
    public function optionsReplace(int $id, Request $request, OptionRepository $optionRepository, EntityManagerInterface $em): JsonResponse
    {
        $opt = $optionRepository->find($id);
        if (!$opt instanceof Option) {
            return $this->json(['error' => 'not_found'], 404);
        }

        $data = $this->decodeJsonObject($request);
        if ($data === null) {
            return $this->json(['error' => 'invalid_json'], Response::HTTP_BAD_REQUEST);
        }

        $errors = $this->validateOptionPayload($data, false);
        if ($errors !== []) {
            return $this->json(['error' => 'validation', 'details' => $errors], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->applyOptionFromPayload($opt, $data, false);
        $em->flush();

        return $this->json($this->optionToArray($opt));
    }

    #[Route('/options/{id}', name: 'api_admin_options_patch', methods: ['PATCH'], requirements: ['id' => '\d+'])]
    public function optionsPatch(int $id, Request $request, OptionRepository $optionRepository, EntityManagerInterface $em): JsonResponse
    {
        $opt = $optionRepository->find($id);
        if (!$opt instanceof Option) {
            return $this->json(['error' => 'not_found'], 404);
        }

        $data = $this->decodeJsonObject($request);
        if ($data === null) {
            return $this->json(['error' => 'invalid_json'], Response::HTTP_BAD_REQUEST);
        }

        $errors = $this->validateOptionPayload($data, true);
        if ($errors !== []) {
            return $this->json(['error' => 'validation', 'details' => $errors], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->applyOptionFromPayload($opt, $data, true);
        $em->flush();

        return $this->json($this->optionToArray($opt));
    }

    #[Route('/options/{id}', name: 'api_admin_options_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function optionsDelete(int $id, OptionRepository $optionRepository, EntityManagerInterface $em): JsonResponse
    {
        $opt = $optionRepository->find($id);
        if (!$opt instanceof Option) {
            return $this->json(['error' => 'not_found'], 404);
        }

        $em->remove($opt);
        $em->flush();

        return $this->json(['ok' => true]);
    }

    #[Route('/imap/fetch-inbox/runs', name: 'api_admin_imap_fetch_inbox_runs', methods: ['GET'])]
    public function imapFetchInboxRuns(Request $request, ImapFetchRunRepository $imapFetchRunRepository): JsonResponse
    {
        $page = max(1, (int) $request->query->get('page', 1));
        $perPage = self::IMAP_RUNS_PER_PAGE;
        $p = $imapFetchRunRepository->createAdminPaginator($page, $perPage);

        $total = $p->count();
        $pageCount = (int) ceil($total / $perPage);

        $rows = [];
        /** @var ImapFetchRun $r */
        foreach ($p as $r) {
            $rows[] = [
                'id' => $r->getId(),
                'startedAt' => $r->getStartedAt()->format(\DateTimeInterface::ATOM),
                'finishedAt' => $r->getFinishedAt()?->format(\DateTimeInterface::ATOM),
                'durationMs' => $r->getDurationMs(),
                'projectFilterId' => $r->getProjectFilterId(),
                'retentionDays' => $r->getRetentionDays(),
                'totalOrganizations' => $r->getTotalOrganizations(),
                'totalProjects' => $r->getTotalProjects(),
                'totalUnseen' => $r->getTotalUnseen(),
                'totalTickets' => $r->getTotalTickets(),
                'totalFailures' => $r->getTotalFailures(),
            ];
        }

        return $this->json([
            'runs' => $rows,
            'pagination' => [
                'page' => $page,
                'pageCount' => $pageCount,
                'total' => $total,
                'perPage' => $perPage,
            ],
        ]);
    }

    #[Route('/imap/fetch-inbox/runs/{id}', name: 'api_admin_imap_fetch_inbox_run_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function imapFetchInboxRunShow(int $id, ImapFetchRunRepository $imapFetchRunRepository): JsonResponse
    {
        $run = $imapFetchRunRepository->find($id);
        if (!$run instanceof ImapFetchRun) {
            return $this->json(['error' => 'not_found'], 404);
        }

        $projects = [];
        foreach ($run->getProjects() as $p) {
            $projects[] = [
                'id' => $p->getId(),
                'organizationId' => $p->getOrganization()?->getId(),
                'organizationName' => $p->getOrganizationName(),
                'projectId' => $p->getProject()?->getId(),
                'projectName' => $p->getProjectName(),
                'imapHost' => $p->getImapHost(),
                'imapPort' => $p->getImapPort(),
                'imapTls' => $p->isImapTls(),
                'imapMailbox' => $p->getImapMailbox(),
                'unseenCount' => $p->getUnseenCount(),
                'ticketsCreated' => $p->getTicketsCreated(),
                'failureCount' => $p->getFailureCount(),
                'connectionError' => $p->getConnectionError(),
                'mailboxError' => $p->getMailboxError(),
                'failures' => $p->getFailuresJson(),
            ];
        }

        usort($projects, static function (array $a, array $b): int {
            $c = strcmp((string) $a['organizationName'], (string) $b['organizationName']);
            if ($c !== 0) return $c;
            return strcmp((string) $a['projectName'], (string) $b['projectName']);
        });

        return $this->json([
            'run' => [
                'id' => $run->getId(),
                'startedAt' => $run->getStartedAt()->format(\DateTimeInterface::ATOM),
                'finishedAt' => $run->getFinishedAt()?->format(\DateTimeInterface::ATOM),
                'durationMs' => $run->getDurationMs(),
                'projectFilterId' => $run->getProjectFilterId(),
                'retentionDays' => $run->getRetentionDays(),
                'totalOrganizations' => $run->getTotalOrganizations(),
                'totalProjects' => $run->getTotalProjects(),
                'totalUnseen' => $run->getTotalUnseen(),
                'totalTickets' => $run->getTotalTickets(),
                'totalFailures' => $run->getTotalFailures(),
            ],
            'projects' => $projects,
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decodeJsonObject(Request $request): ?array
    {
        $raw = $request->getContent();
        if ($raw === '' || $raw === '0') {
            return [];
        }

        try {
            $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        return \is_array($data) ? $data : null;
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return list<string>
     */
    private function validateOptionPayload(array $data, bool $partial): array
    {
        $errors = [];
        $keys = ['optionValue', 'optionName', 'category', 'domain', 'comment'];

        foreach ($keys as $key) {
            if (!\array_key_exists($key, $data)) {
                if (!$partial && \in_array($key, ['optionValue', 'optionName', 'category'], true)) {
                    $errors[] = 'missing:'.$key;
                }

                continue;
            }

            $value = $data[$key];
            if ($key === 'domain' || $key === 'comment') {
                if ($value !== null && !\is_string($value)) {
                    $errors[] = 'type:'.$key;
                }

                continue;
            }

            if (!\is_string($value)) {
                $errors[] = 'type:'.$key;

                continue;
            }

            if (!$partial && \in_array($key, ['optionName', 'category'], true) && trim($value) === '') {
                $errors[] = 'empty:'.$key;
            }
        }

        return $errors;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function applyOptionFromPayload(Option $opt, array $data, bool $partial): void
    {
        if (!$partial || \array_key_exists('optionValue', $data)) {
            $opt->setOptionValue(\is_string($data['optionValue'] ?? null) ? $data['optionValue'] : '');
        }
        if (!$partial || \array_key_exists('optionName', $data)) {
            $opt->setOptionName(\is_string($data['optionName'] ?? null) ? $data['optionName'] : '');
        }
        if (!$partial || \array_key_exists('category', $data)) {
            $opt->setCategory(\is_string($data['category'] ?? null) ? $data['category'] : '');
        }
        if (!$partial || \array_key_exists('domain', $data)) {
            $dom = $data['domain'] ?? null;
            $opt->setDomain(\is_string($dom) ? $dom : null);
        }
        if (!$partial || \array_key_exists('comment', $data)) {
            $c = $data['comment'] ?? null;
            $opt->setComment(\is_string($c) ? $c : null);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function optionToArray(Option $o): array
    {
        return [
            'id' => $o->getId(),
            'optionValue' => $o->getOptionValue(),
            'optionName' => $o->getOptionName(),
            'domain' => $o->getDomain(),
            'category' => $o->getCategory(),
            'comment' => $o->getComment(),
        ];
    }
}
