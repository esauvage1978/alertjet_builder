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
#[UniqueEntity(fields: ['publicToken'], message: 'validation.project_public_token.unique')]
class Project
{
    public const PUBLIC_TOKEN_LENGTH = 12;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /**
     * Jeton opaque dans les URLs (12 caractères hexadécimaux), unique globalement.
     */
    #[ORM\Column(name: 'public_token', length: 12, unique: true)]
    private string $publicToken = '';

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

    /**
     * Afficher l’intégration webhook dans l’UI (l’URL API /api/webhook/{org}/{projet}/{secret} reste valide si désactivé).
     */
    #[ORM\Column(options: ['default' => true])]
    private bool $webhookIntegrationEnabled = true;

    /**
     * Origines autorisées pour les POST navigateur (CORS), une par ligne (https://…).
     * Vide = pas de filtrage sur l’en-tête Origin.
     */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $webhookCorsAllowedOrigins = null;

    /**
     * Intégration "téléphone" (appel entrant / opérateur) - activation UI / règles côté app.
     */
    #[ORM\Column(options: ['default' => false])]
    private bool $phoneIntegrationEnabled = false;

    /**
     * Intégration "formulaire interne" (saisie manuelle dans l'app) - activation UI / règles côté app.
     */
    #[ORM\Column(options: ['default' => false])]
    private bool $internalFormIntegrationEnabled = false;

    /**
     * Horaires d'accueil / astreinte téléphone par jour.
     *
     * Format:
     * {
     *   mon: { enabled: true, morning: { start: "08:00", end: "12:00" }, evening: { start: "14:00", end: "18:00" } },
     *   ...
     * }
     *
     * @var array<string, mixed>|null
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $phoneSchedule = null;

    /** Numéro principal affiché / utilisé pour l’intégration téléphone (obligatoire si l’intégration est activée). */
    #[ORM\Column(length: 48, nullable: true)]
    private ?string $phoneNumber = null;

    /** Numéro ou consigne pour les urgences (optionnel). */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $emergencyPhone = null;

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

    public function isWebhookIntegrationEnabled(): bool
    {
        return $this->webhookIntegrationEnabled;
    }

    public function setWebhookIntegrationEnabled(bool $webhookIntegrationEnabled): self
    {
        $this->webhookIntegrationEnabled = $webhookIntegrationEnabled;

        return $this;
    }

    public function getWebhookCorsAllowedOrigins(): ?string
    {
        return $this->webhookCorsAllowedOrigins;
    }

    public function setWebhookCorsAllowedOrigins(?string $webhookCorsAllowedOrigins): self
    {
        $this->webhookCorsAllowedOrigins = $webhookCorsAllowedOrigins;

        return $this;
    }

    public function isPhoneIntegrationEnabled(): bool
    {
        return $this->phoneIntegrationEnabled;
    }

    public function setPhoneIntegrationEnabled(bool $phoneIntegrationEnabled): self
    {
        $this->phoneIntegrationEnabled = $phoneIntegrationEnabled;

        return $this;
    }

    public function isInternalFormIntegrationEnabled(): bool
    {
        return $this->internalFormIntegrationEnabled;
    }

    public function setInternalFormIntegrationEnabled(bool $internalFormIntegrationEnabled): self
    {
        $this->internalFormIntegrationEnabled = $internalFormIntegrationEnabled;

        return $this;
    }

    /** @return array<string, mixed>|null */
    public function getPhoneSchedule(): ?array
    {
        return $this->phoneSchedule;
    }

    /** @param array<string, mixed>|null $phoneSchedule */
    public function setPhoneSchedule(?array $phoneSchedule): self
    {
        $this->phoneSchedule = $phoneSchedule;

        return $this;
    }

    public function getPhoneNumber(): ?string
    {
        return $this->phoneNumber;
    }

    public function setPhoneNumber(?string $phoneNumber): self
    {
        if ($phoneNumber === null || trim($phoneNumber) === '') {
            $this->phoneNumber = null;
        } else {
            $this->phoneNumber = trim($phoneNumber);
        }

        return $this;
    }

    public function getEmergencyPhone(): ?string
    {
        return $this->emergencyPhone;
    }

    public function setEmergencyPhone(?string $emergencyPhone): self
    {
        $t = $emergencyPhone !== null ? trim($emergencyPhone) : '';
        $this->emergencyPhone = $t !== '' ? $t : null;

        return $this;
    }
}
