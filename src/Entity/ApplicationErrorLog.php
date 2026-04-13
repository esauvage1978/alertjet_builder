<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ApplicationErrorLogRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ApplicationErrorLogRepository::class)]
#[ORM\Table(name: 'application_error_logs')]
#[ORM\Index(name: 'app_error_log_created_idx', columns: ['created_at'])]
#[ORM\Index(name: 'app_error_log_class_idx', columns: ['exception_class', 'created_at'])]
class ApplicationErrorLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    /** @see self::MAX_EXCEPTION_CLASS_LEN Limite MySQL utf8mb4 + index composite (exception_class, created_at). */
    public const MAX_EXCEPTION_CLASS_LEN = 191;

    #[ORM\Column(length: self::MAX_EXCEPTION_CLASS_LEN)]
    private string $exceptionClass;

    #[ORM\Column(type: Types::TEXT)]
    private string $message = '';

    #[ORM\Column]
    private int $code = 0;

    #[ORM\Column(length: 512, nullable: true)]
    private ?string $file = null;

    #[ORM\Column(nullable: true)]
    private ?int $line = null;

    /** Stack trace complète (peut être volumineuse). */
    #[ORM\Column(type: Types::TEXT)]
    private string $trace = '';

    /** Chaîne d'exceptions précédentes (résumé structuré). */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $previousChain = null;

    #[ORM\Column(length: 16, nullable: true)]
    private ?string $httpMethod = null;

    #[ORM\Column(length: 2048, nullable: true)]
    private ?string $requestUri = null;

    #[ORM\Column(length: 128, nullable: true)]
    private ?string $route = null;

    #[ORM\Column(nullable: true)]
    private ?int $httpStatus = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $user = null;

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $actorEmail = null;

    #[ORM\Column(length: 45, nullable: true)]
    private ?string $ip = null;

    #[ORM\Column(length: 512, nullable: true)]
    private ?string $userAgent = null;

    /** Contexte métier (ex. source=caught_invite_webhook). */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $context = null;

    #[ORM\Column(length: 32)]
    private string $source = 'kernel';

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getExceptionClass(): string
    {
        return $this->exceptionClass;
    }

    public function setExceptionClass(string $exceptionClass): self
    {
        $this->exceptionClass = $exceptionClass;

        return $this;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function setMessage(string $message): self
    {
        $this->message = $message;

        return $this;
    }

    public function getCode(): int
    {
        return $this->code;
    }

    public function setCode(int $code): self
    {
        $this->code = $code;

        return $this;
    }

    public function getFile(): ?string
    {
        return $this->file;
    }

    public function setFile(?string $file): self
    {
        $this->file = $file;

        return $this;
    }

    public function getLine(): ?int
    {
        return $this->line;
    }

    public function setLine(?int $line): self
    {
        $this->line = $line;

        return $this;
    }

    public function getTrace(): string
    {
        return $this->trace;
    }

    public function setTrace(string $trace): self
    {
        $this->trace = $trace;

        return $this;
    }

    /** @return list<array{class: string, message: string, code: int|string, file: string, line: int}>|null */
    public function getPreviousChain(): ?array
    {
        return $this->previousChain;
    }

    /** @param list<array<string, mixed>>|null $previousChain */
    public function setPreviousChain(?array $previousChain): self
    {
        $this->previousChain = $previousChain;

        return $this;
    }

    public function getHttpMethod(): ?string
    {
        return $this->httpMethod;
    }

    public function setHttpMethod(?string $httpMethod): self
    {
        $this->httpMethod = $httpMethod;

        return $this;
    }

    public function getRequestUri(): ?string
    {
        return $this->requestUri;
    }

    public function setRequestUri(?string $requestUri): self
    {
        $this->requestUri = $requestUri;

        return $this;
    }

    public function getRoute(): ?string
    {
        return $this->route;
    }

    public function setRoute(?string $route): self
    {
        $this->route = $route;

        return $this;
    }

    public function getHttpStatus(): ?int
    {
        return $this->httpStatus;
    }

    public function setHttpStatus(?int $httpStatus): self
    {
        $this->httpStatus = $httpStatus;

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

    public function getActorEmail(): ?string
    {
        return $this->actorEmail;
    }

    public function setActorEmail(?string $actorEmail): self
    {
        $this->actorEmail = $actorEmail;

        return $this;
    }

    public function getIp(): ?string
    {
        return $this->ip;
    }

    public function setIp(?string $ip): self
    {
        $this->ip = $ip;

        return $this;
    }

    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

    public function setUserAgent(?string $userAgent): self
    {
        $this->userAgent = $userAgent;

        return $this;
    }

    /** @return array<string, mixed>|null */
    public function getContext(): ?array
    {
        return $this->context;
    }

    /** @param array<string, mixed>|null $context */
    public function setContext(?array $context): self
    {
        $this->context = $context;

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
}
