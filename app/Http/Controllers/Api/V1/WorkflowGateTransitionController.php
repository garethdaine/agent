<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Agent\GateTransitionsIndexRequest;
use App\Services\Telemetry\ActiveBuildFreshnessService;
use App\Services\Telemetry\ActiveProjectionBuildStateService;
use App\Support\Telemetry\ProjectionTable;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class WorkflowGateTransitionController extends Controller
{
    public function index(
        GateTransitionsIndexRequest $request,
        string $workflowKey,
        ActiveProjectionBuildStateService $buildStateService,
        ActiveBuildFreshnessService $freshnessService,
    ): JsonResponse {
        $activeBuildId = $buildStateService->activeProjectionBuildId();
        $freshness = $freshnessService->snapshot();
        $limit = (int) ($request->validated()['limit'] ?? 50);

        $query = DB::table(ProjectionTable::qualified('workflow_gate_transitions'))
            ->where('workflow_key', $workflowKey);

        if ($activeBuildId === null) {
            $query->whereRaw('1 = 0');
        } else {
            $query->where('projection_build_id', $activeBuildId);
        }

        $rows = $query
            ->orderByDesc('transitioned_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get([
                'id',
                'workflow_key',
                'previous_gate_state',
                'new_gate_state',
                'source',
                'reason_code',
                'reason',
                'actor_id',
                'run_id',
                'projection_build_id',
                'metadata_json',
                'transitioned_at',
            ]);

        $transitions = $rows->map(static function (object $row): array {
            return [
                'id' => (int) $row->id,
                'workflow_key' => (string) $row->workflow_key,
                'previous_gate_state' => isset($row->previous_gate_state) ? (string) $row->previous_gate_state : null,
                'new_gate_state' => (string) $row->new_gate_state,
                'source' => (string) $row->source,
                'reason_code' => isset($row->reason_code) ? (string) $row->reason_code : null,
                'reason' => isset($row->reason) ? (string) $row->reason : null,
                'actor_id' => isset($row->actor_id) ? (string) $row->actor_id : null,
                'run_id' => isset($row->run_id) ? (string) $row->run_id : null,
                'projection_build_id' => isset($row->projection_build_id) ? (string) $row->projection_build_id : null,
                'metadata_json' => $row->metadata_json,
                'transitioned_at' => isset($row->transitioned_at) ? (string) $row->transitioned_at : null,
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
                'transitions' => $transitions,
            ],
        ]);
    }
}
