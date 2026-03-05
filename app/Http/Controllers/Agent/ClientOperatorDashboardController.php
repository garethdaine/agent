<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use App\Models\AgentJob;
use App\Models\AgentJobRun;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ClientOperatorDashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $jobsQuery = AgentJob::query()->whereNull('deleted_at');

        if ($user && ! $user->hasRole('admin')) {
            $jobsQuery->where(function ($q) use ($user) {
                $q->where('user_id', $user->id);
                if (method_exists($user, 'allTeams') && $user->allTeams()->isNotEmpty()) {
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

        return Inertia::render('Agent/ClientOperatorDashboard', [
            'metrics' => [
                'total_jobs' => $jobsCount,
                'runs_today' => $runsToday,
                'successful_runs_today' => $succeededToday,
                'success_rate_percent' => $successRate,
            ],
            'helpUrl' => route('docs.index'),
        ]);
    }
}
