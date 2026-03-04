<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\AgentJob;
use App\Models\User;

class WorkflowGovernancePolicy
{
    public function pause(User $user, AgentJob $job): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        return $this->isCentralOnCall($user);
    }

    public function resume(User $user, AgentJob $job): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        if ($this->isCentralOnCall($user)) {
            return true;
        }

        if ((int) $job->user_id === (int) $user->id) {
            return true;
        }

        return $this->isWorkflowDelegate($user, $job);
    }

    public function override(User $user, AgentJob $job): bool
    {
        return $user->hasRole('admin');
    }

    private function isCentralOnCall(User $user): bool
    {
        return $this->containsUserId(config('agent.roles.central_on_call_user_ids', []), $user);
    }

    private function isWorkflowDelegate(User $user, AgentJob $job): bool
    {
        $workflowDelegates = config('agent.roles.workflow_delegate_user_ids_by_workflow', []);

        if (is_array($workflowDelegates) && isset($workflowDelegates[$job->workflow_key]) && is_array($workflowDelegates[$job->workflow_key])) {
            return $this->containsUserId($workflowDelegates[$job->workflow_key], $user);
        }

        return $this->containsUserId(config('agent.roles.workflow_delegate_user_ids', []), $user);
    }

    private function containsUserId(mixed $ids, User $user): bool
    {
        if (! is_array($ids)) {
            return false;
        }

        foreach ($ids as $id) {
            if ((int) $id === (int) $user->id) {
                return true;
            }
        }

        return false;
    }
}
