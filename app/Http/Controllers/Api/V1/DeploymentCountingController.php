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

class DeploymentCountingController extends Controller
{
    public function index(
        ActiveProjectionBuildStateService $buildStateService,
        ActiveBuildFreshnessService $freshnessService,
        WorkflowGovernanceSnapshotService $governanceSnapshotService,
    ): JsonResponse {
        $activeBuildId = $buildStateService->activeProjectionBuildId();
        $freshness = $freshnessService->snapshot();

        if ($activeBuildId === null) {
            return response()->json([
                'data' => [
                    'active_projection_build_id' => null,
                    'active_build_activated_at' => $freshness['active_build_activated_at'],
                    'active_build_age_seconds' => $freshness['active_build_age_seconds'],
                    'active_build_is_stale' => $freshness['active_build_is_stale'],
                    'stale_after_seconds' => $freshness['stale_after_seconds'],
                    'total_workflows' => 0,
                    'counted_workflows' => 0,
                    'workflows' => [],
                ],
            ]);
        }

        $rows = DB::table(ProjectionTable::qualified('workflow_reliability_current'))
            ->where('projection_build_id', $activeBuildId)
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

        $workflows = $rows->map(function (object $row) use ($activeBuildId, $governanceSnapshotService): array {
            $reliabilityRow = [
                'workflow_key' => (string) $row->workflow_key,
                'reliability_score' => isset($row->reliability_score) ? (float) $row->reliability_score : null,
                'degraded_rate' => isset($row->degraded_rate) ? (float) $row->degraded_rate : null,
                'hard_fail_rate' => isset($row->hard_fail_rate) ? (float) $row->hard_fail_rate : null,
                'projection_build_id' => (string) $row->projection_build_id,
            ];

            $governance = $governanceSnapshotService->snapshotForWorkflow(
                workflowKey: (string) $row->workflow_key,
                reliabilityRow: $reliabilityRow,
                activeProjectionBuildId: $activeBuildId,
            );

            return [
                'workflow_key' => (string) $row->workflow_key,
                'reliability_score' => $reliabilityRow['reliability_score'],
                'degraded_rate' => $reliabilityRow['degraded_rate'],
                'hard_fail_rate' => $reliabilityRow['hard_fail_rate'],
                'escalation_events' => isset($row->escalation_events) ? (int) $row->escalation_events : null,
                'projection_build_id' => (string) $row->projection_build_id,
                'source_high_watermark_ingested_at' => isset($row->source_high_watermark_ingested_at)
                    ? (string) $row->source_high_watermark_ingested_at
                    : null,
                'updated_at' => isset($row->updated_at) ? (string) $row->updated_at : null,
                'gate_state' => $governance['gate_state'],
                'gate_state_reason' => $governance['gate_state_reason'],
                'countability_state' => $governance['countability_state'],
                'countability_reason' => $governance['countability_reason'],
                'lineage' => $governance['lineage'],
            ];
        })->values();

        $countedWorkflows = $workflows
            ->filter(static fn (array $workflow): bool => $workflow['countability_state'] === 'countable')
            ->count();

        return response()->json([
            'data' => [
                'active_projection_build_id' => $activeBuildId,
                'active_build_activated_at' => $freshness['active_build_activated_at'],
                'active_build_age_seconds' => $freshness['active_build_age_seconds'],
                'active_build_is_stale' => $freshness['active_build_is_stale'],
                'stale_after_seconds' => $freshness['stale_after_seconds'],
                'total_workflows' => $workflows->count(),
                'counted_workflows' => $countedWorkflows,
                'workflows' => $workflows->all(),
            ],
        ]);
    }
}
