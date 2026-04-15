<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ImapFetchRunRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ImapFetchRunRepository::class)]
#[ORM\Table(name: 'imap_fetch_runs')]
#[ORM\Index(name: 'imap_fetch_runs_started_at_idx', columns: ['started_at'])]
class ImapFetchRun
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(name: 'started_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $startedAt;

    #[ORM\Column(name: 'finished_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $finishedAt = null;

    #[ORM\Column(name: 'duration_ms', options: ['default' => 0])]
    private int $durationMs = 0;

    #[ORM\Column(name: 'project_filter_id', nullable: true)]
    private ?int $projectFilterId = null;

    #[ORM\Column(name: 'retention_days', options: ['default' => 30])]
    private int $retentionDays = 30;

    #[ORM\Column(name: 'total_organizations', options: ['default' => 0])]
    private int $totalOrganizations = 0;

    #[ORM\Column(name: 'total_projects', options: ['default' => 0])]
    private int $totalProjects = 0;

    #[ORM\Column(name: 'total_unseen', options: ['default' => 0])]
    private int $totalUnseen = 0;

    #[ORM\Column(name: 'total_tickets', options: ['default' => 0])]
    private int $totalTickets = 0;

    #[ORM\Column(name: 'total_failures', options: ['default' => 0])]
    private int $totalFailures = 0;

    /** @var Collection<int, ImapFetchRunProject> */
    #[ORM\OneToMany(targetEntity: ImapFetchRunProject::class, mappedBy: 'run', cascade: ['persist'], orphanRemoval: true)]
    private Collection $projects;

    public function __construct()
    {
        $this->startedAt = new \DateTimeImmutable();
        $this->projects = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getStartedAt(): \DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function getFinishedAt(): ?\DateTimeImmutable
    {
        return $this->finishedAt;
    }

    public function setFinishedAt(?\DateTimeImmutable $finishedAt): self
    {
        $this->finishedAt = $finishedAt;

        return $this;
    }

    public function getDurationMs(): int
    {
        return $this->durationMs;
    }

    public function setDurationMs(int $durationMs): self
    {
        $this->durationMs = $durationMs;

        return $this;
    }

    public function getProjectFilterId(): ?int
    {
        return $this->projectFilterId;
    }

    public function setProjectFilterId(?int $projectFilterId): self
    {
        $this->projectFilterId = $projectFilterId;

        return $this;
    }

    public function getRetentionDays(): int
    {
        return $this->retentionDays;
    }

    public function setRetentionDays(int $retentionDays): self
    {
        $this->retentionDays = $retentionDays;

        return $this;
    }

    public function getTotalOrganizations(): int
    {
        return $this->totalOrganizations;
    }

    public function setTotalOrganizations(int $totalOrganizations): self
    {
        $this->totalOrganizations = $totalOrganizations;

        return $this;
    }

    public function getTotalProjects(): int
    {
        return $this->totalProjects;
    }

    public function setTotalProjects(int $totalProjects): self
    {
        $this->totalProjects = $totalProjects;

        return $this;
    }

    public function getTotalUnseen(): int
    {
        return $this->totalUnseen;
    }

    public function setTotalUnseen(int $totalUnseen): self
    {
        $this->totalUnseen = $totalUnseen;

        return $this;
    }

    public function getTotalTickets(): int
    {
        return $this->totalTickets;
    }

    public function setTotalTickets(int $totalTickets): self
    {
        $this->totalTickets = $totalTickets;

        return $this;
    }

    public function getTotalFailures(): int
    {
        return $this->totalFailures;
    }

    public function setTotalFailures(int $totalFailures): self
    {
        $this->totalFailures = $totalFailures;

        return $this;
    }

    /** @return Collection<int, ImapFetchRunProject> */
    public function getProjects(): Collection
    {
        return $this->projects;
    }

    public function addProject(ImapFetchRunProject $p): self
    {
        if (!$this->projects->contains($p)) {
            $this->projects->add($p);
            $p->setRun($this);
        }

        return $this;
    }
}

