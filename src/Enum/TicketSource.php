<?php

declare(strict_types=1);

namespace App\Enum;

enum TicketSource: string
{
    case Phone = 'phone';
    case Email = 'email';
    case Webhook = 'webhook';
    case ClientForm = 'client_form';
    case InternalForm = 'internal_form';
}

