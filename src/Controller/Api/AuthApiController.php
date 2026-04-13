<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Controller\AbstractController;
use App\Entity\Organization;
use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/api/auth')]
final class AuthApiController extends AbstractController
{
    #[Route('/login-context', name: 'api_auth_login_context', methods: ['GET'])]
    public function loginContext(
        AuthenticationUtils $authenticationUtils,
        CsrfTokenManagerInterface $csrfTokenManager,
        TranslatorInterface $translator,
    ): JsonResponse {
        $error = $authenticationUtils->getLastAuthenticationError();
        $errorMessage = null;
        if ($error instanceof AuthenticationException) {
            $errorMessage = $translator->trans($error->getMessageKey(), $error->getMessageData(), 'security');
        }

        return $this->json([
            'lastUsername' => $authenticationUtils->getLastUsername(),
            'errorMessage' => $errorMessage,
            'csrf' => $csrfTokenManager->getToken('authenticate')->getValue(),
        ]);
    }

    #[Route('/csrf-forms', name: 'api_auth_csrf_forms', methods: ['GET'])]
    public function csrfForms(CsrfTokenManagerInterface $csrfTokenManager): JsonResponse
    {
        return $this->json([
            'authenticate' => $csrfTokenManager->getToken('authenticate')->getValue(),
            'registration_form' => $csrfTokenManager->getToken('registration_form')->getValue(),
            'forgot_password_form' => $csrfTokenManager->getToken('forgot_password_form')->getValue(),
            'reset_password_form' => $csrfTokenManager->getToken('reset_password_form')->getValue(),
        ]);
    }

    #[Route('/invitation/{token}', name: 'api_auth_invitation_meta', methods: ['GET'])]
    public function invitationMeta(string $token, UserRepository $userRepository, CsrfTokenManagerInterface $csrfTokenManager): JsonResponse
    {
        $user = $userRepository->findOneByOrganizationInviteToken($token);
        if (
            !$user instanceof User
            || $user->getOrganizationInviteExpiresAt() === null
            || $user->getOrganizationInviteExpiresAt() < new \DateTimeImmutable()
        ) {
            return $this->json(['valid' => false], 404);
        }

        $organizationName = '';
        $first = $user->getOrganizations()->first();
        if ($first instanceof Organization) {
            $organizationName = $first->getName();
        }

        return $this->json([
            'valid' => true,
            'organizationName' => $organizationName,
            'csrf' => $csrfTokenManager->getToken('reset_password_form')->getValue(),
        ]);
    }
}
