<?php

declare(strict_types=1);

namespace App\Actions\Agent;

use App\Models\AgentJobRun;

class CreateAgentJobRunAction
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function execute(array $attributes): AgentJobRun
    {
        /** @var AgentJobRun */
        return AgentJobRun::query()->create($attributes);
    }
}
