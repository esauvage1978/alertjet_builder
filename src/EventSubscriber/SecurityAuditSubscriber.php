<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\UserActionLogger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use Symfony\Component\Security\Http\Event\LogoutEvent;

final class SecurityAuditSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly UserActionLogger $userActionLogger,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            LoginSuccessEvent::class => ['onLoginSuccess', 0],
            LoginFailureEvent::class => ['onLoginFailure', 0],
            LogoutEvent::class => ['onLogout', 0],
        ];
    }

    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        $user = $event->getUser();
        if (!$user instanceof User) {
            return;
        }

        $managed = $this->userRepository->find($user->getId());
        if ($managed !== null) {
            $managed->setLastLoginAt(new \DateTimeImmutable());
            $this->entityManager->flush();
            $user = $managed;
        }

        $this->userActionLogger->log(
            'AUTH_LOGIN_SUCCESS',
            $user,
            null,
            [
                'emailVerified' => $user->isEmailVerified(),
                'roles' => $user->getRoles(),
            ],
            $event->getRequest(),
        );
    }

    public function onLoginFailure(LoginFailureEvent $event): void
    {
        $request = $event->getRequest();
        $email = $request->request->getString('_username');
        if ($email === '') {
            $passport = $event->getPassport();
            if ($passport !== null) {
                try {
                    $email = $passport->getUser()->getUserIdentifier();
                } catch (\Throwable) {
                    $email = '';
                }
            }
        }

        $this->userActionLogger->log(
            'AUTH_LOGIN_FAILED',
            null,
            $email !== '' ? $email : null,
            [
                'messageKey' => $event->getException()->getMessageKey(),
                'firewall' => $event->getFirewallName(),
            ],
            $request,
        );
    }

    public function onLogout(LogoutEvent $event): void
    {
        $token = $event->getToken();
        $user = $token?->getUser();
        if (!$user instanceof User) {
            return;
        }

        $this->userActionLogger->log(
            'AUTH_LOGOUT',
            $user,
            null,
            ['roles' => $user->getRoles()],
            $event->getRequest(),
        );
    }
}
