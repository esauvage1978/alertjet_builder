<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Controller\AbstractController;
use App\Entity\User;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Session courante pour clients API / monitoring : jamais de 401 anonyme (évite le bruit dans le profiler).
 */
final class MeApiController extends AbstractController
{
    #[Route('/api/me', name: 'api_me', methods: ['GET', 'HEAD'])]
    public function __invoke(): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['authenticated' => false]);
        }

        return $this->json([
            'authenticated' => true,
            'user' => [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'displayName' => $user->getDisplayName(),
                'initials' => $user->getAvatarInitials(),
                'avatarColor' => $user->getAvatarColorOrDefault(),
                'avatarForegroundColor' => $user->getAvatarForegroundColorOrDefault(),
                'primaryRoleKey' => $user->getPrimaryRoleCatalogKey(),
                'roleBadgeClass' => $user->getRoleBadgeClass(),
                'roles' => $user->getRoles(),
            ],
        ]);
    }
}
