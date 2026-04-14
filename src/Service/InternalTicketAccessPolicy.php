<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Organization;
use App\Entity\User;
use App\Repository\OrganizationClientAccessRepository;

/**
 * Qui peut ouvrir le flux « nouveau ticket » (formulaire interne), selon le rôle et l’activation projet.
 */
final class InternalTicketAccessPolicy
{
    public function canCreateInternalTicket(
        User $user,
        Organization $organization,
        bool $organizationHasInternalFormProject,
        OrganizationClientAccessRepository $clientAccessRepository,
    ): bool {
        if (!$user->belongsToOrganization($organization)) {
            return false;
        }
        if ($user->isAdministratorOrManager()) {
            return true;
        }

        $role = $user->getPrimaryRoleCatalogKey();
        if ($role === 'client') {
            return $organizationHasInternalFormProject
                && $clientAccessRepository->userHasAccess($user, $organization);
        }
        if ($role === 'user') {
            return $organizationHasInternalFormProject;
        }

        return false;
    }
}
