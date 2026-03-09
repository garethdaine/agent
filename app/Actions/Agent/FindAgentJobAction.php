<?php

declare(strict_types=1);

namespace App\Actions\Agent;

use App\Models\AgentJob;
use App\Models\User;

class FindAgentJobAction
{
    public function execute(User $user, int $id, bool $withTrashed = false): AgentJob
    {
        $query = AgentJob::query()->forUser($user);

        if ($withTrashed) {
            $query->withTrashed();
        }

        /** @var AgentJob */
        return $query->findOrFail($id);
    }
}
