<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Organization;
use App\Entity\OrganizationClientAccess;
use App\Entity\OrganizationContact;
use App\Entity\User;
use App\Enum\UserRole;
use App\Repository\OrganizationClientAccessRepository;
use App\Repository\OrganizationContactRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Crée ou met à jour un contact e-mail au sein d’une organisation (émetteur de tickets mail).
 */
final class OrganizationContactService
{
    public function __construct(
        private readonly OrganizationContactRepository $organizationContactRepository,
        private readonly UserRepository $userRepository,
        private readonly OrganizationClientAccessRepository $organizationClientAccessRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function findOrCreateForOrganization(Organization $organization, string $email, ?string $displayName): ?OrganizationContact
    {
        $normalized = mb_strtolower(trim($email));
        if ($normalized === '' || !filter_var($normalized, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        $existing = $this->organizationContactRepository->findOneByOrganizationAndEmail($organization, $normalized);
        if ($existing !== null) {
            $dn = $displayName !== null ? trim($displayName) : '';
            if ($dn !== '' && ($existing->getDisplayName() === null || $existing->getDisplayName() === '')) {
                $existing->setDisplayName($dn);
                $existing->setUpdatedAt(new \DateTimeImmutable());
            }

            $this->ensureClientUserExistsForOrganization($organization, $existing->getEmail(), $existing->getDisplayName());

            return $existing;
        }

        $contact = (new OrganizationContact())
            ->setOrganization($organization)
            ->setEmail($normalized)
            ->setDisplayName(
                $displayName !== null && trim($displayName) !== '' ? trim($displayName) : null,
            );

        $this->entityManager->persist($contact);

        $this->ensureClientUserExistsForOrganization($organization, $contact->getEmail(), $contact->getDisplayName());

        return $contact;
    }

    public function ensureClientUserExistsForOrganization(Organization $organization, string $emailNormalized, ?string $displayName): void
    {
        $user = $this->userRepository->findOneByEmailLowercase($emailNormalized);
        if ($user === null) {
            $user = (new User())->setEmail($emailNormalized);
            $user->setRoles([UserRole::Client->value]);
            $user->setPassword($this->passwordHasher->hashPassword($user, bin2hex(random_bytes(32))));
            $user->setPendingProfileOnboarding(true);
            $user->clearEmailVerification();
            $this->entityManager->persist($user);
        } else {
            $roles = $user->getRoles();
            if (!\in_array(UserRole::Client->value, $roles, true)) {
                $roles[] = UserRole::Client->value;
                $user->setRoles(array_values(array_unique($roles)));
            }
        }

        if (!$user->belongsToOrganization($organization)) {
            $user->addOrganization($organization);
        }

        if (($user->getDisplayName() === null || trim($user->getDisplayName() ?? '') === '') && $displayName !== null && trim($displayName) !== '') {
            $user->setDisplayName(trim($displayName));
        }

        if (!$this->organizationClientAccessRepository->userHasAccess($user, $organization)) {
            $access = (new OrganizationClientAccess())
                ->setOrganization($organization)
                ->setUser($user);
            $this->entityManager->persist($access);
        }
    }
}
