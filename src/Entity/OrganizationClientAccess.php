<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\OrganizationClientAccessRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Accès « portail client » pour une organisation (utilisateurs au rôle Client autorisés explicitement).
 */
#[ORM\Entity(repositoryClass: OrganizationClientAccessRepository::class)]
#[ORM\Table(name: 'organization_client_access')]
#[ORM\UniqueConstraint(name: 'uniq_organization_client_access_org_user', columns: ['organization_id', 'user_id'])]
class OrganizationClientAccess
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Organization $organization = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOrganization(): ?Organization
    {
        return $this->organization;
    }

    public function setOrganization(?Organization $organization): self
    {
        $this->organization = $organization;

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): self
    {
        $this->user = $user;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
