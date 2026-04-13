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
    private const TOKEN_RANDOM_ATTEMPTS = 64;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Project::class);
    }

    public function assignUniquePublicToken(Project $project): void
    {
        for ($attempt = 0; $attempt < self::TOKEN_RANDOM_ATTEMPTS; ++$attempt) {
            $token = bin2hex(random_bytes(6));
            if ($this->isPublicTokenAvailable($token, $project->getId())) {
                $project->setPublicToken($token);

                return;
            }
        }

        throw new \RuntimeException('Impossible de générer un jeton public unique pour le projet.');
    }

    public function isPublicTokenAvailable(string $token, ?int $excludeProjectId = null): bool
    {
        $qb = $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->where('p.publicToken = :t')
            ->setParameter('t', $token);

        if ($excludeProjectId !== null) {
            $qb->andWhere('p.id != :id')->setParameter('id', $excludeProjectId);
        }

        return 0 === (int) $qb->getQuery()->getSingleScalarResult();
    }

    public function findByWebhookToken(string $token): ?Project
    {
        return $this->findOneBy(['webhookToken' => $token]);
    }

    /**
     * Résout un projet à partir du triplet URL public (org + projet + secret webhook).
     */
    public function findByWebhookScoped(
        string $organizationPublicToken,
        string $projectPublicToken,
        string $webhookToken,
    ): ?Project {
        return $this->createQueryBuilder('p')
            ->innerJoin('p.organization', 'o')
            ->andWhere('o.publicToken = :orgToken')
            ->andWhere('p.publicToken = :projectToken')
            ->andWhere('p.webhookToken = :webhookToken')
            ->setParameter('orgToken', $organizationPublicToken)
            ->setParameter('projectToken', $projectPublicToken)
            ->setParameter('webhookToken', $webhookToken)
            ->getQuery()
            ->getOneOrNullResult();
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

    public function countByOrganization(Organization $organization): int
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->andWhere('p.organization = :org')
            ->setParameter('org', $organization)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
