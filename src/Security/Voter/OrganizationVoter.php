<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Organization;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Édition d’une organisation : administrateur (toutes les org.) ou gestionnaire membre de l’org.
 */
final class OrganizationVoter extends Voter
{
    public const EDIT = 'ORGANIZATION_EDIT';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $attribute === self::EDIT && $subject instanceof Organization;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        if (\in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            return true;
        }

        if (!\in_array('ROLE_GESTIONNAIRE', $user->getRoles(), true)) {
            return false;
        }

        return $user->belongsToOrganization($subject);
    }
}
