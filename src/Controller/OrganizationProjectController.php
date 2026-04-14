<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Organization;
use App\Entity\Project;
use App\Entity\User;
use App\Form\ManagerProjectFormType;
use App\Http\AcceptJson;
use App\Repository\ProjectRepository;
use App\Repository\UserRepository;
use App\Security\Voter\OrganizationVoter;
use App\Security\Voter\ProjectVoter;
use App\Service\ProjectAuditHelper;
use App\Service\ProjectImapConnectionTester;
use App\Service\SecretBoxCrypto;
use App\Service\UserActionLogger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use App\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('IS_AUTHENTICATED_FULLY')]
final class OrganizationProjectController extends AbstractController
{
    #[Route('/organisation/{orgToken}/projets', name: 'app_organization_projects', requirements: ['orgToken' => '[a-f0-9]{12}'], methods: ['GET'])]
    public function index(
        #[MapEntity(mapping: ['orgToken' => 'publicToken'])] Organization $organization,
        ProjectRepository $projectRepository,
        EntityManagerInterface $entityManager,
        Request $request,
        CsrfTokenManagerInterface $csrfTokenManager,
    ): Response {
        $this->denyAccessUnlessOrganizationScope($organization);

        $search = (string) $request->query->get('q', '');
        $allProjects = $projectRepository->findByOrganizationOrderedByName($organization);
        $projects = $search !== ''
            ? $projectRepository->findFilteredByOrganization($organization, $search)
            : $allProjects;

        if ($this->ensureProjectsHavePublicTokens($allProjects, $projectRepository)) {
            $entityManager->flush();
        }

        if (!AcceptJson::wants($request)) {
            $qs = $request->getQueryString();

            return $this->redirect('/app/projects'.($qs !== null && $qs !== '' ? '?'.$qs : ''));
        }

        return $this->json([
            'migrated' => true,
            'organization' => [
                'id' => $organization->getId(),
                'name' => $organization->getName(),
                'publicToken' => $organization->getPublicToken(),
            ],
            'projects' => array_map(fn (Project $p) => $this->serializeProjectRow($p), $projects),
            'total' => \count($allProjects),
            'q' => $search,
            'newProjectCsrf' => $csrfTokenManager->getToken('new_project_'.$organization->getId())->getValue(),
            /** Un seul jeton pour toute suppression dans l’org. (évite dépendance à un champ par ligne / cache navigateur) */
            'deleteProjectCsrf' => $csrfTokenManager->getToken('delete_org_project_'.$organization->getId())->getValue(),
        ]);
    }

    /**
     * Anciens enregistrements peuvent avoir public_token vide en base → URLs SPA et MapEntity cassées.
     *
     * @param list<Project> $projects
     */
    private function ensureProjectsHavePublicTokens(array $projects, ProjectRepository $projectRepository): bool
    {
        $changed = false;
        foreach ($projects as $project) {
            $t = $project->getPublicToken();
            if ($t === '' || \strlen($t) !== Project::PUBLIC_TOKEN_LENGTH) {
                $projectRepository->assignUniquePublicToken($project);
                $changed = true;
            }
        }

        return $changed;
    }

    /** @return array<string, mixed> */
    private function serializeProjectRow(Project $p): array
    {
        return [
            'id' => $p->getId(),
            'publicToken' => $p->getPublicToken(),
            'public_token' => $p->getPublicToken(),
            'name' => $p->getName(),
            'webhookTokenPrefix' => mb_substr($p->getWebhookToken(), 0, 16),
            'createdAt' => $p->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'ticketCount' => $p->getTickets()->count(),
            'handlers' => $p->getTicketHandlers()->map(static fn (User $u) => [
                'id' => $u->getId(),
                'initials' => $u->getAvatarInitials(),
                'avatarColor' => $u->getAvatarColorOrDefault(),
                'avatarForegroundColor' => $u->getAvatarForegroundColorOrDefault(),
                'label' => $u->getDisplayNameForGreeting(),
            ])->getValues(),
        ];
    }

    #[Route('/organisation/{orgToken}/projets/nouveau', name: 'app_organization_project_new', requirements: ['orgToken' => '[a-f0-9]{12}'], methods: ['POST'])]
    public function new(
        #[MapEntity(mapping: ['orgToken' => 'publicToken'])] Organization $organization,
        Request $request,
        EntityManagerInterface $entityManager,
        CsrfTokenManagerInterface $csrfTokenManager,
        UserActionLogger $userActionLogger,
        ProjectAuditHelper $projectAuditHelper,
        ProjectRepository $projectRepository,
    ): Response {
        $this->denyAccessUnlessOrganizationScope($organization);

        $wantsJson = AcceptJson::wants($request) || $request->isXmlHttpRequest();

        $token = new CsrfToken('new_project_'.$organization->getId(), (string) $request->request->get('_token'));
        if (!$csrfTokenManager->isTokenValid($token)) {
            if ($wantsJson) {
                return $this->json([
                    'ok' => false,
                    'error' => 'csrf',
                    'message' => $this->trans('error.invalid_csrf'),
                ], 403);
            }

            throw $this->createAccessDeniedException($this->trans('error.invalid_csrf'));
        }

        $name = trim((string) $request->request->get('name', ''));
        if ($name === '' || mb_strlen($name) > 180) {
            if ($wantsJson) {
                return $this->json([
                    'ok' => false,
                    'error' => 'invalid_name',
                    'message' => $this->trans('org.projects.flash_invalid_name'),
                ], 422);
            }
            $this->addFlash('danger', $this->trans('org.projects.flash_invalid_name'));

            return $this->redirect('/app/projects');
        }

        if ($projectRepository->existsOtherWithNameInOrganization($organization, $name)) {
            if ($wantsJson) {
                return $this->json([
                    'ok' => false,
                    'error' => 'duplicate_name',
                    'message' => $this->trans('validation.project_name.unique_in_org', [], 'validators'),
                ], 422);
            }
            $this->addFlash('danger', $this->trans('validation.project_name.unique_in_org', [], 'validators'));

            return $this->redirect('/app/projects');
        }

        $project = (new Project())
            ->setName($name)
            ->setWebhookToken(bin2hex(random_bytes(16)))
            ->setOrganization($organization);

        $entityManager->persist($project);
        $entityManager->flush();

        /** @var User|null $actor */
        $actor = $this->getUser();
        $userActionLogger->log(
            'MANAGER_PROJECT_CREATED',
            $actor instanceof User ? $actor : null,
            null,
            array_merge($projectAuditHelper->contextualize($project, $organization), [
                'event' => 'created',
                'initialSnapshot' => $projectAuditHelper->snapshot($project),
                'webhookTokenPrefix' => mb_substr($project->getWebhookToken(), 0, 10).'…',
            ]),
            $request,
        );

        if ($wantsJson) {
            return $this->json([
                'ok' => true,
                'project' => $this->serializeProjectRow($project),
                'message' => $this->trans('org.projects.flash_created', ['%name%' => $project->getName()]),
            ]);
        }

        $this->addFlash('success', $this->trans('org.projects.flash_created', ['%name%' => $project->getName()]));

        return $this->redirect('/app/projects');
    }

    #[Route('/organisation/{orgToken}/projets/{projectToken}/supprimer', name: 'app_organization_project_delete', requirements: ['orgToken' => '[a-f0-9]{12}', 'projectToken' => '[a-f0-9]{12}'], methods: ['POST'])]
    public function delete(
        #[MapEntity(mapping: ['orgToken' => 'publicToken'])] Organization $organization,
        #[MapEntity(mapping: ['projectToken' => 'publicToken'])] Project $project,
        Request $request,
        EntityManagerInterface $entityManager,
        ProjectRepository $projectRepository,
        CsrfTokenManagerInterface $csrfTokenManager,
        UserActionLogger $userActionLogger,
        ProjectAuditHelper $projectAuditHelper,
    ): Response {
        $this->denyAccessUnlessOrganizationScope($organization);

        if ($project->getOrganization()?->getId() !== $organization->getId()) {
            throw new NotFoundHttpException();
        }

        $this->denyAccessUnlessGranted(ProjectVoter::MANAGE, $project);

        $wantsJson = AcceptJson::wants($request) || $request->isXmlHttpRequest();

        $token = new CsrfToken('delete_org_project_'.$organization->getId(), (string) $request->request->get('_token'));
        if (!$csrfTokenManager->isTokenValid($token)) {
            if ($wantsJson) {
                return $this->json([
                    'ok' => false,
                    'error' => 'csrf',
                    'message' => $this->trans('error.invalid_csrf'),
                ], 403);
            }

            throw $this->createAccessDeniedException($this->trans('error.invalid_csrf'));
        }

        if ($projectRepository->countByOrganization($organization) <= 1) {
            if ($wantsJson) {
                return $this->json([
                    'ok' => false,
                    'error' => 'last_project',
                    'message' => $this->trans('org.projects.delete_last_forbidden'),
                ], 422);
            }
            $this->addFlash('danger', $this->trans('org.projects.delete_last_forbidden'));

            return $this->redirect('/app/projects');
        }

        $projectName = $project->getName();
        $snapshotBefore = $projectAuditHelper->snapshot($project);
        $deleteLogDetails = array_merge($projectAuditHelper->contextualize($project, $organization), [
            'event' => 'deleted',
            'initialSnapshot' => $snapshotBefore,
        ]);

        $entityManager->remove($project);
        $entityManager->flush();

        /** @var User|null $actor */
        $actor = $this->getUser();
        $userActionLogger->log(
            'MANAGER_PROJECT_DELETED',
            $actor instanceof User ? $actor : null,
            null,
            $deleteLogDetails,
            $request,
        );

        if ($wantsJson) {
            return $this->json([
                'ok' => true,
                'message' => $this->trans('org.projects.flash_deleted', ['%name%' => $projectName]),
            ]);
        }

        $this->addFlash('success', $this->trans('org.projects.flash_deleted', ['%name%' => $projectName]));

        return $this->redirect('/app/projects');
    }

    #[Route('/organisation/{orgToken}/projets/{projectToken}', name: 'app_organization_project_show', requirements: ['orgToken' => '[a-f0-9]{12}', 'projectToken' => '[a-f0-9]{12}'], methods: ['GET'])]
    public function show(
        #[MapEntity(mapping: ['orgToken' => 'publicToken'])] Organization $organization,
        #[MapEntity(mapping: ['projectToken' => 'publicToken'])] Project $project,
        Request $request,
    ): Response {
        $this->denyAccessUnlessOrganizationScope($organization);

        if ($project->getOrganization()?->getId() !== $organization->getId()) {
            throw new NotFoundHttpException();
        }

        $this->denyAccessUnlessGranted(ProjectVoter::MANAGE, $project);

        if (AcceptJson::wants($request) || $request->isXmlHttpRequest()) {
            return $this->json($this->buildProjectShowPayload($organization, $project));
        }

        return $this->redirect('/app/projects/'.$project->getPublicToken());
    }

    #[Route('/organisation/{orgToken}/projets/{projectToken}/edit', name: 'app_organization_project_edit', requirements: ['orgToken' => '[a-f0-9]{12}', 'projectToken' => '[a-f0-9]{12}'], methods: ['GET', 'POST'])]
    public function edit(
        #[MapEntity(mapping: ['orgToken' => 'publicToken'])] Organization $organization,
        #[MapEntity(mapping: ['projectToken' => 'publicToken'])] Project $project,
        Request $request,
        EntityManagerInterface $entityManager,
        UserActionLogger $userActionLogger,
        ProjectAuditHelper $projectAuditHelper,
        SecretBoxCrypto $secretBoxCrypto,
        CsrfTokenManagerInterface $csrfTokenManager,
        UserRepository $userRepository,
    ): Response {
        $this->denyAccessUnlessOrganizationScope($organization);

        if ($project->getOrganization()?->getId() !== $organization->getId()) {
            throw new NotFoundHttpException();
        }

        $this->denyAccessUnlessGranted(ProjectVoter::MANAGE, $project);

        if ($request->isMethod('GET')) {
            if (AcceptJson::wants($request) || $request->isXmlHttpRequest()) {
                return $this->json($this->buildProjectEditPayload($organization, $project, $csrfTokenManager, $userRepository));
            }

            return $this->redirect('/app/projects/'.$project->getPublicToken().'/edit');
        }

        $wantsJson = AcceptJson::wants($request) || $request->isXmlHttpRequest();

        $snapshotBefore = $projectAuditHelper->snapshot($project);

        $form = $this->createForm(ManagerProjectFormType::class, $project, [
            'organization' => $organization,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                $project->setName(trim($project->getName()));
                $plainImap = $form->get('imapPassword')->getData();
                $imapPasswordWasSet = \is_string($plainImap) && $plainImap !== '';
                if ($imapPasswordWasSet) {
                    $project->setImapPasswordCipher($secretBoxCrypto->encrypt($plainImap));
                }
                $this->pruneTicketHandlersOutsideOrganization($project, $organization);
                $entityManager->flush();

                $snapshotAfter = $projectAuditHelper->snapshot($project);
                $changes = $projectAuditHelper->diff($snapshotBefore, $snapshotAfter, $imapPasswordWasSet);

                /** @var User|null $actor */
                $actor = $this->getUser();
                $userActionLogger->log(
                    'MANAGER_PROJECT_UPDATED',
                    $actor instanceof User ? $actor : null,
                    null,
                    array_merge($projectAuditHelper->contextualize($project, $organization), [
                        'event' => 'updated',
                        'changes' => $changes,
                        'changeCount' => \count($changes),
                        'changedFieldsSummary' => $projectAuditHelper->summarizeChangeFields($changes),
                    ]),
                    $request,
                );

                if ($wantsJson) {
                    return $this->json([
                        'ok' => true,
                        'message' => $this->trans('flash.manager_project_updated'),
                        'project' => $this->serializeProjectRow($project),
                    ]);
                }

                $this->addFlash('success', $this->trans('flash.manager_project_updated'));

                $fragment = 'pe-pane-general';
                if ($form->has('_active_tab')) {
                    $t = trim((string) $form->get('_active_tab')->getData());
                    if (preg_match('/^pe-pane-[a-z0-9-]+$/', $t) === 1) {
                        $fragment = $t;
                    }
                }

                return $this->redirect('/app/projects/'.$project->getPublicToken().'/edit#'.$fragment);
            }

            if ($wantsJson) {
                return $this->json([
                    'ok' => false,
                    'error' => 'validation',
                    'message' => $this->firstFormErrorMessage($form) ?? $this->trans('flash.form_invalid'),
                    'fieldErrors' => $this->collectFormFieldErrors($form),
                ], 422);
            }

            $this->addFlash('danger', $this->trans('flash.form_invalid'));

            return $this->redirect('/app/projects/'.$project->getPublicToken().'/edit');
        }

        if ($wantsJson) {
            return $this->json([
                'ok' => false,
                'error' => 'bad_request',
                'message' => 'Requête invalide ou formulaire non soumis.',
            ], 400);
        }

        return $this->redirect('/app/projects/'.$project->getPublicToken().'/edit');
    }

    /**
     * @return array<string, mixed>
     */
    private function buildProjectEditPayload(
        Organization $organization,
        Project $project,
        CsrfTokenManagerInterface $csrfTokenManager,
        UserRepository $userRepository,
    ): array {
        $members = $userRepository->createQueryBuilderMembersOfOrganization($organization)->getQuery()->getResult();

        return [
            'migrated' => true,
            'organization' => [
                'id' => $organization->getId(),
                'name' => $organization->getName(),
                'publicToken' => $organization->getPublicToken(),
            ],
            'formPrefix' => 'manager_project_form',
            'formCsrf' => $csrfTokenManager->getToken('manager_project_form')->getValue(),
            'testImapCsrf' => $csrfTokenManager->getToken('test_imap_'.$project->getId())->getValue(),
            'members' => array_map(static fn (User $u) => [
                'id' => $u->getId(),
                'email' => $u->getEmail(),
                'displayName' => $u->getDisplayNameForGreeting(),
                'initials' => $u->getAvatarInitials(),
                'avatarColor' => $u->getAvatarColor(),
                'avatarForegroundColor' => $u->getAvatarForegroundColor(),
                'label' => $u->getDisplayNameForGreeting().' ('.$u->getEmail().')',
            ], $members),
            'project' => [
                'publicToken' => $project->getPublicToken(),
                'name' => $project->getName(),
                'createdAt' => $project->getCreatedAt()->format(\DateTimeInterface::ATOM),
                'webhookUrl' => $this->generateUrl('api_webhook_receive', $this->webhookRouteParams($organization, $project), UrlGeneratorInterface::ABSOLUTE_URL),
                'webhookPingUrl' => $this->generateUrl('api_webhook_ping', $this->webhookRouteParams($organization, $project), UrlGeneratorInterface::ABSOLUTE_URL),
                'slaAckTargetMinutes' => $project->getSlaAckTargetMinutes(),
                'slaResolveTargetMinutes' => $project->getSlaResolveTargetMinutes(),
                'imapEnabled' => $project->isImapEnabled(),
                'imapHost' => $project->getImapHost(),
                'imapPort' => $project->getImapPort(),
                'imapTls' => $project->isImapTls(),
                'imapUsername' => $project->getImapUsername(),
                'imapMailbox' => $project->getImapMailbox(),
                'hasImapPassword' => $project->hasStoredImapPassword(),
                'webhookIntegrationEnabled' => $project->isWebhookIntegrationEnabled(),
                'webhookCorsAllowedOrigins' => $project->getWebhookCorsAllowedOrigins() ?? '',
                'phoneIntegrationEnabled' => $project->isPhoneIntegrationEnabled(),
                'internalFormIntegrationEnabled' => $project->isInternalFormIntegrationEnabled(),
                'phoneSchedule' => $project->getPhoneSchedule(),
                'ticketHandlerIds' => $project->getTicketHandlers()->map(static fn (User $u) => $u->getId())->getValues(),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildProjectShowPayload(Organization $organization, Project $project): array
    {
        $row = $this->serializeProjectRow($project);

        return [
            'migrated' => true,
            'organization' => [
                'id' => $organization->getId(),
                'name' => $organization->getName(),
                'publicToken' => $organization->getPublicToken(),
            ],
            'project' => array_merge($row, [
                'webhookUrl' => $this->generateUrl('api_webhook_receive', $this->webhookRouteParams($organization, $project), UrlGeneratorInterface::ABSOLUTE_URL),
                'webhookPingUrl' => $this->generateUrl('api_webhook_ping', $this->webhookRouteParams($organization, $project), UrlGeneratorInterface::ABSOLUTE_URL),
                'slaAckTargetMinutes' => $project->getSlaAckTargetMinutes(),
                'slaResolveTargetMinutes' => $project->getSlaResolveTargetMinutes(),
                'imapEnabled' => $project->isImapEnabled(),
                'imapHost' => $project->getImapHost(),
                'imapPort' => $project->getImapPort(),
                'imapTls' => $project->isImapTls(),
                'imapUsername' => $project->getImapUsername(),
                'imapMailbox' => $project->getImapMailbox(),
                'hasImapPasswordConfigured' => $project->hasStoredImapPassword(),
                'webhookIntegrationEnabled' => $project->isWebhookIntegrationEnabled(),
                'webhookCorsAllowedOrigins' => $project->getWebhookCorsAllowedOrigins() ?? '',
                'phoneIntegrationEnabled' => $project->isPhoneIntegrationEnabled(),
                'internalFormIntegrationEnabled' => $project->isInternalFormIntegrationEnabled(),
                'phoneSchedule' => $project->getPhoneSchedule(),
            ]),
        ];
    }

    /**
     * @return array{orgToken: string, projectToken: string, webhookToken: string}
     */
    private function webhookRouteParams(Organization $organization, Project $project): array
    {
        return [
            'orgToken' => $organization->getPublicToken(),
            'projectToken' => $project->getPublicToken(),
            'webhookToken' => $project->getWebhookToken(),
        ];
    }

    /** @return array<string, list<string>> */
    private function collectFormFieldErrors(FormInterface $form): array
    {
        $out = [];
        foreach ($form->getErrors(true) as $error) {
            $origin = $error->getOrigin();
            $name = $origin->getName();
            if ($name === '_token') {
                continue;
            }
            $path = $this->formFieldPath($origin);
            if ($path === '') {
                $path = '_form';
            }
            if (!isset($out[$path])) {
                $out[$path] = [];
            }
            $out[$path][] = $error->getMessage();
        }

        return $out;
    }

    private function formFieldPath(FormInterface $field): string
    {
        $names = [];
        $f = $field;
        while ($f !== null) {
            $n = $f->getName();
            if ($n !== '') {
                $names[] = $n;
            }
            $parent = $f->getParent();
            if ($parent === null || $parent->getName() === '') {
                break;
            }
            $f = $parent;
        }

        return implode('.', array_reverse($names));
    }

    private function firstFormErrorMessage(FormInterface $form): ?string
    {
        foreach ($form->getErrors(true) as $error) {
            return $error->getMessage();
        }

        return null;
    }

    #[Route('/organisation/{orgToken}/projets/{projectToken}/test-imap', name: 'app_organization_project_test_imap', requirements: ['orgToken' => '[a-f0-9]{12}', 'projectToken' => '[a-f0-9]{12}'], methods: ['POST'])]
    public function testImap(
        #[MapEntity(mapping: ['orgToken' => 'publicToken'])] Organization $organization,
        #[MapEntity(mapping: ['projectToken' => 'publicToken'])] Project $project,
        Request $request,
        ProjectImapConnectionTester $imapConnectionTester,
        CsrfTokenManagerInterface $csrfTokenManager,
        UserActionLogger $userActionLogger,
        ProjectAuditHelper $projectAuditHelper,
    ): Response {
        $this->denyAccessUnlessOrganizationScope($organization);

        if ($project->getOrganization()?->getId() !== $organization->getId()) {
            throw new NotFoundHttpException();
        }

        $this->denyAccessUnlessGranted(ProjectVoter::MANAGE, $project);

        $wantsJson = AcceptJson::wants($request) || $request->isXmlHttpRequest();

        $token = new CsrfToken('test_imap_'.$project->getId(), (string) $request->request->get('_token'));
        if (!$csrfTokenManager->isTokenValid($token)) {
            if ($wantsJson) {
                return $this->json([
                    'ok' => false,
                    'error' => 'invalid_csrf',
                    'message' => $this->trans('error.invalid_csrf'),
                ], 403);
            }

            throw $this->createAccessDeniedException($this->trans('error.invalid_csrf'));
        }

        $result = $imapConnectionTester->test($project);
        $params = $result->messageParameters;
        if (!$result->success && isset($params['%error%'])) {
            $params['%error%'] = trim((string) $params['%error%']);
            if ($params['%error%'] === '') {
                $params['%error%'] = '—';
            }
        }

        $message = $this->trans($result->messageKey, $params);

        /** @var User|null $actor */
        $actor = $this->getUser();
        $userActionLogger->log(
            'PROJECT_IMAP_CONNECTION_TESTED',
            $actor instanceof User ? $actor : null,
            null,
            array_merge($projectAuditHelper->contextualize($project, $organization), [
                'event' => 'imap_test',
                'success' => $result->success,
                'messageKey' => $result->messageKey,
            ]),
            $request,
        );

        if ($wantsJson) {
            return $this->json([
                'ok' => $result->success,
                'message' => $message,
            ]);
        }

        $this->addFlash(
            $result->success ? 'success' : 'danger',
            $message,
        );

        return $this->redirect('/app/projects/'.$project->getPublicToken().'/edit#pe-pane-mail');
    }

    private function denyAccessUnlessOrganizationScope(Organization $organization): void
    {
        if ($this->isGranted('ROLE_ADMIN')) {
            return;
        }
        $this->denyAccessUnlessGranted(OrganizationVoter::EDIT, $organization);
    }

    private function pruneTicketHandlersOutsideOrganization(Project $project, Organization $organization): void
    {
        foreach ($project->getTicketHandlers()->toArray() as $handler) {
            if (!$handler->belongsToOrganization($organization)) {
                $project->removeTicketHandler($handler);
            }
        }
    }
}
