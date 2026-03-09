<?php

declare(strict_types=1);

namespace App\Actions\Monitoring;

use App\Models\AgentJobRun;
use App\Models\SchedulerHeartbeat;

class GetSystemStateAction
{
    /**
     * @return array{scheduler_healthy: bool, queue_lag_seconds: int, rate_limited: bool, active_runs: int, runtime_mode: string}
     */
    public function execute(): array
    {
        $latestHeartbeat = SchedulerHeartbeat::query()
            ->latest('last_seen_at')
            ->first();

        $activeRunCount = AgentJobRun::query()
            ->whereIn('status', ['queued', 'starting', 'running'])
            ->count();

        $schedulerHealthy = $latestHeartbeat
            && $latestHeartbeat->last_seen_at
            && $latestHeartbeat->last_seen_at->diffInMinutes(now()) < 3;

        $queuedOldest = AgentJobRun::query()
            ->where('status', 'queued')
            ->orderBy('created_at')
            ->value('created_at');

        $queueLag = $queuedOldest ? (int) now()->diffInSeconds($queuedOldest) : 0;

        return [
            'scheduler_healthy' => $schedulerHealthy,
            'queue_lag_seconds' => $queueLag,
            'rate_limited' => false,
            'active_runs' => $activeRunCount,
            'runtime_mode' => config('runtime.default_mode', 'standard'),
        ];
    }
}
