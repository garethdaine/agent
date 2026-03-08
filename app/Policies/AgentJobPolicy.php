<?php

declare(strict_types=1);

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
        if ($agentJob->user_id === $user->id) {
            return true;
        }

        $token = request()->user()?->currentAccessToken();
        if ($token && $token->team_id !== null) {
            return (int) $agentJob->team_id === (int) $token->team_id
                && $user->belongsToTeam($agentJob->team);
        }

        return $agentJob->team_id !== null && $user->belongsToTeam($agentJob->team);
    }

    public function create(User $user): bool
    {
        return $user !== null;
    }

    public function update(User $user, AgentJob $agentJob): bool
    {
        return $this->view($user, $agentJob);
    }

    public function delete(User $user, AgentJob $agentJob): bool
    {
        return $this->view($user, $agentJob);
    }

    public function restore(User $user, AgentJob $agentJob): bool
    {
        return $this->view($user, $agentJob);
    }

    public function forceDelete(User $user, AgentJob $agentJob): bool
    {
        return $this->view($user, $agentJob);
    }
}
