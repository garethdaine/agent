<?php

declare(strict_types=1);

namespace App\Enums\Messenger;

enum ApprovalMode: string
{
    case Autonomous = 'autonomous';
    case Supervised = 'supervised';
    case Restricted = 'restricted';

    public function label(): string
    {
        return match ($this) {
            self::Autonomous => 'Autonomous',
            self::Supervised => 'Supervised',
            self::Restricted => 'Restricted',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Autonomous => 'Auto-approve all tool usage without prompting.',
            self::Supervised => 'Allow file and web tools but block shell/command execution.',
            self::Restricted => 'Read-only access only; no file writes or execution.',
        };
    }
}
