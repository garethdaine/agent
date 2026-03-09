<?php

declare(strict_types=1);

namespace App\Actions\Agent;

use App\Models\AgentJob;

class UpdateAgentJobAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(AgentJob $job, array $data): AgentJob
    {
        $job->fill($data);
        $job->save();

        return $job;
    }
}
