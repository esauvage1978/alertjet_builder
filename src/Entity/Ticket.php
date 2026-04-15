<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\TicketPriority;
use App\Enum\TicketSource;
use App\Enum\TicketStatus;
use App\Enum\TicketType;
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

    /**
     * Contact externe (émetteur e-mail) rattaché à l’organisation — créé ou réutilisé à l’import IMAP.
     */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'organization_contact_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?OrganizationContact $organizationContact = null;

    /**
     * Identifiant Message-ID du message e-mail ayant créé le ticket (référence RFC 5322).
     */
    #[ORM\Column(name: 'incoming_email_message_id', length: 255, nullable: true)]
    private ?string $incomingEmailMessageId = null;

    /**
     * Membre du projet (gestionnaire de tickets) responsable du traitement.
     */
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'assignee_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?User $assignee = null;

    #[ORM\Column(length: 255)]
    private string $title;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 20, enumType: TicketStatus::class)]
    private TicketStatus $status = TicketStatus::New;

    #[ORM\Column(length: 20, enumType: TicketPriority::class)]
    private TicketPriority $priority = TicketPriority::Medium;

    #[ORM\Column(length: 20, enumType: TicketType::class)]
    private TicketType $type = TicketType::Incident;

    #[ORM\Column(length: 32, enumType: TicketSource::class)]
    private TicketSource $source = TicketSource::Webhook;

    #[ORM\Column(length: 64)]
    private string $fingerprint = '';

    #[ORM\Column(options: ['default' => 1])]
    private int $eventCount = 1;

    #[ORM\Column(options: ['default' => false])]
    private bool $silenced = false;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $acknowledgedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $closedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $resolvedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $cancelledAt = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $onHoldReason = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $cancelReason = null;

    /** @var Collection<int, TicketLog> */
    #[ORM\OneToMany(targetEntity: TicketLog::class, mappedBy: 'ticket', cascade: ['persist'], orphanRemoval: true)]
    #[ORM\OrderBy(['createdAt' => 'ASC'])]
    private Collection $logs;

    /** @var Collection<int, TicketAttachment> */
    #[ORM\OneToMany(targetEntity: TicketAttachment::class, mappedBy: 'ticket', cascade: ['persist'], orphanRemoval: true)]
    #[ORM\OrderBy(['createdAt' => 'ASC'])]
    private Collection $attachments;

    /** @var Collection<int, TicketMessage> */
    #[ORM\OneToMany(targetEntity: TicketMessage::class, mappedBy: 'ticket', cascade: ['persist'], orphanRemoval: true)]
    #[ORM\OrderBy(['createdAt' => 'ASC'])]
    private Collection $messages;

    public function __construct()
    {
        $this->publicId = Uuid::v4();
        $this->createdAt = new \DateTimeImmutable();
        $this->logs = new ArrayCollection();
        $this->attachments = new ArrayCollection();
        $this->messages = new ArrayCollection();
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

    public function getOrganizationContact(): ?OrganizationContact
    {
        return $this->organizationContact;
    }

    public function setOrganizationContact(?OrganizationContact $organizationContact): self
    {
        $this->organizationContact = $organizationContact;

        return $this;
    }

    public function getIncomingEmailMessageId(): ?string
    {
        return $this->incomingEmailMessageId;
    }

    public function setIncomingEmailMessageId(?string $incomingEmailMessageId): self
    {
        $this->incomingEmailMessageId = $incomingEmailMessageId;

        return $this;
    }

    public function getAssignee(): ?User
    {
        return $this->assignee;
    }

    public function setAssignee(?User $assignee): self
    {
        $this->assignee = $assignee;

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
        $now = new \DateTimeImmutable();

        if ($status === TicketStatus::Acknowledged && $this->acknowledgedAt === null) {
            $this->acknowledgedAt = $now;
        }

        if ($status === TicketStatus::Resolved && $this->resolvedAt === null) {
            $this->resolvedAt = $now;
        }
        if ($status !== TicketStatus::Resolved) {
            $this->resolvedAt = null;
        }

        if ($status === TicketStatus::Closed && $this->closedAt === null) {
            $this->closedAt = $now;
        }
        if ($status !== TicketStatus::Closed) {
            $this->closedAt = null;
        }

        if ($status === TicketStatus::Cancelled && $this->cancelledAt === null) {
            $this->cancelledAt = $now;
        }
        if ($status !== TicketStatus::Cancelled) {
            $this->cancelledAt = null;
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

    public function getType(): TicketType
    {
        return $this->type;
    }

    public function setType(TicketType $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function getSource(): TicketSource
    {
        return $this->source;
    }

    public function setSource(TicketSource $source): self
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

    public function getAcknowledgedAt(): ?\DateTimeImmutable
    {
        return $this->acknowledgedAt;
    }

    public function getResolvedAt(): ?\DateTimeImmutable
    {
        return $this->resolvedAt;
    }

    public function getClosedAt(): ?\DateTimeImmutable
    {
        return $this->closedAt;
    }

    public function getCancelledAt(): ?\DateTimeImmutable
    {
        return $this->cancelledAt;
    }

    public function getOnHoldReason(): ?string
    {
        return $this->onHoldReason;
    }

    public function setOnHoldReason(?string $onHoldReason): self
    {
        $this->onHoldReason = $onHoldReason;

        return $this;
    }

    public function getCancelReason(): ?string
    {
        return $this->cancelReason;
    }

    public function setCancelReason(?string $cancelReason): self
    {
        $this->cancelReason = $cancelReason;

        return $this;
    }

    /** @return Collection<int, TicketAttachment> */
    public function getAttachments(): Collection
    {
        return $this->attachments;
    }

    public function addAttachment(TicketAttachment $attachment): self
    {
        if (!$this->attachments->contains($attachment)) {
            $this->attachments->add($attachment);
            $attachment->setTicket($this);
        }

        return $this;
    }

    public function removeAttachment(TicketAttachment $attachment): self
    {
        $this->attachments->removeElement($attachment);

        return $this;
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

    /** @return Collection<int, TicketMessage> */
    public function getMessages(): Collection
    {
        return $this->messages;
    }

    public function addMessage(TicketMessage $message): self
    {
        if (!$this->messages->contains($message)) {
            $this->messages->add($message);
            $message->setTicket($this);
        }

        return $this;
    }
}
