<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\ImapFetchProjectStats;
use App\Entity\Project;
use App\Service\ApplicationErrorLogger;
use Psr\Log\LoggerInterface;

/**
 * Récupère les messages non lus sur la boîte IMAP du projet et crée des tickets.
 */
final class ProjectImapInboxService
{
    public function __construct(
        private readonly TicketIngestionService $ticketIngestionService,
        private readonly SecretBoxCrypto $secretBoxCrypto,
        private readonly LoggerInterface $logger,
        private readonly ApplicationErrorLogger $applicationErrorLogger,
        private readonly ImapMimeParser $imapMimeParser,
    ) {
    }

    /**
     * @return ImapFetchProjectStats Statistiques d’import
     */
    public function fetchAndIngestUnread(Project $project): ImapFetchProjectStats
    {
        if (!$project->isImapEnabled() || !\function_exists('imap_open')) {
            return new ImapFetchProjectStats(0, 0, 0);
        }

        $host = trim((string) $project->getImapHost());
        $user = trim((string) $project->getImapUsername());
        $cipher = $project->getImapPasswordCipher();
        if ($host === '' || $user === '' || $cipher === null || $cipher === '') {
            return new ImapFetchProjectStats(0, 0, 0);
        }

        $password = $this->secretBoxCrypto->decrypt($cipher);
        if ($password === null || $password === '') {
            $this->logger->warning('IMAP: impossible de déchiffrer le mot de passe du projet.', ['projectId' => $project->getId()]);

            return new ImapFetchProjectStats(0, 0, 1, [
                [
                    'type' => 'decrypt_failed',
                    'message' => 'Impossible de déchiffrer le mot de passe IMAP.',
                    'context' => ['projectId' => $project->getId()],
                ],
            ]);
        }

        $mailbox = $this->buildMailboxUri($project);

        \set_error_handler(static function (int $errno, string $errstr): bool {
            return true;
        });

        try {
            $conn = @\imap_open($mailbox, $user, $password);
        } finally {
            \restore_error_handler();
        }

        if ($conn === false) {
            $err = (string) (\imap_last_error() ?: '');
            $this->logger->warning('IMAP: échec de connexion.', [
                'projectId' => $project->getId(),
                'error' => $err,
            ]);

            return new ImapFetchProjectStats(0, 0, 1, [
                [
                    'type' => 'connection_failed',
                    'message' => $err !== '' ? $err : 'Connexion IMAP échouée.',
                    'context' => ['projectId' => $project->getId()],
                ],
            ], connectionError: $err !== '' ? $err : null);
        }

        try {
            $uids = \imap_search($conn, 'UNSEEN', \SE_UID) ?: [];
            $unseenCount = \is_array($uids) ? \count($uids) : 0;
            $created = 0;
            $failures = [];
            foreach ($uids as $uid) {
                if (!\is_int($uid) && !\is_string($uid)) {
                    continue;
                }
                $uidInt = \is_int($uid) ? $uid : (ctype_digit($uid) ? (int) $uid : 0);
                if ($uidInt <= 0) {
                    continue;
                }
                $num = \imap_msgno($conn, $uidInt);
                if ($num === 0) {
                    continue;
                }

                $header = \imap_headerinfo($conn, $num);
                $subject = '';
                $fromEmail = null;
                $fromDisplayName = null;
                if ($header !== false) {
                    $rawSub = $header->subject ?? '';
                    if (\is_string($rawSub)) {
                        $decoded = @\iconv_mime_decode($rawSub, \ICONV_MIME_DECODE_CONTINUE_ON_ERROR, 'UTF-8');
                        $subject = \is_string($decoded) ? $decoded : $rawSub;
                    }
                    if (isset($header->from[0])) {
                        $mbUser = $header->from[0]->mailbox ?? '';
                        $hostPart = $header->from[0]->host ?? '';
                        if (\is_string($mbUser) && \is_string($hostPart) && $mbUser !== '' && $hostPart !== '') {
                            $fromEmail = mb_strtolower($mbUser.'@'.$hostPart);
                        }
                        $personal = $header->from[0]->personal ?? '';
                        if (\is_string($personal) && $personal !== '') {
                            $dec = \iconv_mime_decode($personal, \ICONV_MIME_DECODE_CONTINUE_ON_ERROR, 'UTF-8');
                            $fromDisplayName = \is_string($dec) && $dec !== '' ? $dec : $personal;
                        }
                    }
                }

                $parsed = $this->imapMimeParser->extractPlainAndAttachments($conn, $num);
                $body = $parsed['plain'];
                if ($body === '') {
                    $raw = (string) \imap_body($conn, $num);
                    if ($raw !== '') {
                        $raw = quoted_printable_decode($raw);
                    }
                    $body = trim(strip_tags($raw));
                }
                $mailAttachments = $parsed['attachments'];

                $messageId = null;
                if ($header !== false && isset($header->message_id) && \is_string($header->message_id)) {
                    $messageId = trim($header->message_id, "<> \t\n\r\0\x0B");
                }

                try {
                    $this->ticketIngestionService->ingestFromEmail(
                        $project,
                        $subject,
                        $body,
                        $messageId,
                        $fromEmail,
                        $fromDisplayName,
                        $mailAttachments,
                    );
                    @\imap_setflag_full($conn, (string) $uid, '\\Seen', \ST_UID);
                    ++$created;
                } catch (\Throwable $e) {
                    $this->logger->error('IMAP: erreur lors de la création du ticket.', [
                        'projectId' => $project->getId(),
                        'exception' => $e,
                    ]);
                    $this->applicationErrorLogger->logThrowable($e, null, null, [
                        'layer' => 'imap_ingest_message',
                        'projectId' => $project->getId(),
                        'imapUid' => $uid,
                    ], 'caught');
                    $failures[] = [
                        'type' => 'ingest_failed',
                        'message' => $e->getMessage(),
                        'context' => [
                            'projectId' => $project->getId(),
                            'imapUid' => $uid,
                            'exceptionClass' => $e::class,
                        ],
                    ];
                }
            }

            return new ImapFetchProjectStats(
                unseenCount: $unseenCount,
                ticketsCreated: $created,
                failureCount: \count($failures),
                failures: $failures,
            );
        } finally {
            \imap_close($conn);
        }
    }

    private function buildMailboxUri(Project $project): string
    {
        $host = trim((string) $project->getImapHost());
        $port = $project->getImapPort();
        $flags = '/imap';
        if ($project->isImapTls()) {
            $flags .= '/ssl';
        }
        $folder = trim($project->getImapMailbox());
        if ($folder === '') {
            $folder = 'INBOX';
        }

        return sprintf('{%s:%d%s}%s', $host, $port, $flags, $folder);
    }
}
