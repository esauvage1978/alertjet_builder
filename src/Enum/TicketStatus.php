<?php

declare(strict_types=1);

namespace App\Enum;

enum TicketStatus: string
{
    /**
     * Valeur historique (avant ITIL) — conservée pour compat base, à migrer vers `new`.
     */
    case Open = 'open';
    case New = 'new';
    case Acknowledged = 'acknowledged';
    case InProgress = 'in_progress';
    case OnHold = 'on_hold';
    case Resolved = 'resolved';
    case Closed = 'closed';
    case Cancelled = 'cancelled';
}
