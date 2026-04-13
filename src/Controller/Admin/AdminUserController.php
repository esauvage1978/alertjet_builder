<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\User;
use App\Form\AdminUserFormType;
use App\Repository\OrganizationRepository;
use App\Repository\UserRepository;
use App\Service\UserActionLogger;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use App\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/utilisateurs')]
#[IsGranted('ROLE_ADMIN')]
final class AdminUserController extends AbstractController
{
    private const USERS_PER_PAGE = 20;

    #[Route('', name: 'admin_user_index', methods: ['GET'])]
    public function index(Request $request): Response {
        $qs = $request->getQueryString();

        return $this->redirect('/app/admin/utilisateurs'.($qs !== null && $qs !== '' ? '?'.$qs : ''));
    }

    /**
     * @return array<string, int|string>
     */
    private function adminUserIndexQuery(?int $organizationFilter, string $q, ?int $page = null): array
    {
        $params = [];
        if ($organizationFilter !== null) {
            $params['organization'] = $organizationFilter;
        }
        if ($q !== '') {
            $params['q'] = $q;
        }
        if ($page !== null) {
            $params['page'] = $page;
        }

        return $params;
    }

    /**
     * @return list<int>
     */
    private function paginationPageWindow(int $page, int $pageCount): array
    {
        if ($pageCount <= 1) {
            return [1];
        }
        if ($pageCount <= 12) {
            return range(1, $pageCount);
        }

        $radius = 4;
        $lo = max(1, $page - $radius);
        $hi = min($pageCount, $page + $radius);
        if ($lo === 1) {
            $hi = min($pageCount, 1 + 2 * $radius);
        }
        if ($hi === $pageCount) {
            $lo = max(1, $pageCount - 2 * $radius);
        }

        return range($lo, $hi);
    }

    #[Route('/nouveau', name: 'admin_user_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
        UserActionLogger $userActionLogger,
    ): Response {
        $user = new User();
        $form = $this->createForm(AdminUserFormType::class, $user, ['require_password' => true]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $plain = (string) $form->get('plainPassword')->getData();
            $primary = $form->get('primaryRole')->getData();
            $user->setRoles([\is_string($primary) ? $primary : 'ROLE_USER']);
            $user->setPassword($passwordHasher->hashPassword($user, $plain));
            $user->setEmailVerifiedAt(new \DateTimeImmutable());
            $user->clearEmailVerification();
            $user->clearPasswordReset();

            $entityManager->persist($user);
            $entityManager->flush();

            $userActionLogger->log(
                'ADMIN_USER_CREATED',
                $this->getUser(),
                null,
                ['targetUserId' => $user->getId(), 'email' => $user->getEmail()],
                $request,
            );
            $this->addFlash('success', $this->trans('flash.admin_user_created'));

            return $this->redirectToRoute('admin_user_index');
        }

        return $this->render('admin/user/new.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/{id}/modifier', name: 'admin_user_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(
        #[MapEntity(id: 'id')] User $user,
        Request $request,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
        UserActionLogger $userActionLogger,
    ): Response {
        $form = $this->createForm(AdminUserFormType::class, $user, ['require_password' => false]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $primary = $form->get('primaryRole')->getData();
            $user->setRoles([\is_string($primary) ? $primary : 'ROLE_USER']);

            $plain = $form->get('plainPassword')->getData();
            if (\is_string($plain) && $plain !== '') {
                $user->setPassword($passwordHasher->hashPassword($user, $plain));
            }

            $entityManager->flush();

            $userActionLogger->log(
                'ADMIN_USER_UPDATED',
                $this->getUser(),
                null,
                ['targetUserId' => $user->getId(), 'email' => $user->getEmail()],
                $request,
            );
            $this->addFlash('success', $this->trans('flash.admin_user_updated'));

            return $this->redirectToRoute('admin_user_index');
        }

        return $this->render('admin/user/edit.html.twig', [
            'user' => $user,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/supprimer', name: 'admin_user_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(
        #[MapEntity(id: 'id')] User $user,
        Request $request,
        EntityManagerInterface $entityManager,
        CsrfTokenManagerInterface $csrfTokenManager,
        UserActionLogger $userActionLogger,
    ): Response {
        $token = new CsrfToken('delete_user_'.$user->getId(), (string) $request->request->get('_token'));
        if (!$csrfTokenManager->isTokenValid($token)) {
            throw $this->createAccessDeniedException($this->trans('error.invalid_csrf'));
        }

        $current = $this->getUser();
        if ($current instanceof User && $current->getId() === $user->getId()) {
            $this->addFlash('danger', $this->trans('flash.admin_user_delete_self'));

            return $this->redirectToRoute('admin_user_index');
        }

        $id = $user->getId();
        $email = $user->getEmail();

        $entityManager->remove($user);
        $entityManager->flush();

        $userActionLogger->log(
            'ADMIN_USER_DELETED',
            $this->getUser(),
            null,
            ['targetUserId' => $id, 'email' => $email],
            $request,
        );

        $this->addFlash('success', $this->trans('flash.admin_user_deleted'));

        return $this->redirectToRoute('admin_user_index');
    }
}
