<?php

namespace App\Policies;

use App\Models\AgentJob;
use App\Models\User;

class AgentJobPolicy
{
    public function viewAny(User $user): bool
    {
        return $user !== null;
    }

    public function view(User $user, AgentJob $agentJob): bool
    {
        return $agentJob->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user !== null;
    }

    public function update(User $user, AgentJob $agentJob): bool
    {
        return $agentJob->user_id === $user->id;
    }

    public function delete(User $user, AgentJob $agentJob): bool
    {
        return $agentJob->user_id === $user->id;
    }

    public function restore(User $user, AgentJob $agentJob): bool
    {
        return $agentJob->user_id === $user->id;
    }

    public function forceDelete(User $user, AgentJob $agentJob): bool
    {
        return $agentJob->user_id === $user->id;
    }
}
