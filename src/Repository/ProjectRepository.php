<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Organization;
use App\Entity\Project;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Project>
 */
class ProjectRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Project::class);
    }

    public function findByWebhookToken(string $token): ?Project
    {
        return $this->findOneBy(['webhookToken' => $token]);
    }

    /**
     * @return list<Project>
     */
    public function findByOrganizationOrderedByName(Organization $organization): array
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.ticketHandlers', 'ticket_handlers')->addSelect('ticket_handlers')
            ->andWhere('p.organization = :org')
            ->setParameter('org', $organization)
            ->orderBy('p.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<Project>
     */
    public function findFilteredByOrganization(Organization $organization, ?string $search): array
    {
        $qb = $this->createQueryBuilder('p')
            ->leftJoin('p.ticketHandlers', 'th')->addSelect('th')
            ->andWhere('p.organization = :org')
            ->setParameter('org', $organization)
            ->orderBy('p.name', 'ASC');

        if ($search !== null && trim($search) !== '') {
            $qb->andWhere('LOWER(p.name) LIKE :search')
                ->setParameter('search', '%'.mb_strtolower(trim($search)).'%');
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Indique si un autre projet de cette organisation porte déjà ce nom (comparaison stricte).
     */
    public function existsOtherWithNameInOrganization(Organization $organization, string $name, ?int $excludeProjectId = null): bool
    {
        $qb = $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->andWhere('p.organization = :org')
            ->andWhere('p.name = :name')
            ->setParameter('org', $organization)
            ->setParameter('name', $name);

        if ($excludeProjectId !== null) {
            $qb->andWhere('p.id != :id')->setParameter('id', $excludeProjectId);
        }

        return (int) $qb->getQuery()->getSingleScalarResult() > 0;
    }
}
