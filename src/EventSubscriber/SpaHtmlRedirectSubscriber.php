<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\User;
use App\Http\AcceptJson;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Redirige les navigations HTML (non-XHR JSON) vers l’application React sous /app.
 * Les appels avec Accept: application/json atteignent encore les contrôleurs (API « même URL »).
 */
final class SpaHtmlRedirectSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly Security $security,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::REQUEST => ['onKernelRequest', 6]];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        if (!$request->isMethod('GET')) {
            return;
        }

        if (AcceptJson::wants($request)) {
            return;
        }

        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return;
        }

        $path = $request->getPathInfo();
        if (str_starts_with($path, '/app') || str_starts_with($path, '/api') || str_starts_with($path, '/build') || str_starts_with($path, '/_')) {
            return;
        }

        if ($this->isPublicOrSetupPath($path)) {
            return;
        }

        $route = (string) $request->attributes->get('_route', '');
        $target = $this->spaTargetPath($route, $request);
        if ($target === null) {
            return;
        }

        $qs = $request->getQueryString();
        if ($qs !== null && $qs !== '') {
            $target .= '?'.$qs;
        }

        $event->setResponse(new RedirectResponse($target));
    }

    private function isPublicOrSetupPath(string $path): bool
    {
        $prefixes = [
            '/connexion',
            '/deconnexion',
            '/inscription',
            '/mot-de-passe-oublie',
            '/reinitialiser-mot-de-passe',
            '/invitation',
            '/verifier-email',
            '/locale/',
            '/initialisation',
            '/premiere-organisation',
            '/compte/finaliser-profil',
            '/favicon',
            '/robots.txt',
        ];

        foreach ($prefixes as $p) {
            if (str_starts_with($path, $p)) {
                return true;
            }
        }

        return false;
    }

    private function spaTargetPath(string $route, Request $r): ?string
    {
        return match ($route) {
            'app_home' => '/app',
            'app_organization_mine', 'app_organization_edit' => '/app/organization/billing',
            'app_organization_users' => '/app/organization/users',
            'app_organization_show' => '/app/organization/billing',
            'app_organization_tickets' => '/app/organization/tickets',
            'app_organization_projects' => '/app/organization/'.$r->attributes->get('orgToken').'/projects',
            'app_organization_project_edit' => '/app/organization/'.$r->attributes->get('orgToken').'/projects/'.$r->attributes->get('projectId').'/edit',
            'app_account_profile' => '/app/account/profile',
            'app_account_activity' => '/app/account/activity',
            'app_environment_setup_organization' => '/app/initialisation/organisation',
            'app_environment_setup_plan' => '/app/initialisation/plan',
            'app_environment_setup_profile' => '/app/initialisation/profil',
            'app_environment_setup_project' => '/app/initialisation/projet',
            'app_account_profile_onboarding' => '/app/compte/finaliser-profil',
            'admin_organization_index' => '/app/admin/organisations',
            'admin_user_index' => '/app/admin/utilisateurs',
            'admin_audit_actions' => '/app/admin/audit/actions',
            'admin_audit_errors' => '/app/admin/audit/erreurs',
            'admin_audit_error_show' => '/app/admin/audit/erreurs/'.$r->attributes->get('id'),
            default => null,
        };
    }
}
