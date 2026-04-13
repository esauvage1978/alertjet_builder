<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\User;
use App\Enum\EnvironmentWizardStep;
use App\Service\EnvironmentSetupWizard;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class RequireAccountCompletionSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly Security $security,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly EnvironmentSetupWizard $environmentSetupWizard,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        // Après le pare-feu (priorité 8) : sinon l’utilisateur n’est pas encore chargé depuis la session.
        return [KernelEvents::REQUEST => ['onKernelRequest', 7]];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $route = (string) $request->attributes->get('_route');
        if ($route === '' || str_starts_with($route, '_')) {
            return;
        }

        if ($route === 'app_logout') {
            return;
        }

        // Ne pas intercepter les appels API (bootstrap, setup, etc.) : le navigateur suit les 302
        // et reçoit du HTML, ce qui casse fetchJson et bloque la SPA sur « Chargement… ».
        if (str_starts_with($request->getPathInfo(), '/api/')) {
            return;
        }

        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return;
        }

        if (!$user->hasAnyOrganization()) {
            if (
                $route === 'app_environment_setup_organization'
                || $this->spaInitialisationPathToStep($request->getPathInfo()) === EnvironmentWizardStep::Organization
            ) {
                return;
            }

            $event->setResponse(
                new RedirectResponse($this->urlGenerator->generate('app_environment_setup_organization')),
            );

            return;
        }

        if (!$user->needsEnvironmentSetup()) {
            return;
        }

        $step = $this->environmentSetupWizard->resolveStep($user);
        if ($step === EnvironmentWizardStep::Complete) {
            if ($route === 'app_environment_setup_finalize') {
                return;
            }

            $event->setResponse(
                new RedirectResponse($this->urlGenerator->generate('app_environment_setup_finalize')),
            );

            return;
        }

        $path = $request->getPathInfo();
        $spaStep = $this->spaInitialisationPathToStep($path);
        if ($spaStep !== null) {
            $pathNum = $this->environmentSetupWizard->stepNumber($spaStep);
            $currentNum = $this->environmentSetupWizard->stepNumber($step);
            if ($pathNum <= $currentNum) {
                return;
            }
        }

        $requiredRoute = $this->environmentSetupWizard->routeNameForStep($step);
        if ($route !== $requiredRoute) {
            $event->setResponse(
                new RedirectResponse($this->urlGenerator->generate($requiredRoute)),
            );
        }
    }

    private function spaInitialisationPathToStep(string $path): ?EnvironmentWizardStep
    {
        return match ($path) {
            '/app/initialisation/organisation' => EnvironmentWizardStep::Organization,
            '/app/initialisation/plan' => EnvironmentWizardStep::Plan,
            '/app/initialisation/profil' => EnvironmentWizardStep::Profile,
            '/app/initialisation/projet' => EnvironmentWizardStep::Project,
            default => null,
        };
    }
}
