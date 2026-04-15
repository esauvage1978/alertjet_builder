<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * ITIL (simplifié) : Incident / Problème / Demande (service request).
 */
enum TicketType: string
{
    case Incident = 'incident';
    case Problem = 'problem';
    case Request = 'request';
}

