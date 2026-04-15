<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ImapFetchRunProjectRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ImapFetchRunProjectRepository::class)]
#[ORM\Table(name: 'imap_fetch_run_projects')]
#[ORM\Index(name: 'imap_fetch_run_projects_run_idx', columns: ['run_id'])]
#[ORM\Index(name: 'imap_fetch_run_projects_org_idx', columns: ['organization_id'])]
#[ORM\Index(name: 'imap_fetch_run_projects_project_idx', columns: ['project_id'])]
class ImapFetchRunProject
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: ImapFetchRun::class, inversedBy: 'projects')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?ImapFetchRun $run = null;

    #[ORM\ManyToOne(targetEntity: Organization::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Organization $organization = null;

    #[ORM\Column(name: 'organization_name', length: 180)]
    private string $organizationName = '';

    #[ORM\ManyToOne(targetEntity: Project::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Project $project = null;

    #[ORM\Column(name: 'project_name', length: 180)]
    private string $projectName = '';

    #[ORM\Column(length: 255)]
    private string $imapHost = '';

    #[ORM\Column]
    private int $imapPort = 0;

    #[ORM\Column(options: ['default' => true])]
    private bool $imapTls = true;

    #[ORM\Column(length: 128)]
    private string $imapMailbox = 'INBOX';

    #[ORM\Column(name: 'unseen_count', options: ['default' => 0])]
    private int $unseenCount = 0;

    #[ORM\Column(name: 'tickets_created', options: ['default' => 0])]
    private int $ticketsCreated = 0;

    #[ORM\Column(name: 'failure_count', options: ['default' => 0])]
    private int $failureCount = 0;

    #[ORM\Column(name: 'connection_error', type: Types::TEXT, nullable: true)]
    private ?string $connectionError = null;

    #[ORM\Column(name: 'mailbox_error', type: Types::TEXT, nullable: true)]
    private ?string $mailboxError = null;

    #[ORM\Column(name: 'failures_json', type: Types::JSON, nullable: true)]
    private ?array $failuresJson = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRun(): ?ImapFetchRun
    {
        return $this->run;
    }

    public function setRun(?ImapFetchRun $run): self
    {
        $this->run = $run;

        return $this;
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

    public function getOrganizationName(): string
    {
        return $this->organizationName;
    }

    public function setOrganizationName(string $organizationName): self
    {
        $this->organizationName = $organizationName;

        return $this;
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

    public function getProjectName(): string
    {
        return $this->projectName;
    }

    public function setProjectName(string $projectName): self
    {
        $this->projectName = $projectName;

        return $this;
    }

    public function getImapHost(): string
    {
        return $this->imapHost;
    }

    public function setImapHost(string $imapHost): self
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

    public function getImapMailbox(): string
    {
        return $this->imapMailbox;
    }

    public function setImapMailbox(string $imapMailbox): self
    {
        $this->imapMailbox = $imapMailbox;

        return $this;
    }

    public function getUnseenCount(): int
    {
        return $this->unseenCount;
    }

    public function setUnseenCount(int $unseenCount): self
    {
        $this->unseenCount = $unseenCount;

        return $this;
    }

    public function getTicketsCreated(): int
    {
        return $this->ticketsCreated;
    }

    public function setTicketsCreated(int $ticketsCreated): self
    {
        $this->ticketsCreated = $ticketsCreated;

        return $this;
    }

    public function getFailureCount(): int
    {
        return $this->failureCount;
    }

    public function setFailureCount(int $failureCount): self
    {
        $this->failureCount = $failureCount;

        return $this;
    }

    public function getConnectionError(): ?string
    {
        return $this->connectionError;
    }

    public function setConnectionError(?string $connectionError): self
    {
        $this->connectionError = $connectionError;

        return $this;
    }

    public function getMailboxError(): ?string
    {
        return $this->mailboxError;
    }

    public function setMailboxError(?string $mailboxError): self
    {
        $this->mailboxError = $mailboxError;

        return $this;
    }

    public function getFailuresJson(): ?array
    {
        return $this->failuresJson;
    }

    public function setFailuresJson(?array $failuresJson): self
    {
        $this->failuresJson = $failuresJson;

        return $this;
    }
}

