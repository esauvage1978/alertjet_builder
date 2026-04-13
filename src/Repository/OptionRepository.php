<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Option;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Option>
 */
final class OptionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Option::class);
    }

    /**
     * @return list<Option>
     */
    public function findForAdmin(?string $categoryExact, ?string $domainExact, ?string $optionNamePartial): array
    {
        $qb = $this->createQueryBuilder('o')
            ->orderBy('o.category', 'ASC')
            ->addOrderBy('o.domain', 'ASC')
            ->addOrderBy('o.optionName', 'ASC');

        if ($categoryExact !== null && $categoryExact !== '') {
            $qb->andWhere('o.category = :cat')->setParameter('cat', $categoryExact);
        }

        if ($domainExact !== null && $domainExact !== '') {
            $qb->andWhere('o.domain = :dom')->setParameter('dom', $domainExact);
        }

        if ($optionNamePartial !== null && $optionNamePartial !== '') {
            $qb->andWhere('o.optionName LIKE :partial')
                ->setParameter('partial', '%'.addcslashes($optionNamePartial, '%_\\').'%');
        }

        /** @var list<Option> $list */
        $list = $qb->getQuery()->getResult();

        return $list;
    }
}
