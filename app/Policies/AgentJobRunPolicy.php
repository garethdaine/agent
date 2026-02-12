<?php

namespace App\Policies;

use App\Models\AgentJobRun;
use App\Models\User;

class AgentJobRunPolicy
{
    public function viewAny(User $user): bool
    {
        return $user !== null;
    }

    public function view(User $user, AgentJobRun $agentJobRun): bool
    {
        return $agentJobRun->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user !== null;
    }

    public function update(User $user, AgentJobRun $agentJobRun): bool
    {
        return $agentJobRun->user_id === $user->id;
    }

    public function delete(User $user, AgentJobRun $agentJobRun): bool
    {
        return $agentJobRun->user_id === $user->id;
    }

    public function restore(User $user, AgentJobRun $agentJobRun): bool
    {
        return $agentJobRun->user_id === $user->id;
    }

    public function forceDelete(User $user, AgentJobRun $agentJobRun): bool
    {
        return $agentJobRun->user_id === $user->id;
    }
}
