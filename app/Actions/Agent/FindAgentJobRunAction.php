<?php

declare(strict_types=1);

namespace App\Actions\Agent;

use App\Models\AgentJobRun;

class FindAgentJobRunAction
{
    /**
     * @param  array<int, string>  $with
     */
    public function execute(int $id, array $with = []): ?AgentJobRun
    {
        $query = AgentJobRun::query();

        if ($with !== []) {
            $query->with($with);
        }

        /** @var AgentJobRun|null */
        return $query->find($id);
    }
}
