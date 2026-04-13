<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\User;
use App\Entity\UserActionLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UserActionLog>
 */
final class UserActionLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserActionLog::class);
    }

    /**
     * @return list<UserActionLog>
     */
    public function findRecentForUser(User $user, int $limit = 100): array
    {
        /** @var list<UserActionLog> $rows */
        $rows = $this->createQueryBuilder('l')
            ->andWhere('l.user = :user')
            ->setParameter('user', $user)
            ->orderBy('l.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return $rows;
    }

    /**
     * @return Paginator<UserActionLog>
     */
    public function createAdminPaginator(int $page, int $perPage, ?string $actionFilter, ?string $actorSearch): Paginator
    {
        $page = max(1, $page);
        $qb = $this->createQueryBuilder('l')
            ->leftJoin('l.user', 'lu')
            ->addSelect('lu')
            ->orderBy('l.createdAt', 'DESC');

        if ($actionFilter !== null && $actionFilter !== '') {
            $qb->andWhere('l.action LIKE :act')
                ->setParameter('act', '%'.addcslashes($actionFilter, '%_\\').'%');
        }

        if ($actorSearch !== null && $actorSearch !== '') {
            $like = '%'.addcslashes($actorSearch, '%_\\').'%';
            $qb->andWhere('l.actorEmail LIKE :actor OR lu.email LIKE :actor')
                ->setParameter('actor', $like);
        }

        $query = $qb->getQuery()
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage);

        return new Paginator($query, true);
    }
}
