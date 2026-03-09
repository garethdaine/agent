<?php

declare(strict_types=1);

namespace App\Actions\Monitoring;

use App\Models\SchedulerHeartbeat;
use App\Services\Telemetry\ActiveBuildFreshnessService;
use App\Services\Telemetry\ActiveProjectionBuildStateService;
use App\Support\Telemetry\ProjectionTable;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class GetSystemSnapshotAction
{
    /**
     * @return array<string, mixed>
     */
    public function execute(
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
     * @return array{status: string, last_seen_at: ?string, age_seconds: ?int, meta: ?array<string, mixed>}
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

        return [ // @phpstan-ignore return.type
            'status' => $status,
            'last_seen_at' => $lastSeen->toIso8601String(),
            'age_seconds' => $ageSeconds,
            'meta' => is_array($heartbeat->meta_json) ? $heartbeat->meta_json : null,
        ];
    }

    /**
     * @return array{delayed: array<int, array<string, mixed>>, unobservable: array<int, array<string, mixed>>}
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
