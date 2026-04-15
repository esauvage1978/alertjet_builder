<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\ImapTestResult;
use App\Entity\Project;

final class ProjectImapConnectionTester
{
    public function __construct(
        private readonly SecretBoxCrypto $secretBoxCrypto,
    ) {
    }

    public function test(Project $project): ImapTestResult
    {
        if (!$project->isImapEnabled()) {
            return new ImapTestResult(false, 'org.projects.imap_test.disabled');
        }

        if (!\function_exists('imap_open')) {
            return new ImapTestResult(false, 'org.projects.imap_test.no_extension');
        }

        $host = trim((string) $project->getImapHost());
        if ($host === '') {
            return new ImapTestResult(false, 'org.projects.imap_test.missing_host');
        }

        $user = trim((string) $project->getImapUsername());
        if ($user === '') {
            return new ImapTestResult(false, 'org.projects.imap_test.missing_user');
        }

        $cipher = $project->getImapPasswordCipher();
        if ($cipher === null || $cipher === '') {
            return new ImapTestResult(false, 'org.projects.imap_test.missing_password');
        }

        $password = $this->secretBoxCrypto->decrypt($cipher);
        if ($password === null || $password === '') {
            return new ImapTestResult(false, 'org.projects.imap_test.decrypt_failed');
        }

        $serverOnly = $this->buildServerOnlyMailboxUri($project);
        $folder = trim((string) $project->getImapMailbox());
        if ($folder === '') {
            $folder = 'INBOX';
        }
        $fullMailbox = $serverOnly.$folder;

        \set_error_handler(static function (int $errno, string $errstr): bool {
            return true;
        });

        try {
            \imap_errors();

            // 1) Connexion + authentification sans ouvrir de dossier (hôte, port, TLS, identifiants).
            $conn = @\imap_open($serverOnly, $user, $password, \OP_HALFOPEN);
            $firstErr = (string) (\imap_last_error() ?: '');
            \imap_errors();

            if ($conn === false) {
                // Certains serveurs exigent un nom de boîte dans l’URI même en OP_HALFOPEN.
                $conn = @\imap_open($serverOnly.'INBOX', $user, $password, \OP_HALFOPEN);
                $secondErr = (string) (\imap_last_error() ?: '');
                \imap_errors();

                if ($conn === false) {
                    $err = $firstErr !== '' ? $firstErr : $secondErr;
                    $kind = ImapConnectionErrorClassifier::classifyConnectionFailure($err);

                    return new ImapTestResult(
                        false,
                        self::messageKeyForConnectionKind($kind),
                        ['%error%' => $err !== '' ? $err : '—'],
                        $kind,
                    );
                }

                // Connecté via {serveur}INBOX : vérifier le dossier demandé si différent.
                if (\strcasecmp($folder, 'INBOX') !== 0) {
                    if (!@\imap_reopen($conn, $fullMailbox)) {
                        $mErr = (string) (\imap_last_error() ?: '');
                        \imap_errors();
                        \imap_close($conn);

                        return new ImapTestResult(
                            false,
                            'org.projects.imap_test.failed_mailbox',
                            [
                                '%folder%' => $folder,
                                '%error%' => $mErr !== '' ? $mErr : '—',
                            ],
                            'mailbox',
                        );
                    }
                }

                \imap_close($conn);

                return new ImapTestResult(true, 'org.projects.imap_test.ok');
            }

            // 2) Dossier IMAP (présence / droits sur la boîte sélectionnée).
            if (!@\imap_reopen($conn, $fullMailbox)) {
                $mErr = (string) (\imap_last_error() ?: '');
                \imap_errors();
                \imap_close($conn);

                return new ImapTestResult(
                    false,
                    'org.projects.imap_test.failed_mailbox',
                    [
                        '%folder%' => $folder,
                        '%error%' => $mErr !== '' ? $mErr : '—',
                    ],
                    'mailbox',
                );
            }

            \imap_close($conn);
        } finally {
            \restore_error_handler();
        }

        return new ImapTestResult(true, 'org.projects.imap_test.ok');
    }

    /**
     * @return 'credentials'|'tls'|'host_port'|'unknown'
     */
    private static function messageKeyForConnectionKind(string $kind): string
    {
        return match ($kind) {
            'credentials' => 'org.projects.imap_test.failed_credentials',
            'tls' => 'org.projects.imap_test.failed_tls',
            'host_port' => 'org.projects.imap_test.failed_host_port',
            default => 'org.projects.imap_test.failed_unknown',
        };
    }

    private function buildServerOnlyMailboxUri(Project $project): string
    {
        $host = trim((string) $project->getImapHost());
        $port = $project->getImapPort();
        $flags = '/imap';
        if ($project->isImapTls()) {
            $flags .= '/ssl';
        }

        return sprintf('{%s:%d%s}', $host, $port, $flags);
    }
}
