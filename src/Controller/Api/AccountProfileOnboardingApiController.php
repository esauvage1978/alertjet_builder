<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Controller\AbstractController;
use App\Entity\User;
use App\Util\AvatarForegroundPalette;
use App\Util\AvatarPalette;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/account')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
final class AccountProfileOnboardingApiController extends AbstractController
{
    #[Route('/profil-onboarding', name: 'api_account_profile_onboarding', methods: ['GET'])]
    public function profileOnboarding(CsrfTokenManagerInterface $csrfTokenManager): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw new \LogicException('Utilisateur attendu.');
        }

        return $this->json([
            'csrf' => $csrfTokenManager->getToken('submit')->getValue(),
            'action' => $this->generateUrl('app_account_profile_onboarding'),
            'displayName' => $user->getDisplayName(),
            'avatarInitialsCustom' => $user->getAvatarInitialsCustom(),
            'avatarColor' => $user->getAvatarColor(),
            'avatarForegroundColor' => $user->getAvatarForegroundColor(),
            'avatarBgChoices' => AvatarPalette::choices(),
            'avatarFgChoices' => AvatarForegroundPalette::choices(),
        ]);
    }
}
