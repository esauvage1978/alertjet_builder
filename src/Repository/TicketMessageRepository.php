<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\TicketMessage;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TicketMessage>
 */
final class TicketMessageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TicketMessage::class);
    }

    public function findOneByMessageId(string $messageId): ?TicketMessage
    {
        $id = trim($messageId);
        if ($id === '') return null;

        /** @var TicketMessage|null $m */
        $m = $this->findOneBy(['messageId' => $id]);

        return $m;
    }
}

