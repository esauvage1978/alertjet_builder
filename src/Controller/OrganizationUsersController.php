<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Organization;
use App\Entity\User;
use App\Enum\UserRole;
use App\Form\AddOrganizationMemberType;
use App\Http\AcceptJson;
use App\Repository\UserRepository;
use App\Security\Voter\OrganizationVoter;
use App\Service\ApplicationErrorLogger;
use App\Service\CurrentOrganizationService;
use App\Service\MailWebhookService;
use App\Service\UserActionLogger;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use App\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[IsGranted('IS_AUTHENTICATED_FULLY')]
final class OrganizationUsersController extends AbstractController
{
    private const PER_PAGE = 15;

    #[Route('/mon-organisation/utilisateurs', name: 'app_organization_users', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        CurrentOrganizationService $currentOrganizationService,
        UserRepository $userRepository,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
        MailWebhookService $mailWebhookService,
        HttpClientInterface $httpClient,
        LoggerInterface $logger,
        UserActionLogger $userActionLogger,
        ApplicationErrorLogger $applicationErrorLogger,
        CsrfTokenManagerInterface $csrfTokenManager,
        #[Autowire('%env(string:INVITE_WEBHOOK_URL)%')] string $inviteWebhookUrl,
    ): Response {
        $actor = $this->getActor();
        $organization = $currentOrganizationService->getCurrentOrganization();
        if ($organization === null) {
            return $this->redirectToOrganizationOrHome($actor);
        }

        $this->denyAccessUnlessGranted(OrganizationVoter::EDIT, $organization);

        $addForm = $this->createForm(AddOrganizationMemberType::class);
        $addForm->handleRequest($request);

        if ($addForm->isSubmitted()) {
            if ($addForm->isValid()) {
                $email = mb_strtolower(trim((string) $addForm->get('email')->getData()));
                $target = $userRepository->findOneByEmailLowercase($email);
                if ($target !== null) {
                    if ($target->belongsToOrganization($organization)) {
                        $this->addFlash('info', $this->trans('org.users.flash_already_member'));
                    } else {
                        $organization->addUser($target);
                        $entityManager->flush();
                        $loginUrl = $this->generateUrl('app_login', [], UrlGeneratorInterface::ABSOLUTE_URL);
                        $this->notifyMemberInvited(
                            $email,
                            $organization,
                            $request,
                            $mailWebhookService,
                            $httpClient,
                            $inviteWebhookUrl,
                            $logger,
                            $applicationErrorLogger,
                            $this->getActor(),
                            null,
                            $loginUrl,
                        );
                        $this->addFlash('success', $this->trans('org.users.flash_added_existing'));
                        $userActionLogger->log(
                            'ORG_MEMBER_ADDED_EXISTING',
                            $this->getActor(),
                            null,
                            [
                                'invitedEmail' => $email,
                                'targetUserId' => $target->getId(),
                                'organizationId' => $organization->getId(),
                            ],
                            $request,
                        );
                    }
                } else {
                    $invited = new User();
                    $invited->setEmail($email);
                    $invited->setRoles([UserRole::Utilisateur->value]);
                    $invited->setPassword($passwordHasher->hashPassword($invited, bin2hex(random_bytes(32))));
                    $invited->setPendingProfileOnboarding(true);
                    $invited->clearEmailVerification();
                    $organization->addUser($invited);
                    $entityManager->persist($invited);
                    $this->issueOrganizationInviteToken($invited);
                    $entityManager->flush();
                    $acceptUrl = $this->generateUrl(
                        'app_organization_invitation_accept',
                        ['token' => (string) $invited->getOrganizationInviteToken()],
                        UrlGeneratorInterface::ABSOLUTE_URL,
                    );
                    $this->notifyMemberInvited(
                        $email,
                        $organization,
                        $request,
                        $mailWebhookService,
                        $httpClient,
                        $inviteWebhookUrl,
                        $logger,
                        $applicationErrorLogger,
                        $this->getActor(),
                        $acceptUrl,
                        null,
                    );
                    $this->addFlash('success', $this->trans('org.users.flash_invited_new'));
                    $userActionLogger->log(
                        'ORG_MEMBER_INVITED_NEW',
                        $this->getActor(),
                        null,
                        [
                            'invitedEmail' => $email,
                            'invitedUserId' => $invited->getId(),
                            'organizationId' => $organization->getId(),
                        ],
                        $request,
                    );
                }
            } else {
                $this->addFlash('danger', $this->trans('org.users.flash_invalid_email'));
            }

            return $this->redirect('/app/organization/users');
        }

        $search = (string) $request->query->get('q', '');
        $roleFilter = (string) $request->query->get('role', '');

        // Un gestionnaire ne peut pas agir sur les administrateurs : le filtre "admin" est ignoré.
        if ($roleFilter === 'admin' && !$this->isGranted('ROLE_ADMIN')) {
            $roleFilter = '';
        }

        $page = max(1, (int) $request->query->get('page', 1));

        $total = $userRepository->countMembersFiltered($organization, $search ?: null, $roleFilter ?: null);
        $totalPages = max(1, (int) ceil($total / self::PER_PAGE));
        $page = min($page, $totalPages);

        $members = $userRepository->findMembersFiltered(
            $organization,
            $search ?: null,
            $roleFilter ?: null,
            $page,
            self::PER_PAGE,
        );

        if (!AcceptJson::wants($request)) {
            return $this->redirect('/app/organization/users'.($request->getQueryString() ? '?'.$request->getQueryString() : ''));
        }

        return $this->json([
            'migrated' => true,
            'organization' => [
                'id' => $organization->getId(),
                'name' => $organization->getName(),
            ],
            'members' => array_map(fn (User $m) => $this->serializeOrgMemberRow($m, $csrfTokenManager), $members),
            'pagination' => [
                'page' => $page,
                'totalPages' => $totalPages,
                'total' => $total,
                'perPage' => self::PER_PAGE,
            ],
            'filters' => [
                'q' => $search,
                'role' => $roleFilter,
            ],
            'inviteForm' => [
                'action' => $this->generateUrl('app_organization_users'),
                'csrfName' => 'add_organization_member[_token]',
                'csrfValue' => $csrfTokenManager->getToken('add_organization_member')->getValue(),
                'emailName' => 'add_organization_member[email]',
            ],
            'meta' => [
                'actorIsAdmin' => $this->isGranted('ROLE_ADMIN'),
            ],
        ]);
    }

    /** @return array<string, mixed> */
    private function serializeOrgMemberRow(User $m, CsrfTokenManagerInterface $csrf): array
    {
        return [
            'id' => $m->getId(),
            'email' => $m->getEmail(),
            'displayName' => $m->getDisplayName(),
            'primaryRoleCatalogKey' => $m->getPrimaryRoleCatalogKey(),
            'roleBadgeClass' => $m->getRoleBadgeClass(),
            'avatarColorOrDefault' => $m->getAvatarColorOrDefault(),
            'avatarForegroundColorOrDefault' => $m->getAvatarForegroundColorOrDefault(),
            'avatarInitials' => $m->getAvatarInitials(),
            'emailVerifiedAt' => $m->getEmailVerifiedAt()?->format(\DateTimeInterface::ATOM),
            'pendingProfileOnboarding' => $m->isPendingProfileOnboarding(),
            'hasOrganizationInvite' => $m->getOrganizationInviteToken() !== null,
            'csrfRemove' => $csrf->getToken('remove_org_member_'.$m->getId())->getValue(),
            'csrfResend' => $csrf->getToken('resend_org_invite_'.$m->getId())->getValue(),
        ];
    }

    #[Route('/mon-organisation/utilisateurs/{userId}/retirer', name: 'app_organization_users_remove', requirements: ['userId' => '\d+'], methods: ['POST'])]
    public function remove(
        #[MapEntity(id: 'userId')] User $member,
        Request $request,
        CurrentOrganizationService $currentOrganizationService,
        EntityManagerInterface $entityManager,
        CsrfTokenManagerInterface $csrfTokenManager,
    ): Response {
        $actor = $this->getActor();
        $organization = $currentOrganizationService->getCurrentOrganization();
        if ($organization === null) {
            return $this->redirectToOrganizationOrHome($actor);
        }

        $this->denyAccessUnlessGranted(OrganizationVoter::EDIT, $organization);

        $token = new CsrfToken(
            'remove_org_member_'.$member->getId(),
            (string) $request->request->get('_token'),
        );
        if (!$csrfTokenManager->isTokenValid($token)) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        if (!$member->belongsToOrganization($organization)) {
            $this->addFlash('danger', $this->trans('org.users.flash_not_member'));

            return $this->redirect('/app/organization/users');
        }

        if ($organization->getUsers()->count() <= 1) {
            $this->addFlash('danger', $this->trans('org.users.flash_last_member'));

            return $this->redirect('/app/organization/users');
        }

        $organization->removeUser($member);
        $entityManager->flush();
        $this->addFlash('success', $this->trans('org.users.flash_removed'));

        return $this->redirect('/app/organization/users');
    }

    private function getActor(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw new \LogicException('Utilisateur attendu.');
        }

        return $user;
    }

    private function redirectToOrganizationOrHome(User $actor): Response
    {
        if (!$actor->hasAnyOrganization()) {
            $this->addFlash('warning', $this->trans('org.show.no_org'));
        }

        return $this->redirectToRoute('app_home');
    }

    #[Route('/mon-organisation/utilisateurs/{userId}/renvoyer-invitation', name: 'app_organization_users_resend_invite', requirements: ['userId' => '\d+'], methods: ['POST'])]
    public function resendInvite(
        #[MapEntity(id: 'userId')] User $member,
        Request $request,
        CurrentOrganizationService $currentOrganizationService,
        EntityManagerInterface $entityManager,
        MailWebhookService $mailWebhookService,
        HttpClientInterface $httpClient,
        LoggerInterface $logger,
        UserActionLogger $userActionLogger,
        ApplicationErrorLogger $applicationErrorLogger,
        #[Autowire('%env(string:INVITE_WEBHOOK_URL)%')] string $inviteWebhookUrl,
        CsrfTokenManagerInterface $csrfTokenManager,
    ): Response {
        $actor = $this->getActor();
        $organization = $currentOrganizationService->getCurrentOrganization();
        if ($organization === null) {
            return $this->redirectToOrganizationOrHome($actor);
        }

        $this->denyAccessUnlessGranted(OrganizationVoter::EDIT, $organization);

        $token = new CsrfToken(
            'resend_org_invite_'.$member->getId(),
            (string) $request->request->get('_token'),
        );
        if (!$csrfTokenManager->isTokenValid($token)) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        if (!$member->belongsToOrganization($organization)) {
            $this->addFlash('danger', $this->trans('org.users.flash_not_member'));

            return $this->redirect('/app/organization/users');
        }

        $canResend = $member->hasPendingOrganizationInvitation()
            || ($member->isPendingProfileOnboarding() && !$member->isEmailVerified());

        if (!$canResend) {
            $this->addFlash('info', $this->trans('org.users.flash_resend_not_applicable'));

            return $this->redirect('/app/organization/users');
        }

        $this->issueOrganizationInviteToken($member);
        $entityManager->flush();

        $email = mb_strtolower(trim($member->getEmail()));
        $acceptUrl = $this->generateUrl(
            'app_organization_invitation_accept',
            ['token' => (string) $member->getOrganizationInviteToken()],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );
        $this->notifyMemberInvited(
            $email,
            $organization,
            $request,
            $mailWebhookService,
            $httpClient,
            $inviteWebhookUrl,
            $logger,
            $applicationErrorLogger,
            $actor,
            $acceptUrl,
            null,
        );

        $userActionLogger->log(
            'ORG_INVITE_RESENT',
            $actor,
            null,
            [
                'targetUserId' => $member->getId(),
                'organizationId' => $organization->getId(),
            ],
            $request,
        );

        $this->addFlash('success', $this->trans('org.users.flash_invite_resent'));

        return $this->redirect('/app/organization/users');
    }

    private function issueOrganizationInviteToken(User $user): void
    {
        $token = bin2hex(random_bytes(32));
        $user->setOrganizationInviteToken($token);
        $user->setOrganizationInviteExpiresAt((new \DateTimeImmutable())->modify('+24 hours'));
        $user->clearPasswordReset();
    }

    private function notifyMemberInvited(
        string $email,
        Organization $organization,
        Request $request,
        MailWebhookService $mailWebhookService,
        HttpClientInterface $httpClient,
        string $inviteWebhookUrl,
        LoggerInterface $logger,
        ApplicationErrorLogger $applicationErrorLogger,
        ?User $actorUser,
        ?string $acceptInvitationUrl,
        ?string $loginUrl,
    ): void {
        $appUrl = $request->getSchemeAndHttpHost();
        $organizationName = $organization->getName();

        try {
            if ($acceptInvitationUrl !== null) {
                $mailWebhookService->send(
                    'user_invitation',
                    $email,
                    $this->trans('mail.subject.user_invitation'),
                    [
                        'email' => $email,
                        'organizationName' => $organizationName,
                        'acceptInvitationUrl' => $acceptInvitationUrl,
                        'inviteUrl' => $acceptInvitationUrl,
                        'setPasswordUrl' => $acceptInvitationUrl,
                        'appUrl' => $appUrl,
                        'expires_note' => $this->trans('org.users.invite_expires_note'),
                    ],
                );
            } elseif ($loginUrl !== null) {
                $mailWebhookService->send(
                    'user_organization_added',
                    $email,
                    $this->trans('mail.subject.user_organization_added'),
                    [
                        'email' => $email,
                        'organizationName' => $organizationName,
                        'loginUrl' => $loginUrl,
                        'appUrl' => $appUrl,
                    ],
                );
            }
        } catch (\Throwable $e) {
            $logger->warning('Mail webhook invitation membre.', ['exception' => $e, 'email' => $email]);
            $applicationErrorLogger->logThrowable($e, $request, $actorUser, [
                'layer' => 'org_invite_mail_webhook',
                'invitedEmail' => $email,
                'organizationId' => $organization->getId(),
            ], 'caught');
        }

        if ($inviteWebhookUrl === '') {
            return;
        }

        $payload = [
            'source' => 'alertjet-builder-user-invite',
            'email' => $email,
            'user_email' => $email,
            'organization_name' => $organizationName,
            'app_url' => $appUrl,
            'accept_invitation_url' => $acceptInvitationUrl,
            'set_password_url' => $acceptInvitationUrl,
            'login_url' => $loginUrl,
            'invite_url' => $acceptInvitationUrl ?? $loginUrl,
            'expires_note' => $acceptInvitationUrl !== null ? $this->trans('org.users.invite_expires_note') : '',
            'submitted_at' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
        ];

        try {
            $httpClient->request('POST', $inviteWebhookUrl, [
                'json' => $payload,
                'timeout' => 10,
                'headers' => ['Accept' => 'application/json'],
            ]);
        } catch (\Throwable $e) {
            $logger->warning('Invite webhook failed.', ['error' => $e->getMessage(), 'email' => $email]);
            $applicationErrorLogger->logThrowable($e, $request, $actorUser, [
                'layer' => 'org_invite_external_webhook',
                'invitedEmail' => $email,
                'organizationId' => $organization->getId(),
            ], 'caught');
        }
    }
}
