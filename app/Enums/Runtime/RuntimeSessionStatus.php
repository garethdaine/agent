<?php

declare(strict_types=1);

namespace App\Enums\Runtime;

enum RuntimeSessionStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Completed = 'completed';
    case Failed = 'failed';
    case Stopped = 'stopped';
}
