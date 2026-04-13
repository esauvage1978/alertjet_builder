<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Repository\ProjectRepository;
use App\Service\ApplicationErrorLogger;
use App\Service\TicketIngestionService;
use App\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;

final class WebhookController extends AbstractController
{
    public function __construct(
        private readonly ProjectRepository $projectRepository,
        private readonly TicketIngestionService $ingestionService,
        private readonly ApplicationErrorLogger $applicationErrorLogger,
    ) {
    }

    #[Route('/api/webhook/{token}', name: 'api_webhook_receive', methods: ['POST'])]
    public function receive(
        string $token,
        Request $request,
        #[Autowire(service: 'limiter.webhook_ingest')]
        RateLimiterFactory $webhookIngestLimiter,
    ): Response {
        $rateLimit = $webhookIngestLimiter->create($request->getClientIp() ?? 'unknown')->consume();
        if (!$rateLimit->isAccepted()) {
            $retry = max(1, $rateLimit->getRetryAfter()->getTimestamp() - time());
            $response = $this->json(['error' => 'rate_limited'], Response::HTTP_TOO_MANY_REQUESTS);
            $response->headers->set('Retry-After', (string) $retry);

            return $response;
        }

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

        try {
            $result = $this->ingestionService->ingestFromWebhook($project, $rawBody, $json);
        } catch (\Throwable $e) {
            $this->applicationErrorLogger->logThrowable($e, $request, null, [
                'layer' => 'webhook_ingest',
                'projectId' => $project->getId(),
                'webhookTokenSuffix' => substr($token, -6),
            ], 'caught');

            return $this->json(['error' => 'ingest_failed'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

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
    public function ping(
        string $token,
        Request $request,
        #[Autowire(service: 'limiter.webhook_ingest')]
        RateLimiterFactory $webhookIngestLimiter,
    ): Response {
        $rateLimit = $webhookIngestLimiter->create($request->getClientIp() ?? 'unknown')->consume();
        if (!$rateLimit->isAccepted()) {
            $retry = max(1, $rateLimit->getRetryAfter()->getTimestamp() - time());
            $response = $this->json(['ok' => false, 'error' => 'rate_limited'], Response::HTTP_TOO_MANY_REQUESTS);
            $response->headers->set('Retry-After', (string) $retry);

            return $response;
        }

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
