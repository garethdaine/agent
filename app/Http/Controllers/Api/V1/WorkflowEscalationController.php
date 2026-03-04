<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Reliability\WorkflowGovernanceSnapshotService;
use App\Services\Telemetry\ActiveBuildFreshnessService;
use App\Services\Telemetry\ActiveProjectionBuildStateService;
use App\Support\Telemetry\ProjectionTable;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class WorkflowEscalationController extends Controller
{
    public function index(
        string $workflowKey,
        ActiveProjectionBuildStateService $buildStateService,
        ActiveBuildFreshnessService $freshnessService,
        WorkflowGovernanceSnapshotService $governanceSnapshotService,
    ): JsonResponse {
        $activeBuildId = $buildStateService->activeProjectionBuildId();
        $freshness = $freshnessService->snapshot();

        $query = DB::table(ProjectionTable::qualified('escalation_incidents'))
            ->where('workflow_key', $workflowKey);

        if ($activeBuildId === null) {
            $query->whereRaw('1 = 0');
        } else {
            $query->where(function ($scope) use ($activeBuildId): void {
                $scope->where('projection_build_id', $activeBuildId)
                    ->orWhereNull('projection_build_id');
            });
        }

        $rows = $query
            ->orderByDesc('last_triggered_at')
            ->orderByDesc('id')
            ->limit(100)
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
                'metadata_json',
            ]);

        $reliabilityRow = $this->activeBuildReliabilityRow($workflowKey, $activeBuildId);

        $governance = $governanceSnapshotService->snapshotForWorkflow(
            workflowKey: $workflowKey,
            reliabilityRow: $reliabilityRow,
            activeProjectionBuildId: $activeBuildId,
        );

        $escalations = $rows->map(static function (object $row): array {
            return [
                'id' => (int) $row->id,
                'workflow_key' => (string) $row->workflow_key,
                'trigger_type' => (string) $row->trigger_type,
                'status' => (string) $row->status,
                'reason_code' => isset($row->reason_code) ? (string) $row->reason_code : null,
                'reason' => isset($row->reason) ? (string) $row->reason : null,
                'opened_at' => isset($row->opened_at) ? (string) $row->opened_at : null,
                'investigating_at' => isset($row->investigating_at) ? (string) $row->investigating_at : null,
                'resolved_at' => isset($row->resolved_at) ? (string) $row->resolved_at : null,
                'last_triggered_at' => isset($row->last_triggered_at) ? (string) $row->last_triggered_at : null,
                'projection_build_id' => isset($row->projection_build_id) ? (string) $row->projection_build_id : null,
                'metadata_json' => $row->metadata_json,
            ];
        })->values()->all();

        return response()->json([
            'data' => [
                'workflow_key' => $workflowKey,
                'active_projection_build_id' => $activeBuildId,
                'active_build_activated_at' => $freshness['active_build_activated_at'],
                'active_build_age_seconds' => $freshness['active_build_age_seconds'],
                'active_build_is_stale' => $freshness['active_build_is_stale'],
                'stale_after_seconds' => $freshness['stale_after_seconds'],
                'gate_state' => $governance['gate_state'],
                'gate_state_reason' => $governance['gate_state_reason'],
                'countability_state' => $governance['countability_state'],
                'countability_reason' => $governance['countability_reason'],
                'lineage' => $governance['lineage'],
                'escalations' => $escalations,
            ],
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function activeBuildReliabilityRow(string $workflowKey, ?string $activeBuildId): ?array
    {
        if ($activeBuildId === null) {
            return null;
        }

        $row = DB::table(ProjectionTable::qualified('workflow_reliability_current'))
            ->where('workflow_key', $workflowKey)
            ->where('projection_build_id', $activeBuildId)
            ->orderByDesc('updated_at')
            ->first([
                'workflow_key',
                'reliability_score',
                'degraded_rate',
                'hard_fail_rate',
                'projection_build_id',
            ]);

        if ($row === null) {
            return null;
        }

        return [
            'workflow_key' => (string) $row->workflow_key,
            'reliability_score' => isset($row->reliability_score) ? (float) $row->reliability_score : null,
            'degraded_rate' => isset($row->degraded_rate) ? (float) $row->degraded_rate : null,
            'hard_fail_rate' => isset($row->hard_fail_rate) ? (float) $row->hard_fail_rate : null,
            'projection_build_id' => (string) $row->projection_build_id,
        ];
    }
}
