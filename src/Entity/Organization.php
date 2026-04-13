<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\OrganizationPlan;
use App\Repository\OrganizationRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

#[ORM\Entity(repositoryClass: OrganizationRepository::class)]
#[ORM\Table(name: 'organizations')]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: ['name'], message: 'Une organisation porte déjà ce nom.')]
#[UniqueEntity(fields: ['publicToken'], message: 'Jeton d’organisation déjà utilisé.')]
class Organization
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    public const PUBLIC_TOKEN_LENGTH = 12;

    /**
     * Identifiant opaque dans les URLs (pas l’ID numérique interne), hexadécimal sur 12 caractères.
     */
    #[ORM\Column(name: 'public_token', length: 12, unique: true)]
    private string $publicToken = '';

    #[ORM\Column(length: 180, unique: true)]
    private string $name = '';

    /** Adresse de facturation — ligne 1 (rue, numéro, etc.) */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $billingLine1 = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $billingLine2 = null;

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $billingPostalCode = null;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $billingCity = null;

    /** Code pays ISO 3166-1 alpha-2 (ex. FR, BE). */
    #[ORM\Column(length: 2, nullable: true)]
    private ?string $billingCountry = null;

    #[ORM\Column(length: 32, nullable: true, enumType: OrganizationPlan::class)]
    private ?OrganizationPlan $plan = null;

    /** Compte interne : pas d’étape « plan » vitrine ni contrainte liée aux offres. */
    #[ORM\Column(options: ['default' => false])]
    private bool $planExempt = false;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    /** @var Collection<int, User> */
    #[ORM\ManyToMany(targetEntity: User::class, mappedBy: 'organizations')]
    private Collection $users;

    /** @var Collection<int, Project> */
    #[ORM\OneToMany(targetEntity: Project::class, mappedBy: 'organization')]
    private Collection $projects;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->users = new ArrayCollection();
        $this->projects = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPublicToken(): string
    {
        return $this->publicToken;
    }

    public function setPublicToken(string $publicToken): self
    {
        $publicToken = strtolower($publicToken);
        if (\strlen($publicToken) !== self::PUBLIC_TOKEN_LENGTH) {
            throw new \InvalidArgumentException(\sprintf('Le jeton public doit comporter exactement %d caractères.', self::PUBLIC_TOKEN_LENGTH));
        }
        if (!preg_match('/^[a-f0-9]{12}$/', $publicToken)) {
            throw new \InvalidArgumentException('Le jeton public doit être hexadécimal en minuscules.');
        }
        $this->publicToken = $publicToken;

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = trim($name);

        return $this;
    }

    public function getBillingLine1(): ?string
    {
        return $this->billingLine1;
    }

    public function setBillingLine1(?string $billingLine1): self
    {
        $this->billingLine1 = $billingLine1 !== null && $billingLine1 !== '' ? trim($billingLine1) : null;

        return $this;
    }

    public function getBillingLine2(): ?string
    {
        return $this->billingLine2;
    }

    public function setBillingLine2(?string $billingLine2): self
    {
        $this->billingLine2 = $billingLine2 !== null && $billingLine2 !== '' ? trim($billingLine2) : null;

        return $this;
    }

    public function getBillingPostalCode(): ?string
    {
        return $this->billingPostalCode;
    }

    public function setBillingPostalCode(?string $billingPostalCode): self
    {
        $this->billingPostalCode = $billingPostalCode !== null && $billingPostalCode !== '' ? trim($billingPostalCode) : null;

        return $this;
    }

    public function getBillingCity(): ?string
    {
        return $this->billingCity;
    }

    public function setBillingCity(?string $billingCity): self
    {
        $this->billingCity = $billingCity !== null && $billingCity !== '' ? trim($billingCity) : null;

        return $this;
    }

    public function getBillingCountry(): ?string
    {
        return $this->billingCountry;
    }

    public function setBillingCountry(?string $billingCountry): self
    {
        if ($billingCountry === null || $billingCountry === '') {
            $this->billingCountry = null;
        } else {
            $this->billingCountry = strtoupper(trim($billingCountry));
        }

        return $this;
    }

    public function getPlan(): ?OrganizationPlan
    {
        return $this->plan;
    }

    public function setPlan(?OrganizationPlan $plan): self
    {
        $this->plan = $plan;

        return $this;
    }

    public function isPlanExempt(): bool
    {
        return $this->planExempt;
    }

    public function setPlanExempt(bool $planExempt): self
    {
        $this->planExempt = $planExempt;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /** @return Collection<int, User> */
    public function getUsers(): Collection
    {
        return $this->users;
    }

    public function addUser(User $user): self
    {
        if (!$this->users->contains($user)) {
            $this->users->add($user);
            $user->getOrganizations()->add($this);
        }

        return $this;
    }

    public function removeUser(User $user): self
    {
        if ($this->users->removeElement($user)) {
            $user->getOrganizations()->removeElement($this);
        }

        return $this;
    }

    /** @return Collection<int, Project> */
    public function getProjects(): Collection
    {
        return $this->projects;
    }

    public function addProject(Project $project): self
    {
        if (!$this->projects->contains($project)) {
            $this->projects->add($project);
            $project->setOrganization($this);
        }

        return $this;
    }

    public function removeProject(Project $project): self
    {
        if ($this->projects->removeElement($project)) {
            if ($project->getOrganization() === $this) {
                $project->setOrganization(null);
            }
        }

        return $this;
    }
}
