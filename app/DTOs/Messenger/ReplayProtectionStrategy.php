<?php

namespace App\DTOs\Messenger;

enum ReplayProtectionStrategy: string
{
    case Timestamp = 'timestamp';
    case EventId = 'event_id';
    case Both = 'both';
}
