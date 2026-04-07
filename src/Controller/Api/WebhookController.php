<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Repository\ProjectRepository;
use App\Service\TicketIngestionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class WebhookController extends AbstractController
{
    public function __construct(
        private readonly ProjectRepository $projectRepository,
        private readonly TicketIngestionService $ingestionService,
    ) {
    }

    #[Route('/api/webhook/{token}', name: 'api_webhook_receive', methods: ['POST'])]
    public function receive(string $token, Request $request): Response
    {
        $project = $this->projectRepository->findByWebhookToken($token);
        if ($project === null) {
            return $this->json(['error' => 'unknown_webhook_token'], Response::HTTP_NOT_FOUND);
        }

        $rawBody = $request->getContent();
        $json = null;
        $ct = (string) $request->headers->get('Content-Type', '');
        if (str_contains($ct, 'application/json') || str_starts_with(ltrim($rawBody), '{') || str_starts_with(ltrim($rawBody), '[')) {
            try {
                $decoded = json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);
                $json = \is_array($decoded) ? $decoded : null;
            } catch (\JsonException) {
                $json = null;
            }
        }

        $result = $this->ingestionService->ingestFromWebhook($project, $rawBody, $json);
        $ticket = $result->ticket;

        return $this->json([
            'ok' => true,
            'merged' => $result->merged,
            'ticketId' => $ticket->getId(),
            'publicId' => (string) $ticket->getPublicId(),
            'eventCount' => $ticket->getEventCount(),
        ], Response::HTTP_OK);
    }

    #[Route('/api/webhook/{token}', name: 'api_webhook_ping', methods: ['GET', 'HEAD'])]
    public function ping(string $token): Response
    {
        $project = $this->projectRepository->findByWebhookToken($token);
        if ($project === null) {
            return $this->json(['ok' => false], Response::HTTP_NOT_FOUND);
        }

        return $this->json([
            'ok' => true,
            'project' => $project->getName(),
            'message' => 'POST un JSON ou du texte pour créer / fusionner un ticket.',
        ]);
    }
}
