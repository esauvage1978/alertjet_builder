<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Organization;
use App\Entity\OrganizationClientAccess;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<OrganizationClientAccess>
 */
class OrganizationClientAccessRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, OrganizationClientAccess::class);
    }

    public function userHasAccess(User $user, Organization $organization): bool
    {
        return null !== $this->findOneBy([
            'user' => $user,
            'organization' => $organization,
        ]);
    }

    /**
     * @return list<OrganizationClientAccess>
     */
    public function findByOrganizationOrderedByEmail(Organization $organization): array
    {
        return $this->createQueryBuilder('a')
            ->innerJoin('a.user', 'u')->addSelect('u')
            ->andWhere('a.organization = :org')
            ->setParameter('org', $organization)
            ->orderBy('u.email', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
