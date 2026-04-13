<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Organization;
use App\Entity\Project;
use App\Entity\User;
use App\Enum\EnvironmentWizardStep;
use App\Enum\OrganizationPlan;
use App\Form\UserProfileFormType;
use App\Form\FirstProjectFormType;
use App\Form\OrganizationType;
use App\Service\EnvironmentSetupWizard;
use App\Service\SiteVitrinePlansCatalog;
use App\Service\UserActionLogger;
use Doctrine\ORM\EntityManagerInterface;
use App\Controller\AbstractController;
use App\Http\AcceptJson;
use App\Http\FormErrorPayload;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('IS_AUTHENTICATED_FULLY')]
#[Route('/initialisation')]
final class EnvironmentSetupController extends AbstractController
{
    public function __construct(
        private readonly EnvironmentSetupWizard $environmentSetupWizard,
    ) {
    }

    /**
     * Dernière étape logique : toutes les données sont déjà en place (ex. après une réimport partielle).
     */
    #[Route('/terminer', name: 'app_environment_setup_finalize')]
    public function finalize(
        Request $request,
        EntityManagerInterface $entityManager,
        UserActionLogger $userActionLogger,
    ): Response {
        $user = $this->requireUser();

        if (!$user->needsEnvironmentSetup()) {
            return $this->redirectToRoute('app_home');
        }

        if ($this->environmentSetupWizard->resolveStep($user) !== EnvironmentWizardStep::Complete) {
            return $this->redirectToRoute(
                $this->environmentSetupWizard->routeNameForStep($this->environmentSetupWizard->resolveStep($user)),
            );
        }

        $user->setEnvironmentInitializedAt(new \DateTimeImmutable());
        $entityManager->flush();

        $userActionLogger->log('ENVIRONMENT_SETUP_FINALIZED', $user, null, [], $request);

        $this->addFlash('success', $this->trans('flash.environment_finalized'));

        return $this->redirectToRoute('app_home');
    }

    #[Route('/organisation', name: 'app_environment_setup_organization')]
    public function organization(
        Request $request,
        EntityManagerInterface $entityManager,
        UserActionLogger $userActionLogger,
        CsrfTokenManagerInterface $csrfTokenManager,
    ): Response {
        $user = $this->requireUser();

        if (!$user->needsEnvironmentSetup()) {
            return $this->redirectToRoute('app_home');
        }

        $primaryOrg = $user->getPrimaryOrganization();
        $expected = $this->environmentSetupWizard->resolveStep($user);

        $allowedHere = $expected === EnvironmentWizardStep::Organization
            || ($primaryOrg instanceof Organization && $user->needsEnvironmentSetup());

        if (!$allowedHere) {
            return $this->redirectToRoute($this->environmentSetupWizard->routeNameForStep($expected));
        }

        if ($request->isMethod('GET') && !AcceptJson::wants($request)) {
            return $this->redirect('/app/initialisation/organisation');
        }

        $organization = $primaryOrg instanceof Organization ? $primaryOrg : new Organization();
        $wasNewOrg = null === $organization->getId();

        $form = $this->createForm(OrganizationType::class, $organization);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($wasNewOrg) {
                $entityManager->persist($organization);
                $user->addOrganization($organization);

                $roles = $user->getRoles();
                if (!\in_array('ROLE_ADMIN', $roles, true) && !\in_array('ROLE_GESTIONNAIRE', $roles, true)) {
                    $roles[] = 'ROLE_GESTIONNAIRE';
                    $user->setRoles(array_values(array_unique($roles)));
                }
            }

            $entityManager->flush();

            $userActionLogger->log(
                'ENVIRONMENT_SETUP_ORGANIZATION',
                $user,
                null,
                [
                    'organizationId' => $organization->getId(),
                    'name' => $organization->getName(),
                    'created' => $wasNewOrg,
                ],
                $request,
            );

            $next = $this->environmentSetupWizard->resolveStep($user);
            if ($next === EnvironmentWizardStep::Complete) {
                return $this->redirectToRoute('app_environment_setup_finalize');
            }

            return $this->redirectToRoute($this->environmentSetupWizard->routeNameForStep($next));
        }

        if ($form->isSubmitted() && !$form->isValid()) {
            if (AcceptJson::prefersFormJsonErrors($request)) {
                $payload = FormErrorPayload::fromForm($form);

                return $this->json([
                    'error' => 'validation_failed',
                    'fieldErrors' => $payload['fieldErrors'],
                    'formErrors' => $payload['formErrors'],
                    'message' => FormErrorPayload::summaryMessage($payload, $this->trans('flash.form_invalid')),
                    'csrf' => $csrfTokenManager->getToken('organization')->getValue(),
                ], 422);
            }

            $this->addFlash('danger', $this->trans('flash.form_invalid'));
        }

        return $this->redirect('/app/initialisation/organisation');
    }

    #[Route('/plan', name: 'app_environment_setup_plan')]
    public function plan(
        Request $request,
        EntityManagerInterface $entityManager,
        UserActionLogger $userActionLogger,
        SiteVitrinePlansCatalog $siteVitrinePlansCatalog,
        CsrfTokenManagerInterface $csrfTokenManager,
    ): Response {
        $user = $this->requireUser();

        if (!$user->needsEnvironmentSetup()) {
            return $this->redirectToRoute('app_home');
        }

        $expected = $this->environmentSetupWizard->resolveStep($user);
        if (!\in_array($expected, [EnvironmentWizardStep::Plan, EnvironmentWizardStep::Profile, EnvironmentWizardStep::Project], true)) {
            return $this->redirectToRoute($this->environmentSetupWizard->routeNameForStep($expected));
        }

        if ($request->isMethod('GET') && !AcceptJson::wants($request)) {
            return $this->redirect('/app/initialisation/plan');
        }

        $organization = $user->getPrimaryOrganization();
        if (!$organization instanceof Organization) {
            return $this->redirectToRoute('app_environment_setup_organization');
        }

        $plans = $siteVitrinePlansCatalog->getPlans();
        $allowedPlanIds = [];
        foreach ($plans as $p) {
            if (!\is_array($p)) {
                continue;
            }
            $pid = (string) ($p['id'] ?? '');
            if ($pid !== '') {
                $allowedPlanIds[$pid] = true;
            }
        }

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('environment_plan', (string) $request->request->get('_token'))) {
                throw $this->createAccessDeniedException($this->trans('error.invalid_csrf'));
            }

            $submitted = $request->request->getString('plan');
            $planEnum = OrganizationPlan::tryFrom($submitted);
            if ($planEnum === null || !isset($allowedPlanIds[$submitted])) {
                if (AcceptJson::prefersFormJsonErrors($request)) {
                    $msg = $this->trans('flash.plan_required');

                    return $this->json([
                        'error' => 'validation_failed',
                        'fieldErrors' => ['plan' => [$msg]],
                        'formErrors' => [],
                        'message' => $msg,
                        'csrf' => $csrfTokenManager->getToken('environment_plan')->getValue(),
                    ], 422);
                }
                $this->addFlash('danger', $this->trans('flash.plan_required'));
            } else {
                $organization->setPlan($planEnum);
                $entityManager->flush();
                $userActionLogger->log(
                    'ENVIRONMENT_SETUP_PLAN',
                    $user,
                    null,
                    [
                        'organizationId' => $organization->getId(),
                        'plan' => $organization->getPlan()?->value,
                    ],
                    $request,
                );

                $next = $this->environmentSetupWizard->resolveStep($user);
                if ($next === EnvironmentWizardStep::Complete) {
                    return $this->redirectToRoute('app_environment_setup_finalize');
                }

                return $this->redirectToRoute($this->environmentSetupWizard->routeNameForStep($next));
            }
        }

        return $this->redirect('/app/initialisation/plan');
    }

    #[Route('/profil', name: 'app_environment_setup_profile')]
    public function profile(
        Request $request,
        EntityManagerInterface $entityManager,
        UserActionLogger $userActionLogger,
        CsrfTokenManagerInterface $csrfTokenManager,
    ): Response {
        $user = $this->requireUser();

        if (!$user->needsEnvironmentSetup()) {
            return $this->redirectToRoute('app_home');
        }

        $expected = $this->environmentSetupWizard->resolveStep($user);
        if (!\in_array($expected, [EnvironmentWizardStep::Profile, EnvironmentWizardStep::Project], true)) {
            return $this->redirectToRoute($this->environmentSetupWizard->routeNameForStep($expected));
        }

        if ($request->isMethod('GET') && !AcceptJson::wants($request)) {
            return $this->redirect('/app/initialisation/profil');
        }

        $form = $this->createForm(UserProfileFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();
            $userActionLogger->log(
                'ENVIRONMENT_SETUP_PROFILE',
                $user,
                null,
                [
                    'displayName' => $user->getDisplayName(),
                    'avatarColor' => $user->getAvatarColor(),
                ],
                $request,
            );

            $next = $this->environmentSetupWizard->resolveStep($user);
            if ($next === EnvironmentWizardStep::Complete) {
                return $this->redirectToRoute('app_environment_setup_finalize');
            }

            return $this->redirectToRoute($this->environmentSetupWizard->routeNameForStep($next));
        }

        if ($form->isSubmitted() && !$form->isValid()) {
            if (AcceptJson::prefersFormJsonErrors($request)) {
                $payload = FormErrorPayload::fromForm($form);

                return $this->json([
                    'error' => 'validation_failed',
                    'fieldErrors' => $payload['fieldErrors'],
                    'formErrors' => $payload['formErrors'],
                    'message' => FormErrorPayload::summaryMessage($payload, $this->trans('flash.form_invalid')),
                    'csrf' => $csrfTokenManager->getToken('user_profile_form')->getValue(),
                ], 422);
            }

            $this->addFlash('danger', $this->trans('flash.form_invalid'));
        }

        return $this->redirect('/app/initialisation/profil');
    }

    #[Route('/projet', name: 'app_environment_setup_project')]
    public function project(
        Request $request,
        EntityManagerInterface $entityManager,
        UserActionLogger $userActionLogger,
        CsrfTokenManagerInterface $csrfTokenManager,
    ): Response {
        $user = $this->requireUser();

        if (!$user->needsEnvironmentSetup()) {
            return $this->redirectToRoute('app_home');
        }

        $expected = $this->environmentSetupWizard->resolveStep($user);
        if ($expected !== EnvironmentWizardStep::Project) {
            return $this->redirectToRoute($this->environmentSetupWizard->routeNameForStep($expected));
        }

        if ($request->isMethod('GET') && !AcceptJson::wants($request)) {
            return $this->redirect('/app/initialisation/projet');
        }

        $organization = $user->getPrimaryOrganization();
        if (!$organization instanceof Organization) {
            return $this->redirectToRoute('app_environment_setup_organization');
        }

        $form = $this->createForm(FirstProjectFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $name = (string) $form->get('name')->getData();
            $proj = (new Project())
                ->setName(trim($name))
                ->setWebhookToken(bin2hex(random_bytes(16)));
            $organization->addProject($proj);

            $entityManager->persist($proj);
            $user->setEnvironmentInitializedAt(new \DateTimeImmutable());
            $entityManager->flush();

            $userActionLogger->log(
                'ENVIRONMENT_SETUP_PROJECT',
                $user,
                null,
                [
                    'projectId' => $proj->getId(),
                    'name' => $proj->getName(),
                    'organizationId' => $organization->getId(),
                ],
                $request,
            );

            $this->addFlash('success', $this->trans('flash.environment_ready'));

            return $this->redirectToRoute('app_home');
        }

        if ($form->isSubmitted() && !$form->isValid()) {
            if (AcceptJson::prefersFormJsonErrors($request)) {
                $payload = FormErrorPayload::fromForm($form);

                return $this->json([
                    'error' => 'validation_failed',
                    'fieldErrors' => $payload['fieldErrors'],
                    'formErrors' => $payload['formErrors'],
                    'message' => FormErrorPayload::summaryMessage($payload, $this->trans('flash.form_invalid')),
                    'csrf' => $csrfTokenManager->getToken('first_project_form')->getValue(),
                ], 422);
            }

            $this->addFlash('danger', $this->trans('flash.form_invalid'));
        }

        return $this->redirect('/app/initialisation/projet');
    }

    private function requireUser(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw new \LogicException('Utilisateur attendu.');
        }

        return $user;
    }
}
