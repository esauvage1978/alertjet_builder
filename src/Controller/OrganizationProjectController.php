<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Organization;
use App\Entity\Project;
use App\Entity\User;
use App\Form\ManagerProjectFormType;
use App\Http\AcceptJson;
use App\Repository\ProjectRepository;
use App\Security\Voter\OrganizationVoter;
use App\Security\Voter\ProjectVoter;
use App\Service\ProjectImapConnectionTester;
use App\Service\SecretBoxCrypto;
use App\Service\UserActionLogger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use App\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
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
        Request $request,
        CsrfTokenManagerInterface $csrfTokenManager,
    ): Response {
        $this->denyAccessUnlessOrganizationScope($organization);

        $search = (string) $request->query->get('q', '');
        $allProjects = $projectRepository->findByOrganizationOrderedByName($organization);
        $projects = $search !== ''
            ? $projectRepository->findFilteredByOrganization($organization, $search)
            : $allProjects;

        if (!AcceptJson::wants($request)) {
            $qs = $request->getQueryString();

            return $this->redirect('/app/organization/'.$organization->getPublicToken().'/projects'.($qs !== null && $qs !== '' ? '?'.$qs : ''));
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
        ]);
    }

    /** @return array<string, mixed> */
    private function serializeProjectRow(Project $p): array
    {
        return [
            'id' => $p->getId(),
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
        ProjectRepository $projectRepository,
    ): Response {
        $this->denyAccessUnlessOrganizationScope($organization);

        $token = new CsrfToken('new_project_'.$organization->getId(), (string) $request->request->get('_token'));
        if (!$csrfTokenManager->isTokenValid($token)) {
            throw $this->createAccessDeniedException($this->trans('error.invalid_csrf'));
        }

        $name = trim((string) $request->request->get('name', ''));
        if ($name === '' || mb_strlen($name) > 180) {
            $this->addFlash('danger', $this->trans('org.projects.flash_invalid_name'));

            return $this->redirect('/app/organization/'.$organization->getPublicToken().'/projects');
        }

        if ($projectRepository->existsOtherWithNameInOrganization($organization, $name)) {
            $this->addFlash('danger', $this->trans('validation.project_name.unique_in_org', [], 'validators'));

            return $this->redirect('/app/organization/'.$organization->getPublicToken().'/projects');
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
            ['organizationId' => $organization->getId(), 'projectId' => $project->getId(), 'name' => $project->getName()],
            $request,
        );

        $this->addFlash('success', $this->trans('org.projects.flash_created', ['%name%' => $project->getName()]));

        return $this->redirect('/app/organization/'.$organization->getPublicToken().'/projects');
    }

    #[Route('/organisation/{orgToken}/projets/{projectId}/edit', name: 'app_organization_project_edit', requirements: ['orgToken' => '[a-f0-9]{12}', 'projectId' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(
        #[MapEntity(mapping: ['orgToken' => 'publicToken'])] Organization $organization,
        #[MapEntity(id: 'projectId')] Project $project,
        Request $request,
        EntityManagerInterface $entityManager,
        UserActionLogger $userActionLogger,
        SecretBoxCrypto $secretBoxCrypto,
    ): Response {
        $this->denyAccessUnlessOrganizationScope($organization);

        if ($project->getOrganization()?->getId() !== $organization->getId()) {
            throw new NotFoundHttpException();
        }

        $this->denyAccessUnlessGranted(ProjectVoter::MANAGE, $project);

        if ($request->isMethod('GET')) {
            return $this->redirect('/app/organization/'.$organization->getPublicToken().'/projects/'.$project->getId().'/edit');
        }

        $form = $this->createForm(ManagerProjectFormType::class, $project, [
            'organization' => $organization,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $project->setName(trim($project->getName()));
            $plainImap = $form->get('imapPassword')->getData();
            if (\is_string($plainImap) && $plainImap !== '') {
                $project->setImapPasswordCipher($secretBoxCrypto->encrypt($plainImap));
            }
            $this->pruneTicketHandlersOutsideOrganization($project, $organization);
            $entityManager->flush();

            /** @var User|null $actor */
            $actor = $this->getUser();
            $userActionLogger->log(
                'MANAGER_PROJECT_UPDATED',
                $actor instanceof User ? $actor : null,
                null,
                [
                    'organizationId' => $organization->getId(),
                    'projectId' => $project->getId(),
                    'handlerIds' => $project->getTicketHandlers()->map(static fn (User $u) => $u->getId())->getValues(),
                ],
                $request,
            );
            $this->addFlash('success', $this->trans('flash.manager_project_updated'));

            $fragment = 'pe-pane-general';
            if ($form->has('_active_tab')) {
                $t = trim((string) $form->get('_active_tab')->getData());
                if (preg_match('/^pe-pane-[a-z0-9-]+$/', $t) === 1) {
                    $fragment = $t;
                }
            }

            return $this->redirect($this->generateUrl('app_organization_project_edit', [
                'orgToken' => $organization->getPublicToken(),
                'projectId' => $project->getId(),
            ]).'#'.$fragment);
        }

        if ($form->isSubmitted() && !$form->isValid()) {
            $this->addFlash('danger', $this->trans('flash.form_invalid'));
        }

        return $this->redirect('/app/organization/'.$organization->getPublicToken().'/projects/'.$project->getId().'/edit');
    }

    #[Route('/organisation/{orgToken}/projets/{projectId}/test-imap', name: 'app_organization_project_test_imap', requirements: ['orgToken' => '[a-f0-9]{12}', 'projectId' => '\d+'], methods: ['POST'])]
    public function testImap(
        #[MapEntity(mapping: ['orgToken' => 'publicToken'])] Organization $organization,
        #[MapEntity(id: 'projectId')] Project $project,
        Request $request,
        ProjectImapConnectionTester $imapConnectionTester,
        CsrfTokenManagerInterface $csrfTokenManager,
    ): Response {
        $this->denyAccessUnlessOrganizationScope($organization);

        if ($project->getOrganization()?->getId() !== $organization->getId()) {
            throw new NotFoundHttpException();
        }

        $this->denyAccessUnlessGranted(ProjectVoter::MANAGE, $project);

        $token = new CsrfToken('test_imap_'.$project->getId(), (string) $request->request->get('_token'));
        if (!$csrfTokenManager->isTokenValid($token)) {
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

        $this->addFlash(
            $result->success ? 'success' : 'danger',
            $this->trans($result->messageKey, $params),
        );

        return $this->redirect('/app/organization/'.$organization->getPublicToken().'/projects/'.$project->getId().'/edit#pe-pane-mail');
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
