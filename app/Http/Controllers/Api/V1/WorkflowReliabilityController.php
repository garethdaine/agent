<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Repositories\Projection\WorkflowReliabilityCurrentRepository;
use App\Services\Reliability\WorkflowGovernanceSnapshotService;
use App\Services\Telemetry\ActiveBuildFreshnessService;
use App\Support\Agent\ErrorEnvelope;
use Illuminate\Http\JsonResponse;

class WorkflowReliabilityController extends Controller
{
    public function show(
        string $workflowKey,
        WorkflowReliabilityCurrentRepository $repository,
        ActiveBuildFreshnessService $freshnessService,
        WorkflowGovernanceSnapshotService $governanceSnapshotService,
    ): JsonResponse {
        $activeBuildId = $repository->activeProjectionBuildId();
        $freshness = $freshnessService->snapshot();
        $row = $repository->findByWorkflowKey($workflowKey);

        if ($row === null) {
            $reasonState = $activeBuildId === null
                ? 'missing_active_projection_build'
                : 'missing_active_build_projection_row';

            return ErrorEnvelope::make(
                'NOT_FOUND',
                'No active-build reliability projection row was found for workflow '.$workflowKey.'.',
                404,
                [
                    'workflow_key' => [$workflowKey],
                    'active_projection_build_id' => $activeBuildId,
                    'reason_state' => $reasonState,
                ]
            );
        }

        $governance = $governanceSnapshotService->snapshotForWorkflow(
            workflowKey: $workflowKey,
            reliabilityRow: $row,
            activeProjectionBuildId: $activeBuildId,
        );

        return response()->json([
            'data' => array_merge($row, [
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
            ]),
        ]);
    }
}
