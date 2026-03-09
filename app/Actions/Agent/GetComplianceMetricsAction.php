<?php

declare(strict_types=1);

namespace App\Actions\Agent;

use App\Models\AgentJob;
use App\Models\AgentJobRun;
use App\Models\User;

class GetComplianceMetricsAction
{
    /**
     * @return array{
     *     total_jobs: int,
     *     total_runs: int,
     *     success_count: int,
     *     fail_count: int,
     *     active_runs: int,
     * }
     */
    public function execute(User $user): array
    {
        $jobIds = AgentJob::query()->forUser($user)->pluck('id');

        $totalRuns = AgentJobRun::whereIn('agent_job_id', $jobIds)->count();

        $successCount = AgentJobRun::whereIn('agent_job_id', $jobIds)
            ->where('status', AgentJobRun::STATUS_SUCCEEDED)
            ->count();

        $failCount = AgentJobRun::whereIn('agent_job_id', $jobIds)
            ->where('status', AgentJobRun::STATUS_FAILED)
            ->count();

        $activeRuns = AgentJobRun::whereIn('agent_job_id', $jobIds)
            ->whereIn('status', AgentJobRun::ACTIVE_STATUSES)
            ->count();

        return [
            'total_jobs' => $jobIds->count(),
            'total_runs' => $totalRuns,
            'success_count' => $successCount,
            'fail_count' => $failCount,
            'active_runs' => $activeRuns,
        ];
    }
}
