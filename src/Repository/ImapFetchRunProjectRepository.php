<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ImapFetchRunProject;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ImapFetchRunProject>
 */
final class ImapFetchRunProjectRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ImapFetchRunProject::class);
    }
}

