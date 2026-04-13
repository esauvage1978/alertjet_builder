<?php

declare(strict_types=1);

namespace App\Controller;

use App\Api\TicketApiPresenter;
use App\Entity\User;
use App\Http\AcceptJson;
use App\Repository\TicketRepository;
use App\Service\CurrentOrganizationService;
use App\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('IS_AUTHENTICATED_FULLY')]
final class OrganizationTicketsController extends AbstractController
{
    #[Route('/mon-organisation/tickets', name: 'app_organization_tickets', methods: ['GET'])]
    public function index(
        Request $request,
        CurrentOrganizationService $currentOrganizationService,
        TicketRepository $ticketRepository,
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

        $tickets = $ticketRepository->findForOrganization($organization);

        if (!AcceptJson::wants($request)) {
            return $this->redirect('/app/organization/tickets');
        }

        return $this->json([
            'migrated' => true,
            'organization' => [
                'id' => $organization->getId(),
                'name' => $organization->getName(),
                'publicToken' => $organization->getPublicToken(),
            ],
            'tickets' => array_map(static fn ($t) => TicketApiPresenter::one($t), $tickets),
        ]);
    }
}
