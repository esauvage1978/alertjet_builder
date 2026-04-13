<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\User;
use App\Service\ApplicationErrorLogger;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Enregistre toute exception HTTP non interceptée (réponse d’erreur Symfony).
 */
final class ExceptionLogSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly ApplicationErrorLogger $applicationErrorLogger,
        private readonly Security $security,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::EXCEPTION => ['onKernelException', -64]];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $request = $event->getRequest();
        if ($request->attributes->get('_app_error_logged_kernel')) {
            return;
        }
        $request->attributes->set('_app_error_logged_kernel', true);

        $tokenUser = $this->security->getUser();
        $userEntity = $tokenUser instanceof User ? $tokenUser : null;

        $this->applicationErrorLogger->logThrowable(
            $event->getThrowable(),
            $request,
            $userEntity,
            [],
            'kernel',
        );
    }
}
