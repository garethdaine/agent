<?php

declare(strict_types=1);

namespace App\Actions\Agent;

use App\Models\AgentJobRun;

class CheckRunOverlapAction
{
    public function execute(int $jobId): bool
    {
        return AgentJobRun::query()
            ->where('agent_job_id', $jobId)
            ->whereIn('status', AgentJobRun::ACTIVE_STATUSES)
            ->exists();
    }
}
