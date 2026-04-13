<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Organization;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Organization>
 */
final class OrganizationRepository extends ServiceEntityRepository
{
    private const TOKEN_RANDOM_ATTEMPTS = 64;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Organization::class);
    }

    /**
     * Jeton hexadécimal sur 12 caractères (6 octets aléatoires), unique en base.
     */
    public function assignUniquePublicToken(Organization $organization): void
    {
        for ($attempt = 0; $attempt < self::TOKEN_RANDOM_ATTEMPTS; ++$attempt) {
            $token = bin2hex(random_bytes(6));
            if ($this->isPublicTokenAvailable($token, $organization->getId())) {
                $organization->setPublicToken($token);

                return;
            }
        }

        throw new \RuntimeException('Impossible de générer un jeton d’organisation unique.');
    }

    public function isPublicTokenAvailable(string $token, ?int $excludeOrganizationId = null): bool
    {
        $qb = $this->createQueryBuilder('o')
            ->select('COUNT(o.id)')
            ->where('o.publicToken = :t')
            ->setParameter('t', $token);

        if ($excludeOrganizationId !== null) {
            $qb->andWhere('o.id != :id')->setParameter('id', $excludeOrganizationId);
        }

        return 0 === (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * @return list<Organization>
     */
    public function findAllOrderedByName(): array
    {
        return $this->createQueryBuilder('o')
            ->orderBy('o.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
