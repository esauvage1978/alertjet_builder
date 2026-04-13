<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Utilisateur invité : accès réservé à la finalisation du profil (hors déconnexion).
 */
final class ProfileOnboardingRequestSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly Security $security,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::REQUEST => ['onKernelRequest', 4]];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $route = $event->getRequest()->attributes->get('_route');
        if (!\is_string($route) || str_starts_with($route, '_')) {
            return;
        }

        if ($route === 'app_account_profile_onboarding' || $route === 'app_logout') {
            return;
        }

        if (str_starts_with($event->getRequest()->getPathInfo(), '/api/')) {
            return;
        }

        $user = $this->security->getUser();
        if (!$user instanceof User || !$user->isPendingProfileOnboarding()) {
            return;
        }

        $event->setResponse(
            new RedirectResponse($this->urlGenerator->generate('app_account_profile_onboarding')),
        );
    }
}
