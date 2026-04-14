<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Organization;
use App\Entity\OrganizationClientAccess;
use App\Entity\User;
use App\Http\AcceptJson;
use App\Repository\OrganizationClientAccessRepository;
use App\Repository\UserRepository;
use App\Security\Voter\OrganizationVoter;
use App\Service\CurrentOrganizationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
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
        EntityManagerInterface $entityManager,
        CsrfTokenManagerInterface $csrfTokenManager,
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
            if (!\in_array('ROLE_CLIENT', $target->getRoles(), true)) {
                return $this->clientAccessJsonOrRedirect($wantsJson, false, 'org.clients.error_not_client_role');
            }
            if ($accessRepository->userHasAccess($target, $organization)) {
                return $this->clientAccessJsonOrRedirect($wantsJson, false, 'org.clients.error_already');
            }
            $access = (new OrganizationClientAccess())
                ->setOrganization($organization)
                ->setUser($target);
            $entityManager->persist($access);
            $entityManager->flush();

            return $this->clientAccessJsonOrRedirect($wantsJson, true, 'org.clients.flash_added');
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

        return [
            'migrated' => true,
            'organization' => [
                'id' => $organization->getId(),
                'name' => $organization->getName(),
                'publicToken' => $organization->getPublicToken(),
            ],
            'formCsrf' => $csrfTokenManager->getToken('org_clients')->getValue(),
            'accesses' => array_map(static function (OrganizationClientAccess $a) {
                $u = $a->getUser();

                return [
                    'userId' => $u?->getId(),
                    'email' => $u?->getEmail(),
                    'displayName' => $u?->getDisplayName(),
                    'createdAt' => $a->getCreatedAt()->format(\DateTimeInterface::ATOM),
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
