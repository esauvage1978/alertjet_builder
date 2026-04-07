<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\TicketLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TicketLog>
 */
class TicketLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TicketLog::class);
    }
}
