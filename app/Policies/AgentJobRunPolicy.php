<?php

declare(strict_types=1);

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
        if ($agentJobRun->user_id === $user->id) {
            return true;
        }

        $token = request()->user()?->currentAccessToken();
        if ($token && $token->team_id !== null) {
            $agentJobRun->loadMissing(['team', 'job.team']);
            $teamId = (int) $token->team_id;
            $runTeamId = $agentJobRun->team_id !== null ? (int) $agentJobRun->team_id : null;
            $jobTeamId = $agentJobRun->job?->team_id !== null ? (int) $agentJobRun->job->team_id : null;
            $match = ($runTeamId === $teamId) || ($jobTeamId === $teamId);
            $team = $agentJobRun->team ?? $agentJobRun->job?->team;

            return $match && $team !== null && $user->belongsToTeam($team);
        }

        return $agentJobRun->user_id === $user->id
            || ($agentJobRun->team_id !== null && $user->belongsToTeam($agentJobRun->team))
            || ($agentJobRun->job && $user->can('view', $agentJobRun->job));
    }

    public function create(User $user): bool
    {
        return $user !== null;
    }

    public function update(User $user, AgentJobRun $agentJobRun): bool
    {
        return $this->view($user, $agentJobRun);
    }

    public function delete(User $user, AgentJobRun $agentJobRun): bool
    {
        return $this->view($user, $agentJobRun);
    }

    public function restore(User $user, AgentJobRun $agentJobRun): bool
    {
        return $this->view($user, $agentJobRun);
    }

    public function forceDelete(User $user, AgentJobRun $agentJobRun): bool
    {
        return $this->view($user, $agentJobRun);
    }
}
