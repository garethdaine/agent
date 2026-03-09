<?php

declare(strict_types=1);

namespace App\Actions\Agent;

use App\Models\User;

class CheckAgentJobWorkflowKeyUniqueAction
{
    public function execute(User $user, string $workflowKey, ?int $excludeId = null): bool
    {
        $query = $user->agentJobs()
            ->whereNull('deleted_at')
            ->where('workflow_key', $workflowKey);

        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }
}
