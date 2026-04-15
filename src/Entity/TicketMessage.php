<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\TicketMessageRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\TicketEmailMeta;
use App\Entity\TicketMessageAttachment;

#[ORM\Entity(repositoryClass: TicketMessageRepository::class)]
#[ORM\Table(name: 'ticket_messages')]
#[ORM\Index(name: 'ticket_message_ticket_idx', columns: ['ticket_id', 'created_at'])]
#[ORM\Index(name: 'ticket_message_message_id_idx', columns: ['message_id'])]
class TicketMessage
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'messages')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Ticket $ticket = null;

    /**
     * client | agent
     */
    #[ORM\Column(length: 12)]
    private string $senderType = 'client';

    #[ORM\Column(length: 255)]
    private string $senderEmail = '';

    #[ORM\Column(type: Types::TEXT)]
    private string $content = '';

    /**
     * RFC 5322 Message-ID (unique)
     */
    #[ORM\Column(length: 191, unique: true)]
    private string $messageId = '';

    #[ORM\Column(length: 191, nullable: true)]
    private ?string $inReplyTo = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\OneToOne(mappedBy: 'message', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private ?TicketEmailMeta $emailMeta = null;

    /** @var Collection<int, TicketMessageAttachment> */
    #[ORM\OneToMany(targetEntity: TicketMessageAttachment::class, mappedBy: 'message', cascade: ['persist'], orphanRemoval: true)]
    #[ORM\OrderBy(['createdAt' => 'ASC'])]
    private Collection $attachments;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->attachments = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTicket(): ?Ticket
    {
        return $this->ticket;
    }

    public function setTicket(?Ticket $ticket): self
    {
        $this->ticket = $ticket;

        return $this;
    }

    public function getSenderType(): string
    {
        return $this->senderType;
    }

    public function setSenderType(string $senderType): self
    {
        $senderType = trim($senderType);
        if (!\in_array($senderType, ['client', 'agent'], true)) {
            throw new \InvalidArgumentException('senderType invalide.');
        }
        $this->senderType = $senderType;

        return $this;
    }

    public function getSenderEmail(): string
    {
        return $this->senderEmail;
    }

    public function setSenderEmail(string $senderEmail): self
    {
        $this->senderEmail = mb_strtolower(trim($senderEmail));

        return $this;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function setContent(string $content): self
    {
        $this->content = $content;

        return $this;
    }

    public function getMessageId(): string
    {
        return $this->messageId;
    }

    public function setMessageId(string $messageId): self
    {
        $this->messageId = trim($messageId);

        return $this;
    }

    public function getInReplyTo(): ?string
    {
        return $this->inReplyTo;
    }

    public function setInReplyTo(?string $inReplyTo): self
    {
        $t = $inReplyTo !== null ? trim($inReplyTo) : '';
        $this->inReplyTo = $t !== '' ? $t : null;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getEmailMeta(): ?TicketEmailMeta
    {
        return $this->emailMeta;
    }

    public function setEmailMeta(?TicketEmailMeta $emailMeta): self
    {
        $this->emailMeta = $emailMeta;
        if ($emailMeta !== null && $emailMeta->getMessage() !== $this) {
            $emailMeta->setMessage($this);
        }

        return $this;
    }

    /** @return Collection<int, TicketMessageAttachment> */
    public function getAttachments(): Collection
    {
        return $this->attachments;
    }

    public function addAttachment(TicketMessageAttachment $attachment): self
    {
        if (!$this->attachments->contains($attachment)) {
            $this->attachments->add($attachment);
            $attachment->setMessage($this);
        }

        return $this;
    }
}

