<?php

declare(strict_types=1);

namespace App\Enums\Runtime;

enum PolicySnapshotReason: string
{
    case SessionStart = 'session_start';
    case ModeChange = 'mode_change';
}
