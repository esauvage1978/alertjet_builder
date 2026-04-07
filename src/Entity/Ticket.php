<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\TicketPriority;
use App\Enum\TicketStatus;
use App\Repository\TicketRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: TicketRepository::class)]
#[ORM\Table(name: 'tickets')]
#[ORM\Index(name: 'ticket_fingerprint_idx', columns: ['project_id', 'fingerprint', 'status'])]
class Ticket
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $publicId;

    #[ORM\ManyToOne(inversedBy: 'tickets')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Project $project = null;

    #[ORM\Column(length: 255)]
    private string $title;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 20, enumType: TicketStatus::class)]
    private TicketStatus $status = TicketStatus::Open;

    #[ORM\Column(length: 20, enumType: TicketPriority::class)]
    private TicketPriority $priority = TicketPriority::Medium;

    #[ORM\Column(length: 32)]
    private string $source = 'webhook';

    #[ORM\Column(length: 64)]
    private string $fingerprint = '';

    #[ORM\Column(options: ['default' => 1])]
    private int $eventCount = 1;

    #[ORM\Column(options: ['default' => false])]
    private bool $silenced = false;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $resolvedAt = null;

    /** @var Collection<int, TicketLog> */
    #[ORM\OneToMany(targetEntity: TicketLog::class, mappedBy: 'ticket', cascade: ['persist'], orphanRemoval: true)]
    #[ORM\OrderBy(['createdAt' => 'ASC'])]
    private Collection $logs;

    public function __construct()
    {
        $this->publicId = Uuid::v4();
        $this->createdAt = new \DateTimeImmutable();
        $this->logs = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPublicId(): Uuid
    {
        return $this->publicId;
    }

    public function getProject(): ?Project
    {
        return $this->project;
    }

    public function setProject(?Project $project): self
    {
        $this->project = $project;

        return $this;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function getStatus(): TicketStatus
    {
        return $this->status;
    }

    public function setStatus(TicketStatus $status): self
    {
        $this->status = $status;
        if ($status === TicketStatus::Resolved && $this->resolvedAt === null) {
            $this->resolvedAt = new \DateTimeImmutable();
        }
        if ($status !== TicketStatus::Resolved) {
            $this->resolvedAt = null;
        }

        return $this;
    }

    public function getPriority(): TicketPriority
    {
        return $this->priority;
    }

    public function setPriority(TicketPriority $priority): self
    {
        $this->priority = $priority;

        return $this;
    }

    public function getSource(): string
    {
        return $this->source;
    }

    public function setSource(string $source): self
    {
        $this->source = $source;

        return $this;
    }

    public function getFingerprint(): string
    {
        return $this->fingerprint;
    }

    public function setFingerprint(string $fingerprint): self
    {
        $this->fingerprint = $fingerprint;

        return $this;
    }

    public function getEventCount(): int
    {
        return $this->eventCount;
    }

    public function setEventCount(int $eventCount): self
    {
        $this->eventCount = $eventCount;

        return $this;
    }

    public function incrementEventCount(): self
    {
        ++$this->eventCount;

        return $this;
    }

    public function isSilenced(): bool
    {
        return $this->silenced;
    }

    public function setSilenced(bool $silenced): self
    {
        $this->silenced = $silenced;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getResolvedAt(): ?\DateTimeImmutable
    {
        return $this->resolvedAt;
    }

    /** @return Collection<int, TicketLog> */
    public function getLogs(): Collection
    {
        return $this->logs;
    }

    public function addLog(TicketLog $log): self
    {
        if (!$this->logs->contains($log)) {
            $this->logs->add($log);
            $log->setTicket($this);
        }

        return $this;
    }
}
