<?php

declare(strict_types=1);

namespace App\Actions\Monitoring;

use App\Models\AgentJob;
use App\Models\AgentJobRun;
use App\Models\User;

class GetJobMetricsAction
{
    /**
     * @return array{total_jobs: int, runs_today: int, successful_runs_today: int, success_rate_percent: float}
     */
    public function execute(User $user): array
    {
        $jobsQuery = AgentJob::query()->whereNull('deleted_at');

        if (! $user->hasRole('admin')) {
            $jobsQuery->where(function ($q) use ($user) {
                $q->where('user_id', $user->id);
                if (method_exists($user, 'allTeams') && $user->allTeams()->isNotEmpty()) { // @phpstan-ignore function.alreadyNarrowedType
                    $teamIds = $user->allTeams()->pluck('id')->all();
                    $q->orWhereIn('team_id', $teamIds);
                }
            });
        }

        $jobIds = $jobsQuery->select('id')->pluck('id')->all();
        $jobsCount = count($jobIds);

        $todayStart = now()->startOfDay();
        $runsToday = 0;
        $succeededToday = 0;

        if ($jobIds !== []) {
            $runsQuery = AgentJobRun::query()->whereIn('agent_job_id', $jobIds);
            $runsToday = (clone $runsQuery)->where('created_at', '>=', $todayStart)->count();
            $succeededToday = (clone $runsQuery)
                ->where('created_at', '>=', $todayStart)
                ->where('status', AgentJobRun::STATUS_SUCCEEDED)
                ->count();
        }

        $successRate = $runsToday > 0 ? round(100 * $succeededToday / $runsToday, 1) : 0.0;

        return [
            'total_jobs' => $jobsCount,
            'runs_today' => $runsToday,
            'successful_runs_today' => $succeededToday,
            'success_rate_percent' => $successRate,
        ];
    }
}
