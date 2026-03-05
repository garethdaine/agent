<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\AgentJobRun;
use Illuminate\Foundation\Events\Dispatchable;

class AgentJobRunFinished
{
    use Dispatchable;

    public function __construct(
        public readonly AgentJobRun $run,
        public readonly string $status,
    ) {}
}
