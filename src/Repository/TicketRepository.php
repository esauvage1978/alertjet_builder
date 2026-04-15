<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Organization;
use App\Entity\Project;
use App\Entity\Ticket;
use App\Enum\TicketPriority;
use App\Enum\TicketSource;
use App\Enum\TicketStatus;
use App\Enum\TicketType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
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

    public function findWithAttachments(int $id): ?Ticket
    {
        return $this->createQueryBuilder('t')
            ->leftJoin('t.attachments', 'a')->addSelect('a')
            ->leftJoin('t.assignee', 'asg')->addSelect('asg')
            ->leftJoin('t.organizationContact', 'oc')->addSelect('oc')
            ->where('t.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
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
                [TicketStatus::Open, TicketStatus::New, TicketStatus::Acknowledged, TicketStatus::InProgress, TicketStatus::OnHold],
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
            ->leftJoin('t.attachments', 'att')->addSelect('att')
            ->leftJoin('t.assignee', 'asg')->addSelect('asg')
            ->leftJoin('t.organizationContact', 'oc')->addSelect('oc')
            ->where('p.organization = :org')
            ->setParameter('org', $organization)
            ->orderBy('t.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Liste paginée avec filtres (sans pièces jointes).
     *
     * @return array{items: list<Ticket>, total: int}
     */
    public function paginateForOrganization(
        Organization $organization,
        ?string $search,
        ?TicketStatus $status,
        ?TicketPriority $priority,
        ?TicketSource $source,
        ?TicketType $type,
        ?int $projectId,
        ?string $assigneeFilter,
        int $currentUserId,
        int $page,
        int $perPage,
    ): array {
        $qb = $this->createQueryBuilder('t')
            ->innerJoin('t.project', 'p')->addSelect('p')
            ->leftJoin('t.assignee', 'asg')->addSelect('asg')
            ->leftJoin('t.organizationContact', 'oc')->addSelect('oc')
            ->where('p.organization = :org')
            ->setParameter('org', $organization);

        $this->applyOrganizationTicketFilters($qb, $search, $status, $priority, $source, $type, $projectId, $assigneeFilter, $currentUserId);

        $countQb = clone $qb;
        $total = (int) $countQb
            ->select('COUNT(DISTINCT t.id)')
            ->resetDQLPart('orderBy')
            ->getQuery()
            ->getSingleScalarResult();

        $qb->select('t')->addSelect('p')->addSelect('asg')->addSelect('oc')
            ->orderBy('t.createdAt', 'DESC')
            ->setFirstResult(max(0, ($page - 1) * $perPage))
            ->setMaxResults($perPage);

        /** @var list<Ticket> $items */
        $items = $qb->getQuery()->getResult();

        return ['items' => $items, 'total' => $total];
    }

    private function applyOrganizationTicketFilters(
        QueryBuilder $qb,
        ?string $search,
        ?TicketStatus $status,
        ?TicketPriority $priority,
        ?TicketSource $source,
        ?TicketType $type,
        ?int $projectId,
        ?string $assigneeFilter,
        int $currentUserId,
    ): void {
        if ($search !== null && trim($search) !== '') {
            $q = '%'.$this->escapeLike(trim($search)).'%';
            $qb->andWhere('(t.title LIKE :ticketSearch OR t.description LIKE :ticketSearch OR t.onHoldReason LIKE :ticketSearch OR t.cancelReason LIKE :ticketSearch OR t.incomingEmailMessageId LIKE :ticketSearch OR oc.email LIKE :ticketSearch OR oc.displayName LIKE :ticketSearch)')
                ->setParameter('ticketSearch', $q);
        }

        if ($status !== null) {
            $qb->andWhere('t.status = :ticketStatus')->setParameter('ticketStatus', $status);
        }

        if ($priority !== null) {
            $qb->andWhere('t.priority = :ticketPriority')->setParameter('ticketPriority', $priority);
        }

        if ($source !== null) {
            $qb->andWhere('t.source = :ticketSource')->setParameter('ticketSource', $source);
        }

        if ($type !== null) {
            $qb->andWhere('t.type = :ticketType')->setParameter('ticketType', $type);
        }

        if ($projectId !== null) {
            $qb->andWhere('p.id = :filterProjectId')->setParameter('filterProjectId', $projectId);
        }

        if ($assigneeFilter !== null && $assigneeFilter !== '') {
            if ($assigneeFilter === 'unassigned') {
                $qb->andWhere('t.assignee IS NULL');
            } elseif ($assigneeFilter === 'me') {
                $qb->andWhere('asg.id = :filterAssigneeMe')->setParameter('filterAssigneeMe', $currentUserId);
            } elseif (ctype_digit($assigneeFilter)) {
                $qb->andWhere('asg.id = :filterAssigneeUser')->setParameter('filterAssigneeUser', (int) $assigneeFilter);
            }
        }
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['%', '_', '\\'], ['\\%', '\\_', '\\\\'], $value);
    }

    /**
     * Identifiants des utilisateurs actuellement assignés à au moins un ticket de l’organisation.
     *
     * @return list<int>
     */
    public function assigneeUserIdsInOrganization(Organization $organization): array
    {
        $rows = $this->createQueryBuilder('t')
            ->select('DISTINCT u.id AS uid')
            ->innerJoin('t.project', 'p')
            ->innerJoin('t.assignee', 'u')
            ->where('p.organization = :org')
            ->setParameter('org', $organization)
            ->getQuery()
            ->getScalarResult();

        $ids = [];
        foreach ($rows as $row) {
            $id = isset($row['uid']) ? (int) $row['uid'] : 0;
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }
}
