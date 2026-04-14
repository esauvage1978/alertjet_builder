<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Project;
use App\Entity\User;
use App\Http\AcceptJson;
use App\Repository\OrganizationClientAccessRepository;
use App\Repository\ProjectRepository;
use App\Service\CurrentOrganizationService;
use App\Service\InternalTicketAccessPolicy;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('IS_AUTHENTICATED_FULLY')]
final class OrganizationTicketCreateController extends AbstractController
{
    #[Route('/mon-organisation/tickets/nouveau', name: 'app_organization_ticket_new', methods: ['GET'])]
    public function new(
        Request $request,
        CurrentOrganizationService $currentOrganizationService,
        ProjectRepository $projectRepository,
        OrganizationClientAccessRepository $organizationClientAccessRepository,
        InternalTicketAccessPolicy $internalTicketAccessPolicy,
        CsrfTokenManagerInterface $csrfTokenManager,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw new \LogicException();
        }

        $organization = $currentOrganizationService->getCurrentOrganization();
        if ($organization === null) {
            return $this->redirectToRoute('app_home');
        }

        if (!$user->belongsToOrganization($organization)) {
            throw $this->createAccessDeniedException();
        }

        $hasInternalForm = $projectRepository->organizationHasInternalFormIntegrationEnabled($organization);
        if (!$internalTicketAccessPolicy->canCreateInternalTicket($user, $organization, $hasInternalForm, $organizationClientAccessRepository)) {
            if (AcceptJson::wants($request) || $request->isXmlHttpRequest()) {
                return $this->json([
                    'ok' => false,
                    'error' => 'forbidden',
                    'message' => $this->trans('ticket.create.forbidden'),
                ], 403);
            }
            throw $this->createAccessDeniedException();
        }

        if (!AcceptJson::wants($request) && !$request->isXmlHttpRequest()) {
            return $this->redirect('/app/tickets/new');
        }

        $projects = $projectRepository->findByOrganizationWithInternalFormEnabled($organization);

        return $this->json([
            'migrated' => true,
            'organization' => [
                'id' => $organization->getId(),
                'name' => $organization->getName(),
                'publicToken' => $organization->getPublicToken(),
            ],
            'hasInternalFormProject' => $hasInternalForm,
            'projects' => array_map(static fn (Project $p) => [
                'publicToken' => $p->getPublicToken(),
                'name' => $p->getName(),
            ], $projects),
            'formCsrf' => $csrfTokenManager->getToken('internal_ticket_create')->getValue(),
        ]);
    }
}
