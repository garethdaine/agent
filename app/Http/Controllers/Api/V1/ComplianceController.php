<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Agent\GetComplianceMetricsAction;
use App\Http\Controllers\Controller;
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

    public function metrics(GetComplianceMetricsAction $getMetrics): JsonResponse
    {
        $user = auth()->user();

        $metrics = $getMetrics->execute($user);

        $totalRuns = $metrics['total_runs'];

        return response()->json([
            'total_jobs' => $metrics['total_jobs'],
            'total_runs' => $totalRuns,
            'success_rate' => $totalRuns > 0 ? round($metrics['success_count'] / $totalRuns, 4) : null,
            'failure_rate' => $totalRuns > 0 ? round($metrics['fail_count'] / $totalRuns, 4) : null,
            'active_runs' => $metrics['active_runs'],
        ]);
    }
}
