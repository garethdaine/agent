<?php

declare(strict_types=1);

namespace App\Actions\Agent;

use App\Models\AgentJobRun;
use App\Models\User;
use Carbon\CarbonImmutable;

class GetDashboardMetricsAction
{
    /**
     * @return array{
     *     runs_today: int,
     *     success_rate_percent: float,
     *     average_duration_ms: int,
     *     backlog_count: int,
     *     oldest_queued_age_seconds: int,
     *     window_terminal_total: int,
     *     window_succeeded_total: int,
     * }
     */
    public function execute(User $user, int $hours): array
    {
        $now = CarbonImmutable::now('UTC');
        $windowStart = $now->subHours($hours);
        $todayStart = $now->startOfDay();

        $baseQuery = AgentJobRun::query()->forUser($user);
        $windowTerminalQuery = AgentJobRun::query()
            ->forUser($user)
            ->where('created_at', '>=', $windowStart)
            ->whereIn('status', [
                AgentJobRun::STATUS_SUCCEEDED,
                AgentJobRun::STATUS_FAILED,
                AgentJobRun::STATUS_KILLED,
                AgentJobRun::STATUS_TIMED_OUT,
            ]);

        $runsToday = (clone $baseQuery)
            ->where('created_at', '>=', $todayStart)
            ->count();

        $backlogCount = (clone $baseQuery)
            ->where('status', AgentJobRun::STATUS_QUEUED)
            ->count();

        $oldestQueuedAt = (clone $baseQuery)
            ->where('status', AgentJobRun::STATUS_QUEUED)
            ->min('created_at');

        $oldestQueuedAgeSeconds = 0;
        if ($oldestQueuedAt !== null) {
            $oldestQueuedAgeSeconds = CarbonImmutable::parse($oldestQueuedAt, 'UTC')
                ->diffInSeconds($now);
        }

        $windowTerminalTotal = (clone $windowTerminalQuery)->count();
        $windowSucceeded = (clone $windowTerminalQuery)
            ->where('status', AgentJobRun::STATUS_SUCCEEDED)
            ->count();
        $windowAverageDurationMs = (float) ((clone $windowTerminalQuery)->avg('duration_ms') ?? 0);

        $successRatePercent = $windowTerminalTotal > 0
            ? round(($windowSucceeded / $windowTerminalTotal) * 100, 1)
            : 0.0;

        return [
            'runs_today' => $runsToday,
            'success_rate_percent' => $successRatePercent,
            'average_duration_ms' => (int) round($windowAverageDurationMs),
            'backlog_count' => $backlogCount,
            'oldest_queued_age_seconds' => $oldestQueuedAgeSeconds,
            'window_terminal_total' => $windowTerminalTotal,
            'window_succeeded_total' => $windowSucceeded,
        ];
    }
}
