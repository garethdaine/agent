<?php

declare(strict_types=1);

namespace App\Actions\Agent;

use App\Models\AgentJob;

class FindWorkflowJobAction
{
    public function execute(string $workflowKey): ?AgentJob
    {
        return AgentJob::query()
            ->whereNull('deleted_at')
            ->where('workflow_key', $workflowKey)
            ->first();
    }
}
