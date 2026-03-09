<?php

declare(strict_types=1);

namespace App\Actions\Agent;

use App\Models\AgentJobRun;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;

class ListAgentJobRunsAction
{
    /**
     * @return Collection<int, AgentJobRun>
     */
    public function execute(User $user, int $hours = 24, int $limit = 50): Collection
    {
        /** @var Collection<int, AgentJobRun> */
        return AgentJobRun::query()
            ->forUser($user)
            ->where('created_at', '>=', CarbonImmutable::now('UTC')->subHours($hours))
            ->latest('created_at')
            ->limit($limit)
            ->get();
    }
}
