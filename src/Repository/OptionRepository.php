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

    public function getIntValue(string $category, string $optionName, ?string $domain, int $default): int
    {
        $qb = $this->createQueryBuilder('o')
            ->andWhere('o.category = :cat')->setParameter('cat', $category)
            ->andWhere('o.optionName = :name')->setParameter('name', $optionName)
            ->setMaxResults(1);

        if ($domain !== null) {
            $qb->andWhere('o.domain = :dom')->setParameter('dom', $domain);
        } else {
            $qb->andWhere('o.domain IS NULL');
        }

        /** @var Option|null $opt */
        $opt = $qb->getQuery()->getOneOrNullResult();
        if (!$opt instanceof Option) {
            return $default;
        }

        $raw = trim($opt->getOptionValue());
        if ($raw === '') {
            return $default;
        }

        $n = filter_var($raw, \FILTER_VALIDATE_INT);

        return \is_int($n) ? $n : $default;
    }

    public function getTextValue(string $category, string $optionName, ?string $domain, string $default): string
    {
        $qb = $this->createQueryBuilder('o')
            ->andWhere('o.category = :cat')->setParameter('cat', $category)
            ->andWhere('o.optionName = :name')->setParameter('name', $optionName)
            ->setMaxResults(1);

        if ($domain !== null) {
            $qb->andWhere('o.domain = :dom')->setParameter('dom', $domain);
        } else {
            $qb->andWhere('o.domain IS NULL');
        }

        /** @var Option|null $opt */
        $opt = $qb->getQuery()->getOneOrNullResult();
        if (!$opt instanceof Option) {
            return $default;
        }

        $raw = trim($opt->getOptionValue());

        return $raw !== '' ? $raw : $default;
    }
}
