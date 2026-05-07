<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Organization;
use App\Entity\OrganizationClientAccess;
use App\Entity\User;
use App\Http\AcceptJson;
use App\Repository\OrganizationClientAccessRepository;
use App\Repository\TicketRepository;
use App\Repository\UserRepository;
use App\Security\Voter\OrganizationVoter;
use App\Service\CurrentOrganizationService;
use App\Service\MailWebhookService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('IS_AUTHENTICATED_FULLY')]
final class OrganizationClientAccessController extends AbstractController
{
    #[Route('/mon-organisation/clients', name: 'app_organization_clients', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        CurrentOrganizationService $currentOrganizationService,
        OrganizationClientAccessRepository $accessRepository,
        UserRepository $userRepository,
        TicketRepository $ticketRepository,
        EntityManagerInterface $entityManager,
        CsrfTokenManagerInterface $csrfTokenManager,
        MailWebhookService $mailWebhookService,
    ): Response {
        $actor = $this->getUser();
        if (!$actor instanceof User) {
            throw new \LogicException();
        }

        $organization = $currentOrganizationService->getCurrentOrganization();
        if ($organization === null) {
            return $this->redirectToRoute('app_home');
        }

        $this->denyAccessUnlessGranted(OrganizationVoter::EDIT, $organization);

        if ($request->isMethod('GET')) {
            if (AcceptJson::wants($request) || $request->isXmlHttpRequest()) {
                return $this->json($this->buildClientAccessPayload(
                    $organization,
                    $accessRepository,
                    $userRepository,
                    $ticketRepository,
                    $csrfTokenManager,
                ));
            }

            return $this->redirect('/app/organization/clients');
        }

        $token = new CsrfToken('org_clients', (string) $request->request->get('_token'));
        if (!$csrfTokenManager->isTokenValid($token)) {
            if (AcceptJson::wants($request) || $request->isXmlHttpRequest()) {
                return $this->json([
                    'ok' => false,
                    'error' => 'csrf',
                    'message' => $this->trans('error.invalid_csrf'),
                ], 403);
            }
            $this->addFlash('danger', $this->trans('error.invalid_csrf'));

            return $this->redirect('/app/organization/clients');
        }

        $action = (string) $request->request->get('action', '');
        $wantsJson = AcceptJson::wants($request) || $request->isXmlHttpRequest();

        if ($action === 'remove') {
            $userId = (int) $request->request->get('userId', 0);
            $target = $userRepository->find($userId);
            if ($target === null || !$target->belongsToOrganization($organization)) {
                return $this->clientAccessJsonOrRedirect($wantsJson, false, 'org.clients.error_user');
            }
            $row = $accessRepository->findOneBy(['organization' => $organization, 'user' => $target]);
            if ($row === null) {
                return $this->clientAccessJsonOrRedirect($wantsJson, false, 'org.clients.error_not_granted');
            }
            $entityManager->remove($row);
            $entityManager->flush();

            return $this->clientAccessJsonOrRedirect($wantsJson, true, 'org.clients.flash_removed');
        }

        if ($action === 'add') {
            $userId = (int) $request->request->get('userId', 0);
            $target = $userRepository->find($userId);
            if ($target === null || !$target->belongsToOrganization($organization)) {
                return $this->clientAccessJsonOrRedirect($wantsJson, false, 'org.clients.error_user');
            }
            // On exige ROLE_CLIENT (même si superviseur: on garde ROLE_CLIENT présent dans le tableau).
            if (!\in_array('ROLE_CLIENT', $target->getRoles(), true)) {
                return $this->clientAccessJsonOrRedirect($wantsJson, false, 'org.clients.error_not_client_role');
            }
            if ($accessRepository->userHasAccess($target, $organization)) {
                return $this->clientAccessJsonOrRedirect($wantsJson, false, 'org.clients.error_already');
            }
            $access = (new OrganizationClientAccess())
                ->setOrganization($organization)
                ->setUser($target)
                ->setBlockedAt(null);
            $entityManager->persist($access);
            $entityManager->flush();

            return $this->clientAccessJsonOrRedirect($wantsJson, true, 'org.clients.flash_added');
        }

        if ($action === 'toggle_block') {
            $userId = (int) $request->request->get('userId', 0);
            $target = $userRepository->find($userId);
            if ($target === null || !$target->belongsToOrganization($organization)) {
                return $this->clientAccessJsonOrRedirect($wantsJson, false, 'org.clients.error_user');
            }
            $row = $accessRepository->findOneBy(['organization' => $organization, 'user' => $target]);
            if ($row === null) {
                return $this->clientAccessJsonOrRedirect($wantsJson, false, 'org.clients.error_not_granted');
            }
            if ($row->isBlocked()) {
                $row->setBlockedAt(null);
            } else {
                $row->setBlockedAt(new \DateTimeImmutable());
            }
            $entityManager->flush();

            return $this->clientAccessJsonOrRedirect($wantsJson, true, 'org.clients.flash_updated');
        }

        if ($action === 'set_role') {
            $userId = (int) $request->request->get('userId', 0);
            $role = (string) $request->request->get('role', '');
            $target = $userRepository->find($userId);
            if ($target === null || !$target->belongsToOrganization($organization)) {
                return $this->clientAccessJsonOrRedirect($wantsJson, false, 'org.clients.error_user');
            }
            if (!\in_array('ROLE_CLIENT', $target->getRoles(), true)) {
                return $this->clientAccessJsonOrRedirect($wantsJson, false, 'org.clients.error_not_client_role');
            }

            $roles = $target->getRoles();
            $roles = array_values(array_unique(array_filter($roles, static fn (string $r): bool => $r !== 'ROLE_CLIENT_SUPERVISEUR')));
            if (!\in_array('ROLE_CLIENT', $roles, true)) {
                $roles[] = 'ROLE_CLIENT';
            }
            if ($role === 'client_supervisor') {
                $roles[] = 'ROLE_CLIENT_SUPERVISEUR';
            } elseif ($role !== 'client') {
                return $this->clientAccessJsonOrRedirect($wantsJson, false, 'org.clients.error_bad_role');
            }
            $target->setRoles(array_values(array_unique($roles)));
            $entityManager->flush();

            return $this->clientAccessJsonOrRedirect($wantsJson, true, 'org.clients.flash_updated');
        }

        if ($action === 'send_reset') {
            $userId = (int) $request->request->get('userId', 0);
            $target = $userRepository->find($userId);
            if ($target === null || !$target->belongsToOrganization($organization)) {
                return $this->clientAccessJsonOrRedirect($wantsJson, false, 'org.clients.error_user');
            }
            // Génère un lien de réinitialisation, utilisé comme “initialisation de mot de passe”.
            $resetToken = bin2hex(random_bytes(32));
            $target->setPasswordResetToken($resetToken);
            $target->setPasswordResetExpiresAt((new \DateTimeImmutable())->modify('+1 hour'));
            $entityManager->flush();

            $resetUrl = $this->generateUrl('app_reset_password', ['token' => $resetToken], UrlGeneratorInterface::ABSOLUTE_URL);
            $mailWebhookService->send(
                'user_password_reset',
                $target->getEmail(),
                $this->trans('mail.subject.password_reset'),
                [
                    'resetUrl' => $resetUrl,
                    'email' => $target->getEmail(),
                ],
            );

            return $this->clientAccessJsonOrRedirect($wantsJson, true, 'org.clients.flash_reset_sent');
        }

        if ($wantsJson) {
            return $this->json(['ok' => false, 'error' => 'bad_request', 'message' => 'action'], 400);
        }

        return $this->redirect('/app/organization/clients');
    }

    /**
     * @return array<string, mixed>
     */
    private function buildClientAccessPayload(
        Organization $organization,
        OrganizationClientAccessRepository $accessRepository,
        UserRepository $userRepository,
        TicketRepository $ticketRepository,
        CsrfTokenManagerInterface $csrfTokenManager,
    ): array {
        $rows = $accessRepository->findByOrganizationOrderedByEmail($organization);
        $clientMembers = $userRepository->findOrganizationMembersWithRoleClient($organization);
        $grantedIds = [];
        foreach ($rows as $r) {
            $uid = $r->getUser()?->getId();
            if ($uid !== null) {
                $grantedIds[$uid] = true;
            }
        }
        $eligible = array_values(array_filter($clientMembers, static fn (User $u) => $u->getId() !== null && !isset($grantedIds[$u->getId()])));

        $emails = [];
        foreach ($rows as $r) {
            $email = $r->getUser()?->getEmail();
            if (\is_string($email) && $email !== '') {
                $emails[] = $email;
            }
        }
        $counts = $ticketRepository->countByOrganizationAndContactEmails($organization, $emails);

        return [
            'migrated' => true,
            'organization' => [
                'id' => $organization->getId(),
                'name' => $organization->getName(),
                'publicToken' => $organization->getPublicToken(),
            ],
            'i18n' => [
                'org_clients_intro' => $this->trans('org.clients.intro'),
                'org_clients_empty' => $this->trans('org.clients.empty'),
                'org_clients_th_email' => $this->trans('org.clients.th_email'),
                'org_clients_th_display_name' => $this->trans('org.clients.th_display_name'),
                'org_clients_th_since' => $this->trans('org.clients.th_since'),
                'org_clients_th_role' => $this->trans('org.clients.th_role'),
                'org_clients_th_tickets' => $this->trans('org.clients.th_tickets'),
                'org_clients_th_access' => $this->trans('org.clients.th_access'),
                'org_clients_th_actions' => $this->trans('org.clients.th_actions'),
                'org_clients_choose_placeholder' => $this->trans('org.clients.choose_placeholder'),
                'org_clients_eligible_member_label' => $this->trans('org.clients.eligible_member_label'),
                'org_clients_no_eligible_hint' => $this->trans('org.clients.no_eligible_hint'),
                'org_clients_all_eligible_already' => $this->trans('org.clients.all_eligible_already'),
                'org_clients_add_submit' => $this->trans('org.clients.add_submit'),
                'org_clients_remove' => $this->trans('org.clients.remove'),
                'org_clients_role_client' => $this->trans('org.clients.role_client'),
                'org_clients_role_client_supervisor' => $this->trans('org.clients.role_client_supervisor'),
                'org_clients_access_active' => $this->trans('org.clients.access_active'),
                'org_clients_access_blocked' => $this->trans('org.clients.access_blocked'),
                'org_clients_send_reset' => $this->trans('org.clients.send_reset'),
                'org_clients_reset_title' => $this->trans('org.clients.reset_title'),
                'org_clients_block' => $this->trans('org.clients.block'),
                'org_clients_unblock' => $this->trans('org.clients.unblock'),
                'org_clients_block_title' => $this->trans('org.clients.block_title'),
                'org_clients_unblock_title' => $this->trans('org.clients.unblock_title'),
            ],
            'formCsrf' => $csrfTokenManager->getToken('org_clients')->getValue(),
            'accesses' => array_map(static function (OrganizationClientAccess $a) use ($counts) {
                $u = $a->getUser();
                $roles = $u?->getRoles() ?? [];
                $isSupervisor = \in_array('ROLE_CLIENT_SUPERVISEUR', $roles, true);
                $emailLower = $u?->getEmail() ? mb_strtolower(trim((string) $u->getEmail())) : '';

                return [
                    'userId' => $u?->getId(),
                    'email' => $u?->getEmail(),
                    'displayName' => $u?->getDisplayName(),
                    'createdAt' => $a->getCreatedAt()->format(\DateTimeInterface::ATOM),
                    'blockedAt' => $a->getBlockedAt()?->format(\DateTimeInterface::ATOM),
                    'roleKey' => $isSupervisor ? 'client_supervisor' : 'client',
                    'ticketCount' => $emailLower !== '' ? ($counts[$emailLower] ?? 0) : 0,
                ];
            }, $rows),
            'eligibleUsers' => array_map(static fn (User $u) => [
                'id' => $u->getId(),
                'email' => $u->getEmail(),
                'displayName' => $u->getDisplayName(),
            ], $eligible),
        ];
    }

    private function clientAccessJsonOrRedirect(bool $wantsJson, bool $ok, string $messageKey): Response
    {
        if ($wantsJson) {
            return $this->json([
                'ok' => $ok,
                'message' => $this->trans($messageKey),
            ], $ok ? 200 : 422);
        }
        $this->addFlash($ok ? 'success' : 'danger', $this->trans($messageKey));

        return $this->redirect('/app/organization/clients');
    }
}
