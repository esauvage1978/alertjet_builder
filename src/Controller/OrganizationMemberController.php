<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Organization;
use App\Entity\User;
use App\Form\OrganizationType;
use App\Security\Voter\OrganizationVoter;
use App\Service\CurrentOrganizationService;
use App\Http\AcceptJson;
use App\Service\UserActionLogger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use App\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('IS_AUTHENTICATED_FULLY')]
final class OrganizationMemberController extends AbstractController
{
    #[Route('/organisation/contexte/{orgToken}', name: 'app_organization_context_switch', requirements: ['orgToken' => '[a-f0-9]{12}'], methods: ['GET'])]
    public function switchContext(
        #[MapEntity(mapping: ['orgToken' => 'publicToken'])] Organization $organization,
        CurrentOrganizationService $currentOrganizationService,
        Request $request,
    ): RedirectResponse {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw new \LogicException('Utilisateur attendu.');
        }
        if (!$user->belongsToOrganization($organization)) {
            throw $this->createAccessDeniedException();
        }

        $currentOrganizationService->setCurrentOrganization($organization);

        $referer = $request->headers->get('Referer');
        $base = $request->getSchemeAndHttpHost();
        if (\is_string($referer) && str_starts_with($referer, $base)) {
            return $this->redirect($referer);
        }

        return $this->redirectToRoute('app_home');
    }

    #[Route('/mes-organisations', name: 'app_organization_mine')]
    public function mineRedirect(): RedirectResponse
    {
        return $this->redirectToRoute('app_organization_show');
    }

    /**
     * Ancienne URL d’édition : bascule sur l’organisation cible puis fiche unique « Mon organisation ».
     */
    #[Route('/organisation/{orgToken}/edit', name: 'app_organization_edit', requirements: ['orgToken' => '[a-f0-9]{12}'])]
    public function editRedirect(
        #[MapEntity(mapping: ['orgToken' => 'publicToken'])] Organization $organization,
        CurrentOrganizationService $currentOrganizationService,
    ): RedirectResponse {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw new \LogicException('Utilisateur attendu.');
        }
        if (!$user->belongsToOrganization($organization)) {
            throw $this->createAccessDeniedException();
        }

        $currentOrganizationService->setCurrentOrganization($organization);

        return $this->redirectToRoute('app_organization_show');
    }

    #[Route('/mon-organisation', name: 'app_organization_show', methods: ['GET', 'POST'])]
    public function show(
        Request $request,
        CurrentOrganizationService $currentOrganizationService,
        EntityManagerInterface $entityManager,
        UserActionLogger $userActionLogger,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw new \LogicException('Utilisateur attendu.');
        }

        $organization = $currentOrganizationService->getCurrentOrganization();
        if ($organization === null) {
            if (!$user->hasAnyOrganization()) {
                $this->addFlash('warning', $this->trans('org.show.no_org'));
            }

            return $this->redirectToRoute('app_home');
        }

        if (!$user->belongsToOrganization($organization)) {
            throw $this->createAccessDeniedException();
        }

        if (!$user->canAccessOrganizationBillingPage()) {
            $this->addFlash('warning', $this->trans('flash.org_billing_forbidden'));

            return $this->redirectToRoute('app_home');
        }

        $canEdit = $this->isGranted(OrganizationVoter::EDIT, $organization);
        $form = $this->createForm(OrganizationType::class, $organization);
        if (!$canEdit) {
            $form->disable();
        }

        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            if (!$canEdit) {
                throw $this->createAccessDeniedException();
            }
            if ($form->isValid()) {
                $entityManager->flush();
                $userActionLogger->log(
                    'ORGANIZATION_UPDATED_GESTIONNAIRE',
                    $user,
                    null,
                    ['organizationId' => $organization->getId(), 'name' => $organization->getName()],
                    $request,
                );
                $this->addFlash('success', $this->trans('flash.org_updated'));

                return $this->redirect('/app/organization/billing#org-pane-general');
            }
            $this->addFlash('danger', $this->trans('flash.form_invalid'));
        }

        if ($request->isMethod('GET') && AcceptJson::wants($request)) {
            return $this->spaJsonStub($request, 'app_organization_show');
        }

        if ($request->isMethod('GET')) {
            return $this->redirect('/app/organization/billing');
        }

        return $this->redirect('/app/organization/billing');
    }
}
