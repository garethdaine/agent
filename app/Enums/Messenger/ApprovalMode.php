<?php

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
            self::Supervised => 'Surface tool calls for user approval via interactive buttons.',
            self::Restricted => 'Block dangerous tools entirely; read-only access only.',
        };
    }
}
