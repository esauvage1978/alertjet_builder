<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\TicketEmailMetaRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TicketEmailMetaRepository::class)]
#[ORM\Table(name: 'ticket_email_meta')]
class TicketEmailMeta
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(inversedBy: 'emailMeta')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE', unique: true)]
    private ?TicketMessage $message = null;

    #[ORM\Column(type: Types::TEXT)]
    private string $rawHeaders = '';

    /** @var list<string>|null */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $references = null;

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

    public function getRawHeaders(): string
    {
        return $this->rawHeaders;
    }

    public function setRawHeaders(string $rawHeaders): self
    {
        $this->rawHeaders = $rawHeaders;

        return $this;
    }

    /** @return list<string>|null */
    public function getReferences(): ?array
    {
        return $this->references;
    }

    /** @param list<string>|null $references */
    public function setReferences(?array $references): self
    {
        $this->references = $references;

        return $this;
    }
}

