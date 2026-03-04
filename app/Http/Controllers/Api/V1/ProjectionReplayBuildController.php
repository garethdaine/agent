<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Agent\ActivateReplayBuildRequest;
use App\Services\Telemetry\ActiveBuildFreshnessService;
use App\Services\Telemetry\ActiveProjectionBuildStateService;
use App\Services\Telemetry\ProjectionBuildManager;
use App\Support\Agent\ErrorEnvelope;
use Illuminate\Http\JsonResponse;

class ProjectionReplayBuildController extends Controller
{
    public function activeBuild(
        ActiveProjectionBuildStateService $buildStateService,
        ActiveBuildFreshnessService $freshnessService,
    ): JsonResponse {
        $freshness = $freshnessService->snapshot();

        return response()->json([
            'data' => [
                'active_build_id' => $freshness['active_build_id'],
                'rebuilding_build_id' => $buildStateService->rebuildingBuildId(),
                'active_build_activated_at' => $freshness['active_build_activated_at'],
                'active_build_age_seconds' => $freshness['active_build_age_seconds'],
                'active_build_is_stale' => $freshness['active_build_is_stale'],
                'stale_after_seconds' => $freshness['stale_after_seconds'],
                'reason_state' => $freshness['active_build_id'] === null
                    ? 'missing_active_projection_build'
                    : 'active_projection_build_ready',
            ],
        ]);
    }

    public function store(ProjectionBuildManager $manager): JsonResponse
    {
        $result = $manager->startRebuild();

        if ($result->isConflict()) {
            return ErrorEnvelope::make(
                'PROJECTION_REBUILD_IN_PROGRESS',
                $result->message(),
                409,
                [
                    'active_build_id' => $result->activeBuildId(),
                    'rebuilding_build_id' => $result->rebuildingBuildId(),
                    'message' => $result->message(),
                    'conflict_reason_state' => 'rebuilding_build_exists',
                ]
            );
        }

        return response()->json([
            'data' => [
                'build_id' => $result->buildId(),
                'status' => 'rebuilding',
                'active_build_id' => $result->activeBuildId(),
                'rebuilding_build_id' => $result->rebuildingBuildId(),
                'message' => $result->message(),
            ],
        ], 202);
    }

    public function show(
        string $buildId,
        ProjectionBuildManager $manager,
        ActiveProjectionBuildStateService $buildStateService,
        ActiveBuildFreshnessService $freshnessService,
    ): JsonResponse {
        $build = $manager->findBuild($buildId);
        if ($build === null) {
            return ErrorEnvelope::make(
                'NOT_FOUND',
                'Projection build not found.',
                404,
                ['build_id' => $buildId]
            );
        }

        $freshness = $freshnessService->snapshot();
        $activeBuildId = $buildStateService->activeProjectionBuildId();
        $rebuildingBuildId = $buildStateService->rebuildingBuildId();
        $isActive = $activeBuildId === $build['id'];

        return response()->json([
            'data' => [
                'build_id' => $build['id'],
                'status' => $build['status'],
                'is_active' => $isActive,
                'active_projection_build_id' => $activeBuildId,
                'rebuilding_build_id' => $rebuildingBuildId,
                'activated_at' => $build['activated_at'],
                'created_at' => $build['created_at'],
                'updated_at' => $build['updated_at'],
                'parity_state' => $this->parityStateForStatus($build['status']),
                'active_build_age_seconds' => $isActive ? $freshness['active_build_age_seconds'] : null,
                'lineage' => [
                    'active_build_activated_at' => $freshness['active_build_activated_at'],
                    'active_build_is_stale' => $freshness['active_build_is_stale'],
                    'stale_after_seconds' => $freshness['stale_after_seconds'],
                ],
            ],
        ]);
    }

    public function activate(
        ActivateReplayBuildRequest $request,
        string $buildId,
        ProjectionBuildManager $manager,
        ActiveBuildFreshnessService $freshnessService,
    ): JsonResponse {
        $request->validated();

        $result = $manager->activateBuild($buildId);
        if ($result->isNotFound()) {
            return ErrorEnvelope::make(
                'NOT_FOUND',
                $result->message(),
                404,
                [
                    'build_id' => $result->buildId(),
                    'active_build_id' => $result->activeBuildId(),
                    'rebuilding_build_id' => $result->rebuildingBuildId(),
                ]
            );
        }

        if ($result->isParityRequired()) {
            return ErrorEnvelope::make(
                'REPLAY_PARITY_REQUIRED',
                $result->message(),
                409,
                [
                    'build_id' => $result->buildId(),
                    'required_status' => 'parity_passed',
                    'current_status' => $result->currentStatus(),
                    'active_build_id' => $result->activeBuildId(),
                    'rebuilding_build_id' => $result->rebuildingBuildId(),
                ]
            );
        }

        $freshness = $freshnessService->snapshot();

        return response()->json([
            'data' => [
                'build_id' => $result->buildId(),
                'status' => 'active',
                'active_build_id' => $result->activeBuildId(),
                'previous_active_build_id' => $result->previousActiveBuildId(),
                'rebuilding_build_id' => $result->rebuildingBuildId(),
                'activated_at' => $result->activatedAtIso8601(),
                'active_build_age_seconds' => $freshness['active_build_age_seconds'],
                'message' => $result->message(),
            ],
        ]);
    }

    private function parityStateForStatus(string $status): string
    {
        return match ($status) {
            'parity_passed' => 'passed',
            'active' => 'active',
            default => 'pending',
        };
    }
}
