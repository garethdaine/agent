<?php

namespace App\Enums\Runtime;

enum PolicySnapshotReason: string
{
    case SessionStart = 'session_start';
    case ModeChange = 'mode_change';
}
