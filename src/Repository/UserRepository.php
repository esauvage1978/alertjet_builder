<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Organization;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @extends ServiceEntityRepository<User>
 *
 * @method User|null find($id, $lockMode = null, $lockVersion = null)
 * @method User|null findOneBy(array $criteria, array $orderBy = null)
 * @method User[]    findAll()
 * @method User[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
final class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    public function createQueryBuilderMembersOfOrganization(Organization $organization): QueryBuilder
    {
        return $this->createQueryBuilder('u')
            ->innerJoin('u.organizations', 'org_membership')
            ->andWhere('org_membership.id = :orgId')
            ->setParameter('orgId', $organization->getId())
            ->orderBy('u.email', 'ASC');
    }

    /**
     * @return list<User>
     */
    public function findMembersFiltered(
        Organization $organization,
        ?string $search,
        ?string $roleKey,
        int $page = 1,
        int $perPage = 15,
    ): array {
        return $this->buildFilteredMembersQb($organization, $search, $roleKey)
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage)
            ->getQuery()
            ->getResult();
    }

    public function countMembersFiltered(
        Organization $organization,
        ?string $search,
        ?string $roleKey,
    ): int {
        return (int) $this->buildFilteredMembersQb($organization, $search, $roleKey)
            ->select('COUNT(u.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    private function buildFilteredMembersQb(
        Organization $organization,
        ?string $search,
        ?string $roleKey,
    ): QueryBuilder {
        $qb = $this->createQueryBuilder('u')
            ->innerJoin('u.organizations', 'org')
            ->andWhere('org.id = :orgId')
            ->setParameter('orgId', $organization->getId())
            ->orderBy('u.email', 'ASC');

        if ($search !== null && trim($search) !== '') {
            $needle = '%'.mb_strtolower(trim($search)).'%';
            $qb->andWhere(
                'LOWER(u.email) LIKE :memberSearch OR LOWER(COALESCE(u.displayName, :memberSearchEmpty)) LIKE :memberSearch',
            )
                ->setParameter('memberSearch', $needle)
                ->setParameter('memberSearchEmpty', '');
        }

        if ($roleKey !== null && $roleKey !== '') {
            switch ($roleKey) {
                case 'admin':
                    $qb->andWhere('u.roles LIKE :rolePattern')->setParameter('rolePattern', '%ROLE_ADMIN%');
                    break;
                case 'manager':
                    $qb->andWhere('u.roles LIKE :rolePattern')->setParameter('rolePattern', '%ROLE_GESTIONNAIRE%');
                    break;
                case 'client':
                    $qb->andWhere('u.roles LIKE :rolePattern')->setParameter('rolePattern', '%ROLE_CLIENT%');
                    break;
                case 'user':
                    $qb->andWhere('u.roles NOT LIKE :rAdmin')->setParameter('rAdmin', '%ROLE_ADMIN%')
                        ->andWhere('u.roles NOT LIKE :rManager')->setParameter('rManager', '%ROLE_GESTIONNAIRE%')
                        ->andWhere('u.roles NOT LIKE :rClient')->setParameter('rClient', '%ROLE_CLIENT%');
                    break;
            }
        }

        return $qb;
    }

    public function findOneByEmailLowercase(string $email): ?User
    {
        $normalized = mb_strtolower(trim($email));
        if ($normalized === '') {
            return null;
        }

        return $this->createQueryBuilder('u')
            ->andWhere('LOWER(u.email) = :email')
            ->setParameter('email', $normalized)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(\sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $user->setPassword($newHashedPassword);
        $this->getEntityManager()->flush();
    }

    public function findOneByEmailVerificationToken(string $token): ?User
    {
        return $this->findOneBy(['emailVerificationToken' => $token]);
    }

    public function findOneByPasswordResetToken(string $token): ?User
    {
        return $this->findOneBy(['passwordResetToken' => $token]);
    }

    public function findOneByOrganizationInviteToken(string $token): ?User
    {
        return $this->findOneBy(['organizationInviteToken' => $token]);
    }

    public function createAdminFilteredQueryBuilder(?int $organizationId, ?string $emailSearch): QueryBuilder
    {
        $qb = $this->createQueryBuilder('u')
            ->distinct()
            ->orderBy('u.email', 'ASC');

        if ($organizationId !== null) {
            $qb->innerJoin('u.organizations', 'org_filter')
                ->andWhere('org_filter.id = :orgId')
                ->setParameter('orgId', $organizationId);
        }

        if ($emailSearch !== null && $emailSearch !== '') {
            $needle = mb_strtolower(trim($emailSearch));
            if ($needle !== '') {
                $qb->andWhere(
                    'LOWER(u.email) LIKE :adminUserSearch OR LOWER(COALESCE(u.displayName, :adminUserEmpty)) LIKE :adminUserSearch',
                )
                    ->setParameter('adminUserSearch', '%'.$needle.'%')
                    ->setParameter('adminUserEmpty', '');
            }
        }

        return $qb;
    }
}
