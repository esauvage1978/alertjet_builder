<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Organization;
use App\Entity\OrganizationContact;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<OrganizationContact>
 */
class OrganizationContactRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, OrganizationContact::class);
    }

    public function findOneByOrganizationAndEmail(Organization $organization, string $emailNormalized): ?OrganizationContact
    {
        return $this->findOneBy(['organization' => $organization, 'email' => $emailNormalized]);
    }
}
