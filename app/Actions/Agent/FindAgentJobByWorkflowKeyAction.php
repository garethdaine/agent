<?php

declare(strict_types=1);

namespace App\Actions\Agent;

use App\Models\AgentJob;
use App\Models\User;

class FindAgentJobByWorkflowKeyAction
{
    public function execute(User $user, string $workflowKey): AgentJob
    {
        /** @var AgentJob */
        return AgentJob::query()
            ->forUser($user)
            ->where('workflow_key', $workflowKey)
            ->firstOrFail();
    }
}
