<?php

declare(strict_types=1);

namespace App\Enums\Runtime;

enum RuntimeApprovalState: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Denied = 'denied';
    case Expired = 'expired';
}
