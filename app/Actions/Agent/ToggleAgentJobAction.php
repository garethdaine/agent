<?php

declare(strict_types=1);

namespace App\Actions\Agent;

use App\Models\AgentJob;

class ToggleAgentJobAction
{
    public function execute(AgentJob $job): AgentJob
    {
        $job->is_enabled = ! $job->is_enabled;
        $job->save();

        return $job;
    }
}
