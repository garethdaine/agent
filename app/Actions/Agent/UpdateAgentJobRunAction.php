<?php

declare(strict_types=1);

namespace App\Actions\Agent;

use App\Models\AgentJobRun;

class UpdateAgentJobRunAction
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function execute(AgentJobRun $run, array $attributes): AgentJobRun
    {
        $run->update($attributes);

        return $run;
    }
}
