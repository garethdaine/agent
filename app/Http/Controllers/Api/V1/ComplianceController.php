<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AgentJob;
use App\Models\AgentJobRun;
use App\Support\Compliance\ComplianceFlagResolver;
use Illuminate\Http\JsonResponse;

class ComplianceController extends Controller
{
    public function __construct(
        private readonly ComplianceFlagResolver $flagResolver
    ) {}

    public function status(): JsonResponse
    {
        return response()->json([
            'enabled' => config('agent.compliance.enabled', false),
            'enforcement_mode' => $this->flagResolver->getEffectiveMode(),
            'flags' => $this->flagResolver->resolveAll(),
        ]);
    }

    public function metrics(): JsonResponse
    {
        $user = auth()->user();

        $jobIds = AgentJob::query()->forUser($user)->pluck('id');

        $totalJobs = $jobIds->count();

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

        return response()->json([
            'total_jobs' => $totalJobs,
            'total_runs' => $totalRuns,
            'success_rate' => $totalRuns > 0 ? round($successCount / $totalRuns, 4) : null,
            'failure_rate' => $totalRuns > 0 ? round($failCount / $totalRuns, 4) : null,
            'active_runs' => $activeRuns,
        ]);
    }
}
