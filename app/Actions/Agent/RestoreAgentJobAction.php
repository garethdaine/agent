<?php

declare(strict_types=1);

namespace App\Actions\Agent;

use App\Models\AgentJob;

class RestoreAgentJobAction
{
    public function execute(AgentJob $job): AgentJob
    {
        if ($job->trashed()) {
            $job->restore();
        }

        /** @var AgentJob */
        return $job->fresh();
    }
}
