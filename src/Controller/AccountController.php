<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Organization;
use App\Entity\User;
use App\Form\UserProfileFormType;
use App\Http\AcceptJson;
use App\Repository\UserActionLogRepository;
use App\Repository\UserRepository;
use App\Service\UserActionLogger;
use Doctrine\ORM\EntityManagerInterface;
use App\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class AccountController extends AbstractController
{
    #[Route('/verifier-email/{token}', name: 'app_verify_email')]
    public function verifyEmail(
        string $token,
        UserRepository $userRepository,
        EntityManagerInterface $entityManager,
        UserActionLogger $userActionLogger,
        Request $request,
    ): Response {
        $user = $userRepository->findOneByEmailVerificationToken($token);
        if (
            !$user instanceof User
            || $user->getEmailVerificationExpiresAt() === null
            || $user->getEmailVerificationExpiresAt() < new \DateTimeImmutable()
        ) {
            $this->addFlash('danger', $this->trans('flash.verify_invalid'));

            return $this->redirectToRoute('app_login');
        }

        $user->setEmailVerifiedAt(new \DateTimeImmutable());
        $user->clearEmailVerification();
        $entityManager->flush();

        $userActionLogger->log('EMAIL_VERIFIED', $user, null, [], $request);

        $this->addFlash('success', $this->trans('flash.verify_ok'));

        return $this->redirectToRoute('app_login');
    }

    /**
     * Parcours invité : après définition du mot de passe, choix du profil (comme la page profil).
     */
    #[Route('/compte/finaliser-profil', name: 'app_account_profile_onboarding', methods: ['GET', 'POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function profileOnboarding(
        Request $request,
        EntityManagerInterface $entityManager,
        UserActionLogger $userActionLogger,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw new \LogicException('Utilisateur attendu.');
        }

        if (!$user->isPendingProfileOnboarding()) {
            return $this->redirectToRoute('app_home');
        }

        if ($request->isMethod('GET') && !AcceptJson::wants($request)) {
            return $this->redirect('/app/compte/finaliser-profil');
        }

        $form = $this->createForm(UserProfileFormType::class, $user);
        $profileBefore = $this->profileSnapshot($user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user->setPendingProfileOnboarding(false);
            $changes = $this->buildProfileChangeSet($profileBefore, $user);
            $entityManager->flush();
            if ($changes !== []) {
                $userActionLogger->log('PROFILE_ONBOARDING_COMPLETED', $user, null, ['changes' => $changes], $request);
            }
            $this->addFlash('success', $this->trans('flash.profile_onboarding_done'));

            return $this->redirect('/app');
        }

        if ($form->isSubmitted() && !$form->isValid()) {
            $this->addFlash('danger', $this->trans('flash.form_invalid'));
        }

        return $this->redirect('/app/compte/finaliser-profil');
    }

    #[Route('/compte/activite', name: 'app_account_activity')]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function activity(Request $request, UserActionLogRepository $userActionLogRepository): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw new \LogicException('Utilisateur attendu.');
        }

        if (!$user->canViewActivityLog()) {
            $this->addFlash('warning', $this->trans('flash.activity_log_forbidden'));

            return $this->redirect('/app');
        }

        $logs = $userActionLogRepository->findRecentForUser($user, 250);

        if (!AcceptJson::wants($request)) {
            return $this->redirect('/app/account/activity');
        }

        return $this->json([
            'migrated' => true,
            'logs' => array_map(static function ($log): array {
                return [
                    'id' => $log->getId(),
                    'action' => $log->getAction(),
                    'createdAt' => $log->getCreatedAt()->format(\DateTimeInterface::ATOM),
                    'details' => $log->getDetails(),
                ];
            }, $logs),
        ]);
    }

    #[Route('/compte/profil', name: 'app_account_profile', methods: ['GET', 'POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function profile(
        Request $request,
        EntityManagerInterface $entityManager,
        UserActionLogger $userActionLogger,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw new \LogicException('Utilisateur attendu.');
        }

        $form = $this->createForm(UserProfileFormType::class, $user);
        $profileBefore = $this->profileSnapshot($user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $changes = $this->buildProfileChangeSet($profileBefore, $user);
            $entityManager->flush();
            if ($changes !== []) {
                $userActionLogger->log('PROFILE_UPDATED', $user, null, ['changes' => $changes], $request);
            }
            $this->addFlash('success', $this->trans('flash.profile_saved'));

            return $this->redirect('/app/account/profile');
        }

        if ($request->isMethod('GET') && AcceptJson::wants($request)) {
            return $this->spaJsonStub($request, 'app_account_profile');
        }

        if ($request->isMethod('GET')) {
            return $this->redirect('/app/account/profile');
        }

        if ($form->isSubmitted() && !$form->isValid()) {
            $this->addFlash('danger', $this->trans('flash.form_invalid'));
        }

        return $this->redirect('/app/account/profile');
    }

    /**
     * @return array<string, string|null>
     */
    private function profileSnapshot(User $user): array
    {
        return [
            '_email' => $user->getEmail(),
            'displayName' => $user->getDisplayName(),
            'avatarInitialsCustom' => $user->getAvatarInitialsCustom(),
            'avatarColor' => $user->getAvatarColor(),
            'avatarForegroundColor' => $user->getAvatarForegroundColor(),
        ];
    }

    /**
     * @param array<string, string|null> $before
     *
     * @return array<string, array{from: ?string, to: ?string}>
     */
    private function buildProfileChangeSet(array $before, User $user): array
    {
        $after = $this->profileSnapshot($user);
        $email = $after['_email'] ?? $user->getEmail();
        $out = [];
        foreach ($before as $key => $old) {
            if (str_starts_with($key, '_')) {
                continue;
            }
            $new = $after[$key] ?? null;
            if ($this->normalizeProfileValue($old) === $this->normalizeProfileValue($new)) {
                continue;
            }
            if ($key === 'avatarInitialsCustom') {
                $fromRaw = $this->normalizeProfileValue($old);
                $toRaw = $this->normalizeProfileValue($new);
                $fromEff = $fromRaw !== '' ? $old : User::initialsFromDisplayOrEmail($before['displayName'] ?? null, $email);
                $toEff = $toRaw !== '' ? $new : User::initialsFromDisplayOrEmail($after['displayName'] ?? null, $email);

                $out[$key] = [
                    'from' => $fromEff,
                    'to' => $toEff,
                ];

                continue;
            }
            $out[$key] = [
                'from' => $old,
                'to' => $new,
            ];
        }

        return $out;
    }

    private function normalizeProfileValue(?string $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return $value;
    }

    /**
     * Réinitialise le parcours d’initialisation (admin uniquement) pour rejouer les étapes de test.
     * Remet à zéro plan, nom d’affichage et supprime les projets rattachés à l’organisation principale.
     */
    #[Route('/compte/reinitialiser-initialisation', name: 'app_account_reset_environment_setup', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function resetEnvironmentSetup(
        Request $request,
        EntityManagerInterface $entityManager,
        UserActionLogger $userActionLogger,
    ): Response {
        if (!$this->isCsrfTokenValid('environment_setup_reset', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException($this->trans('error.invalid_csrf'));
        }

        $user = $this->getUser();
        if (!$user instanceof User) {
            throw new \LogicException('Utilisateur attendu.');
        }

        $user->setEnvironmentInitializedAt(null);
        $user->setDisplayName(null);
        $user->setAvatarInitialsCustom(null);
        $user->setAvatarColor(null);
        $user->setAvatarForegroundColor(null);

        $organization = $user->getPrimaryOrganization();
        if ($organization instanceof Organization) {
            $organization->setPlan(null);
            foreach ($organization->getProjects()->toArray() as $project) {
                $entityManager->remove($project);
            }
        }

        $entityManager->flush();

        $userActionLogger->log('ENVIRONMENT_SETUP_RESET', $user, null, [
            'organizationId' => $organization?->getId(),
        ], $request);

        $this->addFlash('info', $this->trans('flash.setup_reset'));

        return $this->redirectToRoute('app_environment_setup_organization');
    }
}
