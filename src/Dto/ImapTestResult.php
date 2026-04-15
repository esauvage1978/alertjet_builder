<?php

declare(strict_types=1);

namespace App\Dto;

final readonly class ImapTestResult
{
    /**
     * @param array<string, int|string> $messageParameters
     */
    public function __construct(
        public bool $success,
        public string $messageKey,
        public array $messageParameters = [],
        public ?string $reason = null,
    ) {
    }
}
