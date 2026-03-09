<?php

declare(strict_types=1);

namespace App\Actions\Agent;

use App\Models\AgentJob;

class DeleteAgentJobAction
{
    public function execute(AgentJob $job): bool
    {
        return (bool) $job->delete();
    }
}
