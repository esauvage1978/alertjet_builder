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

        $mailbox = $this->buildMailboxUri($project);
        \set_error_handler(static function (int $errno, string $errstr): bool {
            return true;
        });

        try {
            $conn = @\imap_open($mailbox, $user, $password, \OP_HALFOPEN);
        } finally {
            \restore_error_handler();
        }

        if ($conn === false) {
            $err = \imap_last_error() ?: '';

            return new ImapTestResult(false, 'org.projects.imap_test.failed', ['%error%' => $err]);
        }

        \imap_close($conn);

        return new ImapTestResult(true, 'org.projects.imap_test.ok');
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
