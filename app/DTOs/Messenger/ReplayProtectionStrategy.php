<?php

declare(strict_types=1);

namespace App\DTOs\Messenger;

enum ReplayProtectionStrategy: string
{
    case Timestamp = 'timestamp';
    case EventId = 'event_id';
    case Both = 'both';
}
