<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ProjectRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

#[ORM\Entity(repositoryClass: ProjectRepository::class)]
#[ORM\Table(
    name: 'projects',
    uniqueConstraints: [
        new ORM\UniqueConstraint(name: 'uniq_project_organization_name', fields: ['organization', 'name']),
    ],
)]
#[UniqueEntity(
    fields: ['organization', 'name'],
    message: 'validation.project_name.unique_in_org',
    errorPath: 'name',
)]
class Project
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    private string $name;

    #[ORM\Column(length: 64, unique: true)]
    private string $webhookToken;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\ManyToOne(inversedBy: 'projects')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Organization $organization = null;

    /** @var Collection<int, Ticket> */
    #[ORM\OneToMany(targetEntity: Ticket::class, mappedBy: 'project', orphanRemoval: true)]
    private Collection $tickets;

    /**
     * Membres de l’organisation affectés au traitement des tickets sur ce projet.
     *
     * @var Collection<int, User>
     */
    #[ORM\ManyToMany(targetEntity: User::class)]
    #[ORM\JoinTable(name: 'project_ticket_handler')]
    private Collection $ticketHandlers;

    #[ORM\Column(options: ['default' => false])]
    private bool $imapEnabled = false;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $imapHost = null;

    #[ORM\Column(options: ['default' => 993])]
    private int $imapPort = 993;

    #[ORM\Column(options: ['default' => true])]
    private bool $imapTls = true;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $imapUsername = null;

    /** Mot de passe chiffré (AES-256-GCM). */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $imapPasswordCipher = null;

    #[ORM\Column(length: 128, options: ['default' => 'INBOX'])]
    private string $imapMailbox = 'INBOX';

    /** Objectif prise en charge (minutes), affichage indicateurs. */
    #[ORM\Column(nullable: true)]
    private ?int $slaAckTargetMinutes = null;

    /** Objectif résolution (minutes). */
    #[ORM\Column(nullable: true)]
    private ?int $slaResolveTargetMinutes = null;

    public function __construct()
    {
        $this->tickets = new ArrayCollection();
        $this->ticketHandlers = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getWebhookToken(): string
    {
        return $this->webhookToken;
    }

    public function setWebhookToken(string $webhookToken): self
    {
        $this->webhookToken = $webhookToken;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
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

    /** @return Collection<int, Ticket> */
    public function getTickets(): Collection
    {
        return $this->tickets;
    }

    public function addTicket(Ticket $ticket): self
    {
        if (!$this->tickets->contains($ticket)) {
            $this->tickets->add($ticket);
            $ticket->setProject($this);
        }

        return $this;
    }

    public function removeTicket(Ticket $ticket): self
    {
        $this->tickets->removeElement($ticket);

        return $this;
    }

    /** @return Collection<int, User> */
    public function getTicketHandlers(): Collection
    {
        return $this->ticketHandlers;
    }

    public function addTicketHandler(User $user): self
    {
        if (!$this->ticketHandlers->contains($user)) {
            $this->ticketHandlers->add($user);
        }

        return $this;
    }

    public function removeTicketHandler(User $user): self
    {
        $this->ticketHandlers->removeElement($user);

        return $this;
    }

    public function clearTicketHandlers(): self
    {
        $this->ticketHandlers->clear();

        return $this;
    }

    public function isImapEnabled(): bool
    {
        return $this->imapEnabled;
    }

    public function setImapEnabled(bool $imapEnabled): self
    {
        $this->imapEnabled = $imapEnabled;

        return $this;
    }

    public function getImapHost(): ?string
    {
        return $this->imapHost;
    }

    public function setImapHost(?string $imapHost): self
    {
        $this->imapHost = $imapHost;

        return $this;
    }

    public function getImapPort(): int
    {
        return $this->imapPort;
    }

    public function setImapPort(int $imapPort): self
    {
        $this->imapPort = $imapPort;

        return $this;
    }

    public function isImapTls(): bool
    {
        return $this->imapTls;
    }

    public function setImapTls(bool $imapTls): self
    {
        $this->imapTls = $imapTls;

        return $this;
    }

    public function getImapUsername(): ?string
    {
        return $this->imapUsername;
    }

    public function setImapUsername(?string $imapUsername): self
    {
        $this->imapUsername = $imapUsername;

        return $this;
    }

    public function getImapPasswordCipher(): ?string
    {
        return $this->imapPasswordCipher;
    }

    public function setImapPasswordCipher(?string $imapPasswordCipher): self
    {
        $this->imapPasswordCipher = $imapPasswordCipher;

        return $this;
    }

    public function hasStoredImapPassword(): bool
    {
        return $this->imapPasswordCipher !== null && $this->imapPasswordCipher !== '';
    }

    public function getImapMailbox(): string
    {
        return $this->imapMailbox;
    }

    public function setImapMailbox(string $imapMailbox): self
    {
        $this->imapMailbox = $imapMailbox;

        return $this;
    }

    public function getSlaAckTargetMinutes(): ?int
    {
        return $this->slaAckTargetMinutes;
    }

    public function setSlaAckTargetMinutes(?int $slaAckTargetMinutes): self
    {
        $this->slaAckTargetMinutes = $slaAckTargetMinutes;

        return $this;
    }

    public function getSlaResolveTargetMinutes(): ?int
    {
        return $this->slaResolveTargetMinutes;
    }

    public function setSlaResolveTargetMinutes(?int $slaResolveTargetMinutes): self
    {
        $this->slaResolveTargetMinutes = $slaResolveTargetMinutes;

        return $this;
    }
}
