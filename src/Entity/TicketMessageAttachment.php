<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\TicketMessageAttachmentRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TicketMessageAttachmentRepository::class)]
#[ORM\Table(name: 'ticket_message_attachments')]
#[ORM\Index(name: 'tma_message_idx', columns: ['message_id', 'created_at'])]
class TicketMessageAttachment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'attachments')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?TicketMessage $message = null;

    #[ORM\Column(length: 255)]
    private string $filePath = '';

    #[ORM\Column(length: 255)]
    private string $fileName = '';

    #[ORM\Column(length: 128, nullable: true)]
    private ?string $mimeType = null;

    #[ORM\Column(options: ['default' => 0])]
    private int $sizeBytes = 0;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getMessage(): ?TicketMessage
    {
        return $this->message;
    }

    public function setMessage(?TicketMessage $message): self
    {
        $this->message = $message;

        return $this;
    }

    public function getFilePath(): string
    {
        return $this->filePath;
    }

    public function setFilePath(string $filePath): self
    {
        $this->filePath = $filePath;

        return $this;
    }

    public function getFileName(): string
    {
        return $this->fileName;
    }

    public function setFileName(string $fileName): self
    {
        $this->fileName = $fileName;

        return $this;
    }

    public function getMimeType(): ?string
    {
        return $this->mimeType;
    }

    public function setMimeType(?string $mimeType): self
    {
        $t = $mimeType !== null ? trim($mimeType) : '';
        $this->mimeType = $t !== '' ? $t : null;

        return $this;
    }

    public function getSizeBytes(): int
    {
        return $this->sizeBytes;
    }

    public function setSizeBytes(int $sizeBytes): self
    {
        $this->sizeBytes = max(0, $sizeBytes);

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}

