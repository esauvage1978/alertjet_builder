<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Form\ResetPasswordFormType;
use App\Repository\UserRepository;
use App\Service\UserActionLogger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

final class AcceptOrganizationInvitationController extends AbstractController
{
    /**
     * Lien unique 24 h : définir le mot de passe, valider l’e-mail / l’accès, connexion automatique
     * puis parcours profil obligatoire.
     */
    #[Route('/invitation/{token}', name: 'app_organization_invitation_accept', methods: ['GET', 'POST'])]
    public function accept(
        string $token,
        Request $request,
        UserRepository $userRepository,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager,
        UserActionLogger $userActionLogger,
        Security $security,
    ): Response {
        $user = $userRepository->findOneByOrganizationInviteToken($token);
        if (
            !$user instanceof User
            || $user->getOrganizationInviteExpiresAt() === null
            || $user->getOrganizationInviteExpiresAt() < new \DateTimeImmutable()
        ) {
            $this->addFlash('danger', $this->trans('flash.invitation_link_invalid'));

            return $this->redirectToRoute('app_login');
        }

        if ($request->isMethod('GET')) {
            return $this->redirect('/app/invitation/'.$token);
        }

        $form = $this->createForm(ResetPasswordFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $plainPassword = (string) $form->get('plainPassword')->getData();
            $user->setPassword($passwordHasher->hashPassword($user, $plainPassword));
            $user->clearOrganizationInvite();
            if (!$user->isEmailVerified()) {
                $user->setEmailVerifiedAt(new \DateTimeImmutable());
            }
            $entityManager->flush();

            $userActionLogger->log(
                'ORG_INVITATION_ACCEPTED',
                $user,
                null,
                [],
                $request,
            );

            $security->login($user);
            $this->addFlash('success', $this->trans('flash.invitation_accepted'));

            return $this->redirectToRoute('app_account_profile_onboarding');
        }

        if ($form->isSubmitted() && !$form->isValid()) {
            $this->addFlash('danger', $this->trans('flash.form_invalid'));
        }

        return $this->redirect('/app/invitation/'.$token);
    }
}
