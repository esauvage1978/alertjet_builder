<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Enum\UserRole;
use App\Form\ForgotPasswordFormType;
use App\Form\RegistrationFormType;
use App\Form\ResetPasswordFormType;
use App\Repository\UserRepository;
use App\Service\ApplicationErrorLogger;
use App\Service\MailWebhookService;
use App\Service\UserActionLogger;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use App\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
final class SecurityController extends AbstractController
{
    #[Route('/login', name: 'app_login_short_redirect', methods: ['GET'])]
    public function loginShortRedirect(): Response
    {
        // URL courte publique (liens externes) → URL SPA réelle.
        return $this->redirectToRoute('app_login', [], Response::HTTP_MOVED_PERMANENTLY);
    }

    #[Route('/connexion', name: 'app_login_check', methods: ['POST'])]
    public function loginCheck(): void
    {
        throw new \LogicException('Géré par le pare-feu form_login (check_path).');
    }

    #[Route('/connexion', name: 'app_connexion_redirect', methods: ['GET'])]
    public function connexionRedirect(): Response
    {
        return $this->redirectToRoute('app_login');
    }

    #[Route('/deconnexion', name: 'app_logout')]
    public function logout(): void
    {
        throw new \LogicException('Géré par la configuration de sécurité (clôture main).');
    }

    #[Route('/inscription', name: 'app_register')]
    public function register(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager,
        MailWebhookService $mailWebhookService,
        UserActionLogger $userActionLogger,
        LoggerInterface $logger,
        ApplicationErrorLogger $applicationErrorLogger,
    ): Response {
        if ($request->isMethod('GET')) {
            return $this->redirect('/app/inscription');
        }

        $user = new User();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $plainPassword = (string) $form->get('plainPassword')->getData();
            $user->setPassword($passwordHasher->hashPassword($user, $plainPassword));
            $user->setRoles([UserRole::Utilisateur->value]);

            $verificationToken = bin2hex(random_bytes(32));
            $user->setEmailVerificationToken($verificationToken);
            $user->setEmailVerificationExpiresAt((new \DateTimeImmutable())->modify('+48 hours'));

            $entityManager->persist($user);
            $entityManager->flush();

            $userActionLogger->log(
                'USER_REGISTERED',
                $user,
                null,
                [
                    'roles' => $user->getRoles(),
                ],
                $request,
            );

            $verifyUrl = $this->generateUrl('app_verify_email', ['token' => $verificationToken], UrlGeneratorInterface::ABSOLUTE_URL);

            try {
                $mailWebhookService->send(
                    'user_registration',
                    $user->getEmail(),
                    $this->trans('mail.subject.verify'),
                    [
                        'verifyUrl' => $verifyUrl,
                        'email' => $user->getEmail(),
                    ],
                );
                $userActionLogger->log('EMAIL_VERIFICATION_SENT', $user, null, ['template' => 'user_registration'], $request);
            } catch (\Throwable $e) {
                $logger->error('Webhook mail inscription', ['exception' => $e]);
                $applicationErrorLogger->logThrowable($e, $request, $user, [
                    'layer' => 'registration_verify_mail',
                    'template' => 'user_registration',
                ], 'caught');
                $this->addFlash('warning', $this->trans('flash.register_mail_fail'));
                $userActionLogger->log(
                    'EMAIL_VERIFICATION_SEND_FAILED',
                    $user,
                    null,
                    ['errorClass' => $e::class],
                    $request,
                );
            }

            $this->addFlash('success', $this->trans('flash.register_ok'));

            return $this->redirectToRoute('app_login');
        }

        if ($form->isSubmitted() && !$form->isValid()) {
            $this->addFlash('danger', $this->trans('flash.form_invalid'));
        }

        return $this->redirect('/app/inscription');
    }

    #[Route('/mot-de-passe-oublie', name: 'app_forgot_password')]
    public function forgotPassword(
        Request $request,
        UserRepository $userRepository,
        EntityManagerInterface $entityManager,
        MailWebhookService $mailWebhookService,
        UserActionLogger $userActionLogger,
        LoggerInterface $logger,
        ApplicationErrorLogger $applicationErrorLogger,
    ): Response {
        if ($request->isMethod('GET')) {
            return $this->redirect('/app/mot-de-passe-oublie');
        }

        $form = $this->createForm(ForgotPasswordFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $email = (string) $form->get('email')->getData();
            $user = $userRepository->findOneBy(['email' => $email]);

            if ($user instanceof User) {
                $resetToken = bin2hex(random_bytes(32));
                $user->setPasswordResetToken($resetToken);
                $user->setPasswordResetExpiresAt((new \DateTimeImmutable())->modify('+1 hour'));
                $entityManager->flush();

                $resetUrl = $this->generateUrl('app_reset_password', ['token' => $resetToken], UrlGeneratorInterface::ABSOLUTE_URL);

                try {
                    $mailWebhookService->send(
                        'user_password_reset',
                        $user->getEmail(),
                        $this->trans('mail.subject.password_reset'),
                        [
                            'resetUrl' => $resetUrl,
                            'email' => $user->getEmail(),
                        ],
                    );
                } catch (\Throwable $e) {
                    $logger->error('Webhook mail mot de passe oublié', ['exception' => $e]);
                    $applicationErrorLogger->logThrowable($e, $request, $user, [
                        'layer' => 'forgot_password_mail',
                        'template' => 'user_password_reset',
                    ], 'caught');
                }

                $userActionLogger->log('PASSWORD_RESET_DISPATCHED', $user, null, ['template' => 'user_password_reset'], $request);
            } else {
                $userActionLogger->log(
                    'PASSWORD_RESET_UNKNOWN_EMAIL',
                    null,
                    $email,
                    [],
                    $request,
                );
            }

            $this->addFlash('success', $this->trans('flash.reset_mail_ok'));

            return $this->redirectToRoute('app_login');
        }

        if ($form->isSubmitted() && !$form->isValid()) {
            $this->addFlash('danger', $this->trans('flash.form_invalid'));
        }

        return $this->redirect('/app/mot-de-passe-oublie');
    }

    #[Route('/reinitialiser-mot-de-passe/{token}', name: 'app_reset_password')]
    public function resetPassword(
        string $token,
        Request $request,
        UserRepository $userRepository,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager,
        UserActionLogger $userActionLogger,
    ): Response {
        $user = $userRepository->findOneByPasswordResetToken($token);
        if (
            !$user instanceof User
            || $user->getPasswordResetExpiresAt() === null
            || $user->getPasswordResetExpiresAt() < new \DateTimeImmutable()
        ) {
            $this->addFlash('danger', $this->trans('flash.reset_link_invalid'));

            return $this->redirectToRoute('app_forgot_password');
        }

        if ($request->isMethod('GET')) {
            return $this->redirect('/app/reinitialiser-mot-de-passe/'.$token);
        }

        $form = $this->createForm(ResetPasswordFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $plainPassword = (string) $form->get('plainPassword')->getData();
            $user->setPassword($passwordHasher->hashPassword($user, $plainPassword));
            $user->clearPasswordReset();
            if (!$user->isEmailVerified()) {
                $user->setEmailVerifiedAt(new \DateTimeImmutable());
            }
            $entityManager->flush();

            $userActionLogger->log(
                'PASSWORD_CHANGED',
                $user,
                null,
                ['via' => 'reset_token'],
                $request,
            );

            if ($user->isPendingProfileOnboarding()) {
                $this->addFlash('success', $this->trans('flash.invite_password_ok'));

                return $this->redirectToRoute('app_login');
            }

            $this->addFlash('success', $this->trans('flash.password_updated'));

            return $this->redirectToRoute('app_login');
        }

        if ($form->isSubmitted() && !$form->isValid()) {
            $this->addFlash('danger', $this->trans('flash.form_invalid'));
        }

        return $this->redirect('/app/reinitialiser-mot-de-passe/'.$token);
    }
}
