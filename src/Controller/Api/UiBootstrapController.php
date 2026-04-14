<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Controller\AbstractController;
use App\Entity\Organization;
use App\Entity\User;
use App\Repository\OrganizationClientAccessRepository;
use App\Repository\ProjectRepository;
use App\Security\Voter\OrganizationVoter;
use App\Service\CurrentOrganizationService;
use App\Service\InternalTicketAccessPolicy;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

final class UiBootstrapController extends AbstractController
{
    /** Valeur valide pour générer le path ; remplacée par « __locale__ » côté client. */
    private const LOCALE_URL_DUMMY = 'fr';

    /** 32 caractères hex (exigence route) ; remplacée par « __token__ » côté client. */
    private const ORG_CONTEXT_TOKEN_DUMMY = '000000000000';

    #[Route('/api/ui/bootstrap', name: 'api_ui_bootstrap', methods: ['GET'])]
    public function __invoke(
        RequestStack $requestStack,
        CurrentOrganizationService $currentOrganizationService,
        UrlGeneratorInterface $urlGenerator,
        CsrfTokenManagerInterface $csrfTokenManager,
        ProjectRepository $projectRepository,
        OrganizationClientAccessRepository $organizationClientAccessRepository,
        InternalTicketAccessPolicy $internalTicketAccessPolicy,
    ): JsonResponse {
        $user = $this->getUser();
        $request = $requestStack->getCurrentRequest();

        if (!$user instanceof User) {
            return $this->json($this->guestBootstrap($request, $urlGenerator));
        }

        $locale = $request?->getLocale() ?? 'fr';

        $org = $currentOrganizationService->getCurrentOrganization();

        $setupIncomplete = !$user->hasAnyOrganization() || $user->needsEnvironmentSetup();
        $profileOnboarding = $user->isPendingProfileOnboarding();
        $hideAppSidebar = $setupIncomplete || $profileOnboarding;
        $showMainNavDestinations = !$setupIncomplete && !$profileOnboarding;

        $canEditOrg = $org !== null && $this->isGranted(OrganizationVoter::EDIT, $org);

        $hasInternalForm = $org !== null && $projectRepository->organizationHasInternalFormIntegrationEnabled($org);
        $showTicketCreateEntry = $org !== null && $internalTicketAccessPolicy->canCreateInternalTicket(
            $user,
            $org,
            $hasInternalForm,
            $organizationClientAccessRepository,
        );

        return $this->json([
            'guest' => false,
            'flashes' => $this->pullFlashes($request),
            'locale' => $locale,
            'i18n' => $this->minimalUiLabels(),
            'user' => $this->serializeUser($user),
            'currentOrganization' => $org ? $this->serializeOrganization($org) : null,
            'organizations' => array_map(
                fn (Organization $o) => $this->serializeOrganization($o),
                $currentOrganizationService->getOrganizationsSorted(),
            ),
            'flags' => [
                'setupIncomplete' => $setupIncomplete,
                'showMainNavDestinations' => $showMainNavDestinations,
                'profileOnboarding' => $profileOnboarding,
                'hideAppSidebar' => $hideAppSidebar,
                'canViewActivityLog' => $user->canViewActivityLog(),
                'canAccessOrganizationBillingPage' => $user->canAccessOrganizationBillingPage(),
                'canEditCurrentOrganization' => $canEditOrg,
                'isAdmin' => $this->isGranted('ROLE_ADMIN'),
                'hasInternalFormProject' => $hasInternalForm,
                'showTicketCreateEntry' => $showTicketCreateEntry,
            ],
            'routes' => [
                'spa' => $urlGenerator->generate('app_spa'),
                'legacyHome' => $urlGenerator->generate('app_home'),
                'logout' => $urlGenerator->generate('app_logout'),
                'profile' => $urlGenerator->generate('app_account_profile'),
                'activity' => $urlGenerator->generate('app_account_activity'),
                'organizationBilling' => $urlGenerator->generate('app_organization_show'),
                'organizationUsers' => $urlGenerator->generate('app_organization_users'),
                'organizationTickets' => $urlGenerator->generate('app_organization_tickets'),
                'organizationClients' => $urlGenerator->generate('app_organization_clients'),
                'organizationTicketNew' => $urlGenerator->generate('app_organization_ticket_new'),
                'organizationProjects' => $org ? $urlGenerator->generate('app_spa_catch', ['reactPath' => 'projects']) : null,
                'localeSwitch' => $this->localeSwitchUrlPattern($urlGenerator),
                'adminOrganizations' => $urlGenerator->generate('admin_organization_index'),
                'adminUsers' => $urlGenerator->generate('admin_user_index'),
                'adminAuditActions' => $urlGenerator->generate('admin_audit_actions'),
                'adminAuditErrors' => $urlGenerator->generate('admin_audit_errors'),
                'orgContextSwitch' => $this->organizationContextSwitchUrlPattern($urlGenerator),
            ],
            'csrf' => [
                'logout' => $csrfTokenManager->getToken('logout')->getValue(),
            ],
            // Chemins relatifs au basename React Router (/app) — pas de préfixe /app (sinon double segment /app/app/…).
            'spaPaths' => [
                'organizationUsers' => '/organization/users',
                'organizationBilling' => '/organization/billing',
                'organizationTickets' => '/tickets',
                'organizationClients' => '/organization/clients',
                'organizationTicketNew' => '/tickets/new',
                'organizationProjects' => $org ? '/projects' : null,
                'accountProfile' => '/account/profile',
                'accountActivity' => '/account/activity',
                'adminOrganizations' => '/admin/organisations',
                'adminUsers' => '/admin/utilisateurs',
                'adminAuditActions' => '/admin/audit/actions',
                'adminAuditErrors' => '/admin/audit/erreurs',
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function guestBootstrap(?Request $request, UrlGeneratorInterface $urlGenerator): array
    {
        $locale = $request?->getLocale() ?? 'fr';

        return [
            'guest' => true,
            'flashes' => $this->pullFlashes($request),
            'locale' => $locale,
            'i18n' => array_merge($this->minimalUiLabels(), $this->guestAuthLabels()),
            'routes' => [
                'localeSwitch' => $this->localeSwitchUrlPattern($urlGenerator),
                'loginPost' => $urlGenerator->generate('app_login_check'),
                'registerPost' => $urlGenerator->generate('app_register'),
                'forgotPost' => $urlGenerator->generate('app_forgot_password'),
            ],
            'spaPaths' => [
                'login' => '/login',
                'inscription' => '/inscription',
                'forgotPassword' => '/mot-de-passe-oublie',
                'adminOrganizations' => '/admin/organisations',
                'adminUsers' => '/admin/utilisateurs',
                'adminAuditActions' => '/admin/audit/actions',
                'adminAuditErrors' => '/admin/audit/erreurs',
            ],
        ];
    }

    /**
     * @return array<string, list<string>>
     */
    private function pullFlashes(?Request $request): array
    {
        if ($request === null || !$request->hasSession()) {
            return [];
        }

        return $request->getSession()->getFlashBag()->all();
    }

    /** @return array<string, string> */
    private function guestAuthLabels(): array
    {
        return [
            'auth_product_aria' => $this->trans('auth.product_aria'),
            'auth_hero_title_part1' => $this->trans('auth.hero.title_part1'),
            'auth_hero_secure' => $this->trans('auth.hero.secure'),
            'auth_hero_lead_html' => $this->trans('auth.hero.lead_html'),
            'auth_hero_bullet1' => $this->trans('auth.hero.bullet1'),
            'auth_hero_bullet2' => $this->trans('auth.hero.bullet2'),
            'auth_hero_bullet3' => $this->trans('auth.hero.bullet3'),
            'auth_trust_strip' => $this->trans('auth.trust_strip'),
            'auth_login_heading' => $this->trans('auth.login.heading'),
            'auth_login_tagline' => $this->trans('auth.login.tagline'),
            'auth_login_submit' => $this->trans('auth.login.submit'),
            'auth_login_register' => $this->trans('auth.login.register'),
            'auth_login_forgot_short' => $this->trans('auth.login.forgot_short'),
            'auth_login_no_account' => $this->trans('auth.login.no_account'),
            'auth_register_heading' => $this->trans('auth.register.heading'),
            'auth_register_tagline' => $this->trans('auth.register.tagline'),
            'auth_register_submit' => $this->trans('auth.register.submit'),
            'auth_register_already' => $this->trans('auth.register.already'),
            'auth_register_login_link' => $this->trans('auth.register.login_link'),
            'auth_forgot_heading' => $this->trans('auth.forgot.heading'),
            'auth_forgot_tagline' => $this->trans('auth.forgot.tagline'),
            'auth_forgot_submit' => $this->trans('auth.forgot.submit'),
            'auth_forgot_back' => $this->trans('auth.forgot.back'),
            'auth_reset_heading' => $this->trans('auth.reset.heading'),
            'auth_reset_tagline' => $this->trans('auth.reset.tagline'),
            'auth_reset_submit' => $this->trans('auth.reset.submit'),
            'auth_reset_login' => $this->trans('auth.reset.login'),
            'auth_invitation_heading' => $this->trans('auth.invitation.heading'),
            'auth_invitation_tagline' => $this->trans('auth.invitation.tagline'),
            'auth_invitation_tagline_org' => $this->trans('auth.invitation.tagline_org'),
            'auth_invitation_submit' => $this->trans('auth.invitation.submit'),
            'auth_invitation_login_instead' => $this->trans('auth.invitation.login_instead'),
            'auth_invitation_invalid' => $this->trans('flash.invitation_link_invalid'),
            'form_email' => $this->trans('form.email'),
            'form_password' => $this->trans('form.password'),
            'form_password_confirm' => $this->trans('form.password_confirm'),
            'form_account_email' => $this->trans('form.account_email'),
            'form_new_password' => $this->trans('form.new_password'),
        ];
    }

    /** @return array<string, string> */
    private function minimalUiLabels(): array
    {
        return [
            'brand_html' => $this->trans('auth.brand_html'),
            'nav_dashboard' => $this->trans('nav.dashboard'),
            'nav_org_users' => $this->trans('nav.org_users'),
            'nav_org_clients' => $this->trans('nav.org_clients'),
            'nav_ticket_new' => $this->trans('nav.ticket_new'),
            'nav_section_admin' => $this->trans('nav.section_administration'),
            'nav_section_tickets' => $this->trans('nav.section_tickets'),
            'nav_tickets' => $this->trans('nav.tickets'),
            'nav_project' => $this->trans('nav.project'),
            'nav_org_billing' => $this->trans('nav.org_billing'),
            'nav_org_switcher' => $this->trans('nav.org_switcher'),
            'nav_current_org' => $this->trans('nav.current_org'),
            'nav_profile' => $this->trans('nav.profile'),
            'nav_activity' => $this->trans('nav.activity_log'),
            'nav_logout' => $this->trans('nav.logout'),
            'nav_toggle' => $this->trans('nav.toggle_menu'),
            'nav_admin_header' => $this->trans('nav.admin_header'),
            'nav_admin_orgs' => $this->trans('nav.organizations_admin'),
            'nav_admin_users' => $this->trans('nav.users_admin'),
            'nav_admin_audit_actions' => $this->trans('nav.admin_audit_actions'),
            'nav_admin_audit_errors' => $this->trans('nav.admin_audit_errors'),
            'breadcrumb_home' => $this->trans('breadcrumb.home'),
            'breadcrumb_tickets' => $this->trans('breadcrumb.tickets'),
            'breadcrumb_org_clients' => $this->trans('breadcrumb.org_clients'),
            'breadcrumb_ticket_new' => $this->trans('breadcrumb.ticket_new'),
            'breadcrumb_org_projects' => $this->trans('org.projects.breadcrumb'),
            'breadcrumb_org_projects_edit' => $this->trans('org.projects.breadcrumb_edit'),
            'wizard_steps_progress_aria' => $this->trans('wizard.steps.progress_aria'),
            'wizard_steps_organization' => $this->trans('wizard.steps.organization'),
            'wizard_steps_plan' => $this->trans('wizard.steps.plan'),
            'wizard_steps_profile' => $this->trans('wizard.steps.profile'),
            'wizard_steps_project' => $this->trans('wizard.steps.project'),
            'footer_tagline' => $this->trans('footer.tagline'),
            'app_title' => $this->trans('app.title_short'),
            'locale_fr' => 'FR',
            'locale_en' => 'EN',
        ];
    }

    /** @return array<string, mixed> */
    private function serializeUser(User $user): array
    {
        return [
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'displayName' => $user->getDisplayName(),
            'initials' => $user->getAvatarInitials(),
            'avatarColor' => $user->getAvatarColorOrDefault(),
            'avatarForegroundColor' => $user->getAvatarForegroundColorOrDefault(),
            'primaryRoleKey' => $user->getPrimaryRoleCatalogKey(),
            'roleBadgeClass' => $user->getRoleBadgeClass(),
        ];
    }

    /** @return array<string, mixed> */
    private function serializeOrganization(Organization $o): array
    {
        return [
            'id' => $o->getId(),
            'name' => $o->getName(),
            'publicToken' => $o->getPublicToken(),
        ];
    }

    private function localeSwitchUrlPattern(UrlGeneratorInterface $urlGenerator): string
    {
        $path = $urlGenerator->generate(
            'app_locale_switch',
            ['locale' => self::LOCALE_URL_DUMMY],
            UrlGeneratorInterface::ABSOLUTE_PATH,
        );

        return preg_replace('#/[a-z]{2}$#', '/__locale__', $path) ?? $path;
    }

    private function organizationContextSwitchUrlPattern(UrlGeneratorInterface $urlGenerator): string
    {
        $path = $urlGenerator->generate(
            'app_organization_context_switch',
            ['orgToken' => self::ORG_CONTEXT_TOKEN_DUMMY],
            UrlGeneratorInterface::ABSOLUTE_PATH,
        );

        return str_replace(self::ORG_CONTEXT_TOKEN_DUMMY, '__token__', $path);
    }
}
