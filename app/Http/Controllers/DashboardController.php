<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Monitoring\GetJobMetricsAction;
use App\Actions\Monitoring\GetRuntimePolicyAction;
use App\Actions\Monitoring\GetSystemSnapshotAction;
use App\Services\Telemetry\ActiveBuildFreshnessService;
use App\Services\Telemetry\ActiveProjectionBuildStateService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(
        Request $request,
        ActiveProjectionBuildStateService $buildStateService,
        ActiveBuildFreshnessService $freshnessService,
        GetJobMetricsAction $jobMetrics,
        GetSystemSnapshotAction $systemSnapshot,
        GetRuntimePolicyAction $runtimePolicy,
    ): Response {
        return Inertia::render('Dashboard', [
            'jobMetrics' => $jobMetrics->execute($request->user()),
            'system' => $systemSnapshot->execute($buildStateService, $freshnessService),
            'runtimePolicy' => $runtimePolicy->execute(),
            'navigation' => [
                'deployments' => '/agent/deployments',
                'escalations' => '/agent/escalations',
                'replayBuilds' => '/agent/replay-builds',
                'budgets' => '/agent/budgets',
            ],
        ]);
    }
}
