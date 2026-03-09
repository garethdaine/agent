<?php

declare(strict_types=1);

namespace App\Actions\Monitoring;

use App\Models\AgentJobRun;
use App\Models\User;

class GetRecentActivityAction
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function execute(User $user): array
    {
        $recentRuns = AgentJobRun::query()
            ->where('user_id', $user->id)
            ->whereNotNull('finished_at')
            ->with('job:id,name')
            ->latest('finished_at')
            ->limit(10)
            ->get();

        return $recentRuns->map(fn (AgentJobRun $run) => [
            'type' => match ($run->status) {
                'succeeded' => 'success',
                'failed', 'timed_out', 'killed' => 'failure',
                default => 'info',
            },
            'label' => ($run->job->name ?? 'Job').' — '.str_replace('_', ' ', $run->status),
            'status' => $run->status,
            'job_name' => $run->job?->name,
            'duration_ms' => $run->duration_ms,
            'timestamp' => $run->finished_at?->toIso8601String(),
        ])->toArray();
    }
}
