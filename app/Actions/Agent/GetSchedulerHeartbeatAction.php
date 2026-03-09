<?php

declare(strict_types=1);

namespace App\Actions\Agent;

use App\Models\SchedulerHeartbeat;

class GetSchedulerHeartbeatAction
{
    public function execute(string $source = 'scheduler_dispatch'): ?SchedulerHeartbeat
    {
        /** @var SchedulerHeartbeat|null */
        return SchedulerHeartbeat::query()->where('source', $source)->first();
    }
}
