<?php

namespace App\Enums\Runtime;

enum RuntimeTurnStatus: string
{
    case Pending = 'pending';
    case Running = 'running';
    case Completed = 'completed';
    case Failed = 'failed';
}
