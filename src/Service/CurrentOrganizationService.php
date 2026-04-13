<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Organization;
use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Organisation « courante » (session), parmi celles du compte connecté.
 */
final class CurrentOrganizationService
{
    private const SESSION_KEY = 'current_organization_id';

    private const REQUEST_ATTR = '_current_organization';

    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly Security $security,
    ) {
    }

    public function getCurrentOrganization(): ?Organization
    {
        $request = $this->requestStack->getCurrentRequest();
        if ($request === null) {
            return null;
        }

        if ($request->attributes->has(self::REQUEST_ATTR)) {
            /** @var Organization|null */
            return $request->attributes->get(self::REQUEST_ATTR);
        }

        $user = $this->security->getUser();
        if (!$user instanceof User || !$user->hasAnyOrganization()) {
            $request->attributes->set(self::REQUEST_ATTR, null);

            return null;
        }

        $sorted = $this->sortOrganizations($user);
        $session = $request->getSession();
        $id = $session->has(self::SESSION_KEY) ? (int) $session->get(self::SESSION_KEY) : null;

        $current = null;
        if ($id > 0) {
            foreach ($sorted as $org) {
                if ($org->getId() === $id) {
                    $current = $org;
                    break;
                }
            }
        }

        if ($current === null && $sorted !== []) {
            $current = $sorted[0];
            $session->set(self::SESSION_KEY, $current->getId());
        }

        $request->attributes->set(self::REQUEST_ATTR, $current);

        return $current;
    }

    /**
     * @return list<Organization>
     */
    public function getOrganizationsSorted(): array
    {
        $user = $this->security->getUser();
        if (!$user instanceof User || !$user->hasAnyOrganization()) {
            return [];
        }

        return $this->sortOrganizations($user);
    }

    public function setCurrentOrganization(Organization $organization): void
    {
        $request = $this->requestStack->getCurrentRequest();
        if ($request === null) {
            return;
        }

        $request->getSession()->set(self::SESSION_KEY, $organization->getId());
        $request->attributes->set(self::REQUEST_ATTR, $organization);
    }

    /**
     * @return list<Organization>
     */
    private function sortOrganizations(User $user): array
    {
        $orgs = $user->getOrganizations()->toArray();
        usort(
            $orgs,
            static fn (Organization $a, Organization $b): int => strcasecmp($a->getName(), $b->getName()),
        );

        return $orgs;
    }
}
