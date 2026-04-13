<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ApplicationErrorLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ApplicationErrorLog>
 */
final class ApplicationErrorLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ApplicationErrorLog::class);
    }

    /**
     * @return Paginator<ApplicationErrorLog>
     */
    public function createAdminPaginator(int $page, int $perPage, ?string $qClass, ?string $qMessage): Paginator
    {
        $page = max(1, $page);
        $qb = $this->createQueryBuilder('e')
            ->orderBy('e.createdAt', 'DESC');

        if ($qClass !== null && $qClass !== '') {
            $qb->andWhere('e.exceptionClass LIKE :qc')
                ->setParameter('qc', '%'.addcslashes($qClass, '%_\\').'%');
        }

        if ($qMessage !== null && $qMessage !== '') {
            $qb->andWhere('e.message LIKE :qm')
                ->setParameter('qm', '%'.addcslashes($qMessage, '%_\\').'%');
        }

        $query = $qb->getQuery()
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage);

        return new Paginator($query, true);
    }
}
