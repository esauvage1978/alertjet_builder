<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Organization;
use App\Form\OrganizationType;
use App\Repository\OrganizationRepository;
use App\Service\UserActionLogger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use App\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/organisations')]
#[IsGranted('ROLE_ADMIN')]
final class AdminOrganizationController extends AbstractController
{
    #[Route('', name: 'admin_organization_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->redirect('/app/admin/organisations');
    }

    #[Route('/nouvelle', name: 'admin_organization_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $entityManager,
        UserActionLogger $userActionLogger,
    ): Response {
        $organization = new Organization();
        $form = $this->createForm(OrganizationType::class, $organization);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($organization);
            $entityManager->flush();
            $userActionLogger->log(
                'ADMIN_ORGANIZATION_CREATED',
                $this->getUser(),
                null,
                ['organizationId' => $organization->getId(), 'name' => $organization->getName()],
                $request,
            );
            $this->addFlash('success', $this->trans('flash.org_created'));

            return $this->redirectToRoute('admin_organization_index');
        }

        return $this->render('admin/organization/new.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/{orgToken}/modifier', name: 'admin_organization_edit', requirements: ['orgToken' => '[a-f0-9]{32}'], methods: ['GET', 'POST'])]
    public function edit(
        #[MapEntity(mapping: ['orgToken' => 'publicToken'])] Organization $organization,
        Request $request,
        EntityManagerInterface $entityManager,
        UserActionLogger $userActionLogger,
    ): Response {
        $form = $this->createForm(OrganizationType::class, $organization);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();
            $userActionLogger->log(
                'ADMIN_ORGANIZATION_UPDATED',
                $this->getUser(),
                null,
                ['organizationId' => $organization->getId(), 'name' => $organization->getName()],
                $request,
            );
            $this->addFlash('success', $this->trans('flash.org_updated'));

            return $this->redirectToRoute('admin_organization_index');
        }

        return $this->render('admin/organization/edit.html.twig', [
            'organization' => $organization,
            'form' => $form,
        ]);
    }

    #[Route('/{orgToken}/supprimer', name: 'admin_organization_delete', requirements: ['orgToken' => '[a-f0-9]{32}'], methods: ['POST'])]
    public function delete(
        #[MapEntity(mapping: ['orgToken' => 'publicToken'])] Organization $organization,
        Request $request,
        EntityManagerInterface $entityManager,
        CsrfTokenManagerInterface $csrfTokenManager,
        UserActionLogger $userActionLogger,
    ): Response {
        $token = new CsrfToken('delete_organization_'.$organization->getId(), (string) $request->request->get('_token'));
        if (!$csrfTokenManager->isTokenValid($token)) {
            throw $this->createAccessDeniedException($this->trans('error.invalid_csrf'));
        }

        $id = $organization->getId();
        $name = $organization->getName();

        $entityManager->remove($organization);
        $entityManager->flush();

        $userActionLogger->log(
            'ADMIN_ORGANIZATION_DELETED',
            $this->getUser(),
            null,
            ['organizationId' => $id, 'name' => $name],
            $request,
        );

        $this->addFlash('success', $this->trans('flash.org_deleted'));

        return $this->redirectToRoute('admin_organization_index');
    }
}
