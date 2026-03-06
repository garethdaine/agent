<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AgentJob;
use App\Models\AgentJobRun;
use App\Models\SchedulerHeartbeat;
use App\Services\Telemetry\ActiveBuildFreshnessService;
use App\Services\Telemetry\ActiveProjectionBuildStateService;
use App\Support\Telemetry\ProjectionTable;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(
        Request $request,
        ActiveProjectionBuildStateService $buildStateService,
        ActiveBuildFreshnessService $freshnessService,
    ): Response {
        return Inertia::render('Dashboard', [
            'jobMetrics' => $this->jobMetrics($request),
            'system' => $this->systemSnapshot($buildStateService, $freshnessService),
            'runtimePolicy' => $this->runtimePolicy(),
            'navigation' => [
                'deployments' => '/agent/deployments',
                'escalations' => '/agent/escalations',
                'replayBuilds' => '/agent/replay-builds',
                'budgets' => '/agent/budgets',
            ],
        ]);
    }

    /**
     * Operator-level job metrics (previously ClientOperatorDashboardController).
     *
     * @return array{total_jobs:int,runs_today:int,successful_runs_today:int,success_rate_percent:float}
     */
    private function jobMetrics(Request $request): array
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

        return [
            'total_jobs' => $jobsCount,
            'runs_today' => $runsToday,
            'successful_runs_today' => $succeededToday,
            'success_rate_percent' => $successRate,
        ];
    }

    /**
     * System health snapshot (previously OperatorPageController@systemOverview).
     *
     * @return array<string,mixed>
     */
    private function systemSnapshot(
        ActiveProjectionBuildStateService $buildStateService,
        ActiveBuildFreshnessService $freshnessService,
    ): array {
        $activeBuildId = $buildStateService->activeProjectionBuildId();
        $freshness = $freshnessService->snapshot();
        $rebuildingBuildId = $buildStateService->rebuildingBuildId();

        $heartbeat = SchedulerHeartbeat::query()->where('source', 'scheduler_dispatch')->first();
        $scheduler = $this->schedulerSnapshot($heartbeat);

        $signals = $this->loadDelayedAndUnobservableSignals($activeBuildId);

        return [
            'activeProjectionBuildId' => $activeBuildId,
            'rebuildingBuildId' => $rebuildingBuildId,
            'freshness' => $freshness,
            'scheduler' => $scheduler,
            'signals' => $signals,
            'reasonState' => $activeBuildId === null
                ? 'missing_active_projection_build'
                : 'active_projection_build_ready',
        ];
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
     * @return array{default_mode:string,modes:list<string>,tool_deny:list<string>,tool_allow:list<string>,tool_allowlist_active:bool}
     */
    private function runtimePolicy(): array
    {
        $toolDeny = config('runtime.tool_deny', []);
        $toolAllow = config('runtime.tool_allow', []);

        return [
            'default_mode' => config('runtime.default_mode', 'safe'),
            'modes' => array_keys(config('runtime.modes', [])),
            'tool_deny' => array_values($toolDeny),
            'tool_allow' => array_values($toolAllow),
            'tool_allowlist_active' => $toolAllow !== [],
        ];
    }

    /**
     * @return array{delayed:array<int,array<string,mixed>>,unobservable:array<int,array<string,mixed>>}
     */
    private function loadDelayedAndUnobservableSignals(?string $activeBuildId): array
    {
        $rows = DB::table(ProjectionTable::qualified('escalation_incidents'))
            ->when(
                $activeBuildId === null,
                static fn ($query) => $query->whereRaw('1 = 0'),
                static fn ($query) => $query->where(function ($scope) use ($activeBuildId): void {
                    $scope->where('projection_build_id', $activeBuildId)
                        ->orWhereNull('projection_build_id');
                })
            )
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
}
