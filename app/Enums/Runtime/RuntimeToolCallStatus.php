<?php

declare(strict_types=1);

namespace App\Enums\Runtime;

enum RuntimeToolCallStatus: string
{
    case PendingApproval = 'pending_approval';
    case Approved = 'approved';
    case Denied = 'denied';
    case Running = 'running';
    case Completed = 'completed';
    case Failed = 'failed';
}
