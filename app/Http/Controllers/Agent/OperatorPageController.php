<?php

declare(strict_types=1);

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use App\Models\AgentJob;
use App\Models\SchedulerHeartbeat;
use App\Repositories\Projection\WorkflowReliabilityCurrentRepository;
use App\Services\Reliability\WorkflowGovernanceSnapshotService;
use App\Services\Telemetry\ActiveBuildFreshnessService;
use App\Services\Telemetry\ActiveProjectionBuildStateService;
use App\Support\Telemetry\ProjectionTable;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class OperatorPageController extends Controller
{
    public function deployments(
        ActiveProjectionBuildStateService $buildStateService,
        ActiveBuildFreshnessService $freshnessService,
        WorkflowGovernanceSnapshotService $governanceSnapshotService,
    ): Response {
        $activeBuildId = $buildStateService->activeProjectionBuildId();
        $freshness = $freshnessService->snapshot();
        $monthUtc = now('UTC')->format('Y-m');

        $reliabilityRows = DB::table(ProjectionTable::qualified('workflow_reliability_current'))
            ->when(
                $activeBuildId === null,
                static fn ($query) => $query->whereRaw('1 = 0'),
                static fn ($query) => $query->where('projection_build_id', $activeBuildId)
            )
            ->orderBy('workflow_key')
            ->get([
                'workflow_key',
                'reliability_score',
                'degraded_rate',
                'hard_fail_rate',
                'escalation_events',
                'projection_build_id',
                'source_high_watermark_ingested_at',
                'updated_at',
            ]);

        $costByWorkflow = DB::table(ProjectionTable::qualified('workflow_cost_rollups'))
            ->selectRaw('workflow_key, COALESCE(SUM(canonical_cost_usd), 0) AS canonical_cost_usd')
            ->where('budget_month_utc', $monthUtc)
            ->when(
                $activeBuildId === null,
                static fn ($query) => $query->whereRaw('1 = 0'),
                static fn ($query) => $query->where('projection_build_id', $activeBuildId)
            )
            ->groupBy('workflow_key')
            ->get()
            ->keyBy('workflow_key');

        $budgetByWorkflow = DB::table('workflow_monthly_budget_policies')
            ->where('is_active', true)
            ->get(['workflow_key', 'monthly_budget_usd'])
            ->keyBy('workflow_key');

        $workflows = $reliabilityRows->map(function (object $row) use (
            $activeBuildId,
            $budgetByWorkflow,
            $costByWorkflow,
            $governanceSnapshotService,
        ): array {
            $workflowKey = (string) $row->workflow_key;

            $reliability = [
                'workflow_key' => $workflowKey,
                'reliability_score' => isset($row->reliability_score) ? (float) $row->reliability_score : null,
                'degraded_rate' => isset($row->degraded_rate) ? (float) $row->degraded_rate : null,
                'hard_fail_rate' => isset($row->hard_fail_rate) ? (float) $row->hard_fail_rate : null,
                'projection_build_id' => isset($row->projection_build_id) ? (string) $row->projection_build_id : null,
            ];

            $governance = $governanceSnapshotService->snapshotForWorkflow(
                workflowKey: $workflowKey,
                reliabilityRow: $reliability,
                activeProjectionBuildId: $activeBuildId,
            );

            $monthlyBudget = $this->toNullableFloat(data_get($budgetByWorkflow->get($workflowKey), 'monthly_budget_usd'));
            $monthlyCost = $this->toNullableFloat(data_get($costByWorkflow->get($workflowKey), 'canonical_cost_usd')) ?? 0.0;

            $budgetUtilization = null;
            if ($monthlyBudget !== null && $monthlyBudget > 0) {
                $budgetUtilization = round(($monthlyCost / $monthlyBudget) * 100, 2);
            }

            $countabilityLabel = $governance['countability_state'];
            if (
                $governance['countability_state'] === 'not_countable'
                && $governance['countability_reason'] === 'incident_open'
            ) {
                $countabilityLabel = 'not countable (incident open)';
            }

            return [
                'workflow_key' => $workflowKey,
                'reliability_score' => $reliability['reliability_score'],
                'degraded_rate' => $reliability['degraded_rate'],
                'hard_fail_rate' => $reliability['hard_fail_rate'],
                'budget_utilization_percent' => $budgetUtilization,
                'escalation_events' => isset($row->escalation_events) ? (int) $row->escalation_events : 0,
                'gate_state' => $governance['gate_state'],
                'countability_state' => $governance['countability_state'],
                'countability_reason' => $governance['countability_reason'],
                'countability_label' => $countabilityLabel,
                'links' => [
                    'detail' => '/agent/deployments/'.$workflowKey,
                    'reliability' => '/agent/api/v1/workflows/'.$workflowKey.'/reliability',
                ],
            ];
        })->values();

        return Inertia::render('Agent/Deployments/Index', [
            'activeProjectionBuildId' => $activeBuildId,
            'freshness' => $freshness,
            'navigation' => [
                'systemOverview' => '/agent/system-overview',
                'escalations' => '/agent/escalations',
                'budgets' => '/agent/budgets',
                'replayBuilds' => '/agent/replay-builds',
            ],
            'workflows' => $workflows,
        ]);
    }

    public function deployment(
        Request $request,
        string $workflowKey,
        WorkflowReliabilityCurrentRepository $repository,
        ActiveBuildFreshnessService $freshnessService,
        WorkflowGovernanceSnapshotService $governanceSnapshotService,
    ): Response {
        $activeBuildId = $repository->activeProjectionBuildId();
        $freshness = $freshnessService->snapshot();

        $reliability = $repository->findByWorkflowKey($workflowKey);

        $governance = $governanceSnapshotService->snapshotForWorkflow(
            workflowKey: $workflowKey,
            reliabilityRow: $reliability,
            activeProjectionBuildId: $activeBuildId,
        );

        $job = AgentJob::query()
            ->whereNull('deleted_at')
            ->where('workflow_key', $workflowKey)
            ->first();

        $canPauseResume = $job !== null && Gate::forUser($request->user())->allows('workflow.resume', $job);

        $countabilityLabel = $governance['countability_state'];
        if (
            $governance['countability_state'] === 'not_countable'
            && $governance['countability_reason'] === 'incident_open'
        ) {
            $countabilityLabel = 'not countable (incident open)';
        }

        return Inertia::render('Agent/Deployments/Show', [
            'workflowKey' => $workflowKey,
            'activeProjectionBuildId' => $activeBuildId,
            'freshness' => $freshness,
            'reliability' => $reliability,
            'governanceState' => [
                'gateState' => $governance['gate_state'],
                'countabilityState' => $governance['countability_state'],
                'countabilityReason' => $governance['countability_reason'],
                'countabilityLabel' => $countabilityLabel,
            ],
            'deepLinks' => [
                'reliability' => '/agent/api/v1/workflows/'.$workflowKey.'/reliability',
                'cost' => '/agent/api/v1/workflows/'.$workflowKey.'/cost',
                'attemptLineage' => '/agent/monitor?workflow='.$workflowKey,
                'gateTransitions' => '/agent/api/v1/workflows/'.$workflowKey.'/gate-transitions',
                'escalationHistory' => '/agent/api/v1/workflows/'.$workflowKey.'/escalations',
                'replayBuilds' => '/agent/replay-builds',
            ],
            'navigation' => [
                'deployments' => '/agent/deployments',
                'systemOverview' => '/agent/system-overview',
                'escalations' => '/agent/escalations',
                'budgets' => '/agent/budgets',
                'replayBuilds' => '/agent/replay-builds',
            ],
            'governance' => [
                'canPauseResume' => $canPauseResume,
                'canManageEscalations' => $this->canManageEscalations($request),
                'canManageReplay' => $this->canManageReplay($request),
            ],
        ]);
    }

    public function systemOverview(
        ActiveProjectionBuildStateService $buildStateService,
        ActiveBuildFreshnessService $freshnessService,
    ): Response {
        $activeBuildId = $buildStateService->activeProjectionBuildId();
        $freshness = $freshnessService->snapshot();
        $rebuildingBuildId = $buildStateService->rebuildingBuildId();

        $heartbeat = SchedulerHeartbeat::query()->where('source', 'scheduler_dispatch')->first();
        $scheduler = $this->schedulerSnapshot($heartbeat);

        $signals = $this->loadDelayedAndUnobservableSignals($activeBuildId);

        return Inertia::render('Agent/SystemOverview/Show', [
            'activeProjectionBuildId' => $activeBuildId,
            'rebuildingBuildId' => $rebuildingBuildId,
            'freshness' => $freshness,
            'scheduler' => $scheduler,
            'signals' => $signals,
            'reasonState' => $activeBuildId === null
                ? 'missing_active_projection_build'
                : 'active_projection_build_ready',
            'navigation' => [
                'deployments' => '/agent/deployments',
                'escalations' => '/agent/escalations',
                'replayBuilds' => '/agent/replay-builds',
                'budgets' => '/agent/budgets',
            ],
        ]);
    }

    public function escalations(
        Request $request,
        ActiveProjectionBuildStateService $buildStateService,
    ): Response {
        $activeBuildId = $buildStateService->activeProjectionBuildId();

        $rows = $this->incidentQuery($activeBuildId)
            ->orderByDesc('last_triggered_at')
            ->orderByDesc('id')
            ->limit(200)
            ->get([
                'id',
                'workflow_key',
                'trigger_type',
                'status',
                'reason_code',
                'reason',
                'opened_at',
                'investigating_at',
                'resolved_at',
                'last_triggered_at',
                'projection_build_id',
            ]);

        $incidents = $rows->map(fn (object $row): array => [
            'id' => (int) $row->id,
            'workflow_key' => (string) $row->workflow_key,
            'trigger_type' => (string) $row->trigger_type,
            'status' => (string) $row->status,
            'reason_code' => $row->reason_code,
            'reason' => $row->reason,
            'opened_at' => isset($row->opened_at) ? (string) $row->opened_at : null,
            'investigating_at' => isset($row->investigating_at) ? (string) $row->investigating_at : null,
            'resolved_at' => isset($row->resolved_at) ? (string) $row->resolved_at : null,
            'last_triggered_at' => isset($row->last_triggered_at) ? (string) $row->last_triggered_at : null,
            'projection_build_id' => isset($row->projection_build_id) ? (string) $row->projection_build_id : null,
            'links' => [
                'deployment' => '/agent/deployments/'.(string) $row->workflow_key,
                'history' => '/agent/api/v1/workflows/'.(string) $row->workflow_key.'/escalations',
            ],
        ])->values();

        return Inertia::render('Agent/Escalations/Index', [
            'activeProjectionBuildId' => $activeBuildId,
            'incidents' => $incidents,
            'delayedSignals' => $incidents->where('reason_code', 'telemetry_delayed')->values(),
            'unobservableSignals' => $incidents->where('reason_code', 'telemetry_unobservable')->values(),
            'governance' => [
                'canManageEscalations' => $this->canManageEscalations($request),
            ],
            'navigation' => [
                'deployments' => '/agent/deployments',
                'systemOverview' => '/agent/system-overview',
            ],
        ]);
    }

    public function budgets(
        ActiveProjectionBuildStateService $buildStateService,
        WorkflowGovernanceSnapshotService $governanceSnapshotService,
    ): Response {
        $activeBuildId = $buildStateService->activeProjectionBuildId();
        $monthUtc = now('UTC')->format('Y-m');

        $policies = DB::table('workflow_monthly_budget_policies')
            ->where('is_active', true)
            ->get([
                'workflow_key',
                'monthly_budget_usd',
                'warning_threshold_percent',
                'enforcement_threshold_percent',
            ]);

        $costRows = DB::table(ProjectionTable::qualified('workflow_cost_rollups'))
            ->selectRaw('workflow_key, COALESCE(SUM(canonical_cost_usd), 0) AS canonical_cost_usd')
            ->where('budget_month_utc', $monthUtc)
            ->when(
                $activeBuildId === null,
                static fn ($query) => $query->whereRaw('1 = 0'),
                static fn ($query) => $query->where('projection_build_id', $activeBuildId)
            )
            ->groupBy('workflow_key')
            ->get()
            ->keyBy('workflow_key');

        $reliabilityRows = DB::table(ProjectionTable::qualified('workflow_reliability_current'))
            ->when(
                $activeBuildId === null,
                static fn ($query) => $query->whereRaw('1 = 0'),
                static fn ($query) => $query->where('projection_build_id', $activeBuildId)
            )
            ->get([
                'workflow_key',
                'reliability_score',
                'degraded_rate',
                'hard_fail_rate',
                'projection_build_id',
            ])
            ->keyBy('workflow_key');

        $workflowKeys = collect($policies->pluck('workflow_key'))
            ->merge($costRows->keys())
            ->merge($reliabilityRows->keys())
            ->filter(static fn ($key): bool => is_string($key) && $key !== '')
            ->unique()
            ->sort()
            ->values();

        $rows = $workflowKeys->map(function (string $workflowKey) use (
            $activeBuildId,
            $costRows,
            $governanceSnapshotService,
            $policies,
            $reliabilityRows,
        ): array {
            $policy = $policies->firstWhere('workflow_key', $workflowKey);
            $monthlyBudget = $this->toNullableFloat(data_get($policy, 'monthly_budget_usd'));
            $monthlyCost = $this->toNullableFloat(data_get($costRows->get($workflowKey), 'canonical_cost_usd')) ?? 0.0;

            $budgetUtilization = null;
            if ($monthlyBudget !== null && $monthlyBudget > 0) {
                $budgetUtilization = round(($monthlyCost / $monthlyBudget) * 100, 2);
            }

            $reliabilityRow = $reliabilityRows->get($workflowKey);
            $reliability = $reliabilityRow === null
                ? null
                : [
                    'workflow_key' => $workflowKey,
                    'reliability_score' => $this->toNullableFloat(data_get($reliabilityRow, 'reliability_score')),
                    'degraded_rate' => $this->toNullableFloat(data_get($reliabilityRow, 'degraded_rate')),
                    'hard_fail_rate' => $this->toNullableFloat(data_get($reliabilityRow, 'hard_fail_rate')),
                    'projection_build_id' => data_get($reliabilityRow, 'projection_build_id'),
                ];

            $governance = $governanceSnapshotService->snapshotForWorkflow(
                workflowKey: $workflowKey,
                reliabilityRow: $reliability,
                activeProjectionBuildId: $activeBuildId,
            );

            $countabilityLabel = $governance['countability_state'];
            if (
                $governance['countability_state'] === 'not_countable'
                && $governance['countability_reason'] === 'incident_open'
            ) {
                $countabilityLabel = 'not countable (incident open)';
            }

            return [
                'workflow_key' => $workflowKey,
                'budget_month_utc' => now('UTC')->format('Y-m'),
                'monthly_budget_usd' => $monthlyBudget,
                'monthly_canonical_cost_usd' => $monthlyCost,
                'budget_utilization_percent' => $budgetUtilization,
                'warning_threshold_percent' => $this->toNullableFloat(data_get($policy, 'warning_threshold_percent')),
                'enforcement_threshold_percent' => $this->toNullableFloat(data_get($policy, 'enforcement_threshold_percent')),
                'countability_state' => $governance['countability_state'],
                'countability_reason' => $governance['countability_reason'],
                'countability_label' => $countabilityLabel,
                'links' => [
                    'deployment' => '/agent/deployments/'.$workflowKey,
                    'cost' => '/agent/api/v1/workflows/'.$workflowKey.'/cost',
                ],
            ];
        })->values();

        return Inertia::render('Agent/Budgets/Index', [
            'activeProjectionBuildId' => $activeBuildId,
            'monthUtc' => $monthUtc,
            'rows' => $rows,
            'navigation' => [
                'deployments' => '/agent/deployments',
                'systemOverview' => '/agent/system-overview',
            ],
        ]);
    }

    public function replayBuilds(
        Request $request,
        ActiveProjectionBuildStateService $buildStateService,
        ActiveBuildFreshnessService $freshnessService,
    ): Response {
        $activeBuildId = $buildStateService->activeProjectionBuildId();
        $rebuildingBuildId = $buildStateService->rebuildingBuildId();
        $freshness = $freshnessService->snapshot();

        $builds = DB::table(ProjectionTable::qualified('telemetry_projection_builds'))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(200)
            ->get([
                'id',
                'status',
                'activated_at',
                'created_at',
                'updated_at',
            ])
            ->map(function (object $row) use ($activeBuildId): array {
                $buildId = (string) $row->id;

                return [
                    'id' => $buildId,
                    'status' => (string) $row->status,
                    'is_active' => $buildId === $activeBuildId,
                    'is_serving' => $buildId === $activeBuildId,
                    'activated_at' => isset($row->activated_at) ? (string) $row->activated_at : null,
                    'created_at' => isset($row->created_at) ? (string) $row->created_at : null,
                    'updated_at' => isset($row->updated_at) ? (string) $row->updated_at : null,
                    'links' => [
                        'detail' => '/agent/replay-builds/'.$buildId,
                    ],
                ];
            })
            ->values();

        return Inertia::render('Agent/ReplayBuilds/Index', [
            'activeProjectionBuildId' => $activeBuildId,
            'rebuildingBuildId' => $rebuildingBuildId,
            'freshness' => $freshness,
            'builds' => $builds,
            'governance' => [
                'canManageReplay' => $this->canManageReplay($request),
            ],
            'navigation' => [
                'deployments' => '/agent/deployments',
                'systemOverview' => '/agent/system-overview',
            ],
        ]);
    }

    public function replayBuild(
        Request $request,
        string $buildId,
        ActiveProjectionBuildStateService $buildStateService,
        ActiveBuildFreshnessService $freshnessService,
    ): Response {
        $build = DB::table(ProjectionTable::qualified('telemetry_projection_builds'))
            ->where('id', $buildId)
            ->first([
                'id',
                'status',
                'activated_at',
                'created_at',
                'updated_at',
            ]);

        abort_if($build === null, 404);

        $activeBuildId = $buildStateService->activeProjectionBuildId();
        $freshness = $freshnessService->snapshot();
        $isActive = (string) $build->id === $activeBuildId;

        return Inertia::render('Agent/ReplayBuilds/Show', [
            'build' => [
                'id' => (string) $build->id,
                'status' => (string) $build->status,
                'isActive' => $isActive,
                'isServing' => $isActive,
                'active_build_age_seconds' => $isActive ? $freshness['active_build_age_seconds'] : null,
                'active_build_is_stale' => $isActive ? $freshness['active_build_is_stale'] : null,
                'stale_after_seconds' => $freshness['stale_after_seconds'],
                'activated_at' => isset($build->activated_at) ? (string) $build->activated_at : null,
                'created_at' => isset($build->created_at) ? (string) $build->created_at : null,
                'updated_at' => isset($build->updated_at) ? (string) $build->updated_at : null,
            ],
            'activeProjectionBuildId' => $activeBuildId,
            'rebuildingBuildId' => $buildStateService->rebuildingBuildId(),
            'governance' => [
                'canManageReplay' => $this->canManageReplay($request),
            ],
            'navigation' => [
                'replayBuilds' => '/agent/replay-builds',
                'systemOverview' => '/agent/system-overview',
            ],
        ]);
    }

    /**
     * @return array{status:string,last_seen_at:?string,age_seconds:?int,meta:?array<string,mixed>}
     */
    private function schedulerSnapshot(?SchedulerHeartbeat $heartbeat): array
    {
        if ($heartbeat === null) {
            return [
                'status' => 'unknown',
                'last_seen_at' => null,
                'age_seconds' => null,
                'meta' => null,
            ];
        }

        $lastSeen = CarbonImmutable::parse($heartbeat->last_seen_at, 'UTC')->utc();
        $ageSeconds = $lastSeen->diffInSeconds(CarbonImmutable::now('UTC'));

        $status = 'healthy';
        if ($ageSeconds > 300) {
            $status = 'down';
        } elseif ($ageSeconds > 90) {
            $status = 'degraded';
        }

        return [
            'status' => $status,
            'last_seen_at' => $lastSeen->toIso8601String(),
            'age_seconds' => $ageSeconds,
            'meta' => is_array($heartbeat->meta_json) ? $heartbeat->meta_json : null,
        ];
    }

    /**
     * @return array{delayed:array<int,array<string,mixed>>,unobservable:array<int,array<string,mixed>>}
     */
    private function loadDelayedAndUnobservableSignals(?string $activeBuildId): array
    {
        $rows = $this->incidentQuery($activeBuildId)
            ->whereIn('reason_code', ['telemetry_delayed', 'telemetry_unobservable'])
            ->orderByDesc('last_triggered_at')
            ->limit(100)
            ->get([
                'id',
                'workflow_key',
                'trigger_type',
                'status',
                'reason_code',
                'reason',
                'last_triggered_at',
            ]);

        $signals = $rows->map(static fn (object $row): array => [
            'id' => (int) $row->id,
            'workflow_key' => (string) $row->workflow_key,
            'trigger_type' => (string) $row->trigger_type,
            'status' => (string) $row->status,
            'reason_code' => isset($row->reason_code) ? (string) $row->reason_code : null,
            'reason' => isset($row->reason) ? (string) $row->reason : null,
            'last_triggered_at' => isset($row->last_triggered_at) ? (string) $row->last_triggered_at : null,
        ]);

        return [
            'delayed' => $signals->where('reason_code', 'telemetry_delayed')->values()->all(),
            'unobservable' => $signals->where('reason_code', 'telemetry_unobservable')->values()->all(),
        ];
    }

    private function incidentQuery(?string $activeBuildId)
    {
        return DB::table(ProjectionTable::qualified('escalation_incidents'))
            ->when(
                $activeBuildId === null,
                static fn ($query) => $query->whereRaw('1 = 0'),
                static fn ($query) => $query->where(function ($scope) use ($activeBuildId): void {
                    $scope->where('projection_build_id', $activeBuildId)
                        ->orWhereNull('projection_build_id');
                })
            );
    }

    private function canManageEscalations(Request $request): bool
    {
        $user = $request->user();
        if ($user === null) {
            return false;
        }

        if ($user->hasRole('admin')) {
            return true;
        }

        return in_array((string) $user->id, array_map('strval', config('agent.roles.central_on_call_user_ids', [])), true);
    }

    private function canManageReplay(Request $request): bool
    {
        return (bool) $request->user()?->hasRole('admin');
    }

    private function toNullableFloat(mixed $value): ?float
    {
        if ($value === null) {
            return null;
        }

        if (! is_numeric($value)) {
            return null;
        }

        return (float) $value;
    }
}
