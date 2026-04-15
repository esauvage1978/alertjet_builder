<?php

declare(strict_types=1);

namespace App\Dto;

/**
 * Résultat détaillé d’un import IMAP pour un projet.
 */
final readonly class ImapFetchProjectStats
{
    /**
     * @param list<array{type: string, message: string, context?: array<string, mixed>}> $failures
     */
    public function __construct(
        public int $unseenCount,
        public int $ticketsCreated,
        public int $failureCount,
        public array $failures = [],
        public ?string $connectionError = null,
        public ?string $mailboxError = null,
    ) {
    }
}

