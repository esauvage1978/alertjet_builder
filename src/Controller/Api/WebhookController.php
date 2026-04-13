<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\Project;
use App\Repository\ProjectRepository;
use App\Service\ApplicationErrorLogger;
use App\Service\TicketIngestionService;
use App\Service\WebhookCorsHelper;
use App\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;

final class WebhookController extends AbstractController
{
    private const TOKEN_REQUIREMENT = '[a-f0-9]{32}';

    public function __construct(
        private readonly ProjectRepository $projectRepository,
        private readonly TicketIngestionService $ingestionService,
        private readonly ApplicationErrorLogger $applicationErrorLogger,
        private readonly WebhookCorsHelper $webhookCorsHelper,
    ) {
    }

    #[Route(
        '/api/webhook/{orgToken}/{projectToken}/{webhookToken}',
        name: 'api_webhook_receive',
        requirements: [
            'orgToken' => '[a-f0-9]{12}',
            'projectToken' => '[a-f0-9]{12}',
            'webhookToken' => self::TOKEN_REQUIREMENT,
        ],
        methods: ['POST'],
    )]
    public function receive(
        string $orgToken,
        string $projectToken,
        string $webhookToken,
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

        $project = $this->projectRepository->findByWebhookScoped($orgToken, $projectToken, $webhookToken);
        if ($project === null) {
            return $this->json(['error' => 'unknown_webhook_token'], Response::HTTP_NOT_FOUND);
        }

        if (!$this->webhookCorsHelper->isOriginRequestAllowed($request, $project)) {
            return $this->json(['error' => 'cors_forbidden'], Response::HTTP_FORBIDDEN);
        }

        $response = $this->processReceiveBody($project, $request, $webhookToken);
        $this->webhookCorsHelper->attachResponseCorsHeaders($response, $request, $project);

        return $response;
    }

    #[Route(
        '/api/webhook/{orgToken}/{projectToken}/{webhookToken}',
        name: 'api_webhook_receive_options',
        requirements: [
            'orgToken' => '[a-f0-9]{12}',
            'projectToken' => '[a-f0-9]{12}',
            'webhookToken' => self::TOKEN_REQUIREMENT,
        ],
        methods: ['OPTIONS'],
    )]
    public function receiveOptions(
        string $orgToken,
        string $projectToken,
        string $webhookToken,
        Request $request,
    ): Response {
        return $this->preflightScoped($orgToken, $projectToken, $webhookToken, $request);
    }

    /** @deprecated URL à un seul segment — préférer /api/webhook/{org}/{projet}/{secret} */
    #[Route(
        '/api/webhook/{token}',
        name: 'api_webhook_receive_legacy',
        requirements: ['token' => self::TOKEN_REQUIREMENT],
        methods: ['POST'],
    )]
    public function receiveLegacy(
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

        if (!$this->webhookCorsHelper->isOriginRequestAllowed($request, $project)) {
            return $this->json(['error' => 'cors_forbidden'], Response::HTTP_FORBIDDEN);
        }

        $response = $this->processReceiveBody($project, $request, $token);
        $this->webhookCorsHelper->attachResponseCorsHeaders($response, $request, $project);

        return $response;
    }

    #[Route(
        '/api/webhook/{token}',
        name: 'api_webhook_receive_legacy_options',
        requirements: ['token' => self::TOKEN_REQUIREMENT],
        methods: ['OPTIONS'],
    )]
    public function receiveLegacyOptions(string $token, Request $request): Response
    {
        $project = $this->projectRepository->findByWebhookToken($token);
        if ($project === null) {
            return new Response('', Response::HTTP_NOT_FOUND);
        }
        if (!$this->webhookCorsHelper->isOriginRequestAllowed($request, $project)) {
            return new Response('', Response::HTTP_FORBIDDEN);
        }
        $response = new Response('', Response::HTTP_NO_CONTENT);
        $this->webhookCorsHelper->attachPreflightHeaders($response, $request, $project);

        return $response;
    }

    private function preflightScoped(string $orgToken, string $projectToken, string $webhookToken, Request $request): Response
    {
        $project = $this->projectRepository->findByWebhookScoped($orgToken, $projectToken, $webhookToken);
        if ($project === null) {
            return new Response('', Response::HTTP_NOT_FOUND);
        }
        if (!$this->webhookCorsHelper->isOriginRequestAllowed($request, $project)) {
            return new Response('', Response::HTTP_FORBIDDEN);
        }
        $response = new Response('', Response::HTTP_NO_CONTENT);
        $this->webhookCorsHelper->attachPreflightHeaders($response, $request, $project);

        return $response;
    }

    private function processReceiveBody(Project $project, Request $request, string $webhookTokenForLog): Response
    {
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
                'webhookTokenSuffix' => substr($webhookTokenForLog, -6),
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

    #[Route(
        '/api/webhook/{orgToken}/{projectToken}/{webhookToken}',
        name: 'api_webhook_ping',
        requirements: [
            'orgToken' => '[a-f0-9]{12}',
            'projectToken' => '[a-f0-9]{12}',
            'webhookToken' => self::TOKEN_REQUIREMENT,
        ],
        methods: ['GET', 'HEAD'],
    )]
    public function ping(
        string $orgToken,
        string $projectToken,
        string $webhookToken,
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

        $project = $this->projectRepository->findByWebhookScoped($orgToken, $projectToken, $webhookToken);
        if ($project === null) {
            return $this->json(['ok' => false], Response::HTTP_NOT_FOUND);
        }

        return $this->json([
            'ok' => true,
            'project' => $project->getName(),
            'message' => 'POST un JSON ou du texte pour créer / fusionner un ticket.',
        ]);
    }

    #[Route(
        '/api/webhook/{token}',
        name: 'api_webhook_ping_legacy',
        requirements: ['token' => self::TOKEN_REQUIREMENT],
        methods: ['GET', 'HEAD'],
    )]
    public function pingLegacy(
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
