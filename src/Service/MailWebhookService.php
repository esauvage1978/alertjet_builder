<?php

declare(strict_types=1);

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Envoie une charge utile JSON vers MAIL_WEBHOOK_URL pour déclencher l’envoi d’e-mail côté automation (Make, n8n, etc.).
 *
 * Schéma suggéré : { type: "email", template, to, subject, data, meta }
 */
final class MailWebhookService
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        private readonly string $mailWebhookUrl,
        private readonly string $mailWebhookSecret,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public function send(string $template, string $to, string $subject, array $data = []): void
    {
        if ($this->mailWebhookUrl === '') {
            $this->logger->info('MAIL_WEBHOOK_URL vide — e-mail non envoyé (webhook désactivé).', [
                'template' => $template,
                'to' => $to,
                'subject' => $subject,
            ]);

            return;
        }

        $payload = [
            'type' => 'email',
            'template' => $template,
            'to' => $to,
            'subject' => $subject,
            'data' => $data,
            'meta' => [
                'app' => 'alertjet-builder',
                'sentAt' => (new \DateTimeImmutable())->format(\DATE_ATOM),
            ],
        ];

        $body = json_encode($payload, JSON_THROW_ON_ERROR);
        $headers = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];

        if ($this->mailWebhookSecret !== '') {
            $headers['X-Webhook-Signature'] = hash_hmac('sha256', $body, $this->mailWebhookSecret);
        }

        $this->httpClient->request('POST', $this->mailWebhookUrl, [
            'headers' => $headers,
            'body' => $body,
            'timeout' => 15,
        ]);
    }
}
