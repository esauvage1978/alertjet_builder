<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Organization;
use App\Entity\Project;
use App\Entity\Ticket;
use App\Enum\TicketStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Ticket>
 */
class TicketRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Ticket::class);
    }

    public function findOpenByFingerprint(Project $project, string $fingerprint): ?Ticket
    {
        return $this->createQueryBuilder('t')
            ->where('t.project = :project')
            ->andWhere('t.fingerprint = :fp')
            ->andWhere('t.status IN (:openish)')
            ->setParameter('project', $project)
            ->setParameter('fp', $fingerprint)
            ->setParameter(
                'openish',
                [TicketStatus::Open, TicketStatus::InProgress],
            )
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return list<Ticket>
     */
    public function findForOrganization(Organization $organization, int $limit = 500): array
    {
        return $this->createQueryBuilder('t')
            ->innerJoin('t.project', 'p')
            ->addSelect('p')
            ->where('p.organization = :org')
            ->setParameter('org', $organization)
            ->orderBy('t.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
