<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Api\TicketApiPresenter;
use App\Entity\TicketLog;
use App\Enum\TicketPriority;
use App\Enum\TicketStatus;
use App\Repository\ProjectRepository;
use App\Repository\TicketRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class TicketApiController extends AbstractController
{
    public function __construct(
        private readonly ProjectRepository $projectRepository,
        private readonly TicketRepository $ticketRepository,
        private readonly EntityManagerInterface $em,
    ) {
    }

    #[Route('/api/projects/{id}/tickets', name: 'api_tickets_list', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function listForProject(int $id, Request $request): Response
    {
        $project = $this->projectRepository->find($id);
        if ($project === null) {
            return $this->json(['error' => 'project_not_found'], Response::HTTP_NOT_FOUND);
        }

        $status = $request->query->get('status');
        $qb = $this->ticketRepository->createQueryBuilder('t')
            ->andWhere('t.project = :p')
            ->setParameter('p', $project)
            ->orderBy('t.createdAt', 'DESC');

        if (\is_string($status) && $status !== '') {
            try {
                $st = TicketStatus::from($status);
                $qb->andWhere('t.status = :st')->setParameter('st', $st);
            } catch (\ValueError) {
                return $this->json(['error' => 'invalid_status'], Response::HTTP_BAD_REQUEST);
            }
        }

        $tickets = $qb->getQuery()->getResult();

        return $this->json(array_map(static fn ($t) => TicketApiPresenter::one($t), $tickets));
    }

    #[Route('/api/tickets/{id}', name: 'api_ticket_one', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function one(int $id): Response
    {
        $ticket = $this->ticketRepository->find($id);
        if ($ticket === null) {
            return $this->json(['error' => 'ticket_not_found'], Response::HTTP_NOT_FOUND);
        }

        return $this->json(TicketApiPresenter::one($ticket));
    }

    #[Route('/api/tickets/{id}', name: 'api_ticket_patch', methods: ['PATCH'], requirements: ['id' => '\d+'])]
    public function patch(int $id, Request $request): Response
    {
        $ticket = $this->ticketRepository->find($id);
        if ($ticket === null) {
            return $this->json(['error' => 'ticket_not_found'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);
        if (!\is_array($data)) {
            return $this->json(['error' => 'invalid_json'], Response::HTTP_BAD_REQUEST);
        }

        $before = $ticket->getStatus()->value;

        if (isset($data['status']) && \is_string($data['status'])) {
            try {
                $ticket->setStatus(TicketStatus::from($data['status']));
            } catch (\ValueError) {
                return $this->json(['error' => 'invalid_status'], Response::HTTP_BAD_REQUEST);
            }
        }

        if (isset($data['priority']) && \is_string($data['priority'])) {
            try {
                $ticket->setPriority(TicketPriority::from($data['priority']));
            } catch (\ValueError) {
                return $this->json(['error' => 'invalid_priority'], Response::HTTP_BAD_REQUEST);
            }
        }

        if (\array_key_exists('silenced', $data)) {
            $ticket->setSilenced((bool) $data['silenced']);
        }

        if (isset($data['note']) && \is_string($data['note']) && trim($data['note']) !== '') {
            $ticket->addLog(
                (new TicketLog())
                    ->setType('note')
                    ->setMessage(trim($data['note'])),
            );
        }

        if ($ticket->getStatus()->value !== $before) {
            $ticket->addLog(
                (new TicketLog())
                    ->setType('status')
                    ->setMessage(sprintf('Statut : %s → %s', $before, $ticket->getStatus()->value)),
            );
        }

        $this->em->flush();

        return $this->json(TicketApiPresenter::one($ticket));
    }

    #[Route('/api/projects/{id}/stats', name: 'api_project_stats', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function stats(int $id): Response
    {
        $project = $this->projectRepository->find($id);
        if ($project === null) {
            return $this->json(['error' => 'project_not_found'], Response::HTTP_NOT_FOUND);
        }

        $conn = $this->em->getConnection();
        $pid = $project->getId();

        $total = (int) $conn->fetchOne('SELECT COUNT(*) FROM tickets WHERE project_id = ?', [$pid]);
        $criticalOpen = (int) $conn->fetchOne(
            "SELECT COUNT(*) FROM tickets WHERE project_id = ? AND priority = 'critical' AND status IN ('open','in_progress')",
            [$pid],
        );

        $mts = $conn->fetchOne(
            "SELECT AVG((julianday(resolved_at) - julianday(created_at)) * 86400) FROM tickets WHERE project_id = ? AND resolved_at IS NOT NULL",
            [$pid],
        );
        $avgResolutionSeconds = $mts !== null ? (float) $mts : null;

        return $this->json([
            'totalTickets' => $total,
            'openCritical' => $criticalOpen,
            'avgResolutionSeconds' => $avgResolutionSeconds,
        ]);
    }
}
