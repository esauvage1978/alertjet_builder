<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ProjectRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProjectRepository::class)]
#[ORM\Table(name: 'projects')]
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

    /** @var Collection<int, Ticket> */
    #[ORM\OneToMany(targetEntity: Ticket::class, mappedBy: 'project', orphanRemoval: true)]
    private Collection $tickets;

    public function __construct()
    {
        $this->tickets = new ArrayCollection();
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
}
