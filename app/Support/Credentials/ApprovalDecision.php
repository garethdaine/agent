<?php

declare(strict_types=1);

namespace App\Support\Credentials;

enum ApprovalDecision: string
{
    case Allowed = 'allowed';
    case NeedsConfirmation = 'needs_confirmation';
    case Denied = 'denied';
}
