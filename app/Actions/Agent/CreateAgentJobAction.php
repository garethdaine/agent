<?php

declare(strict_types=1);

namespace App\Actions\Agent;

use App\Models\AgentJob;
use App\Models\User;

class CreateAgentJobAction
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function execute(User $user, array $attributes): AgentJob
    {
        /** @var AgentJob */
        return $user->agentJobs()->create($attributes);
    }
}
