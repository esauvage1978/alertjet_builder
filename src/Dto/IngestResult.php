<?php

declare(strict_types=1);

namespace App\Dto;

use App\Entity\Ticket;

final class IngestResult
{
    public function __construct(
        public readonly Ticket $ticket,
        public readonly bool $merged,
    ) {
    }
}
