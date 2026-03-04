<?php

declare(strict_types=1);

namespace App\Services\Reliability;

use App\Support\Telemetry\ProjectionTable;
use Illuminate\Support\Facades\DB;

final class WorkflowGovernanceSnapshotService
{
    /**
     * @param  array<string, mixed>|null  $reliabilityRow
     * @return array{
     *     gate_state:?string,
     *     gate_state_reason:?string,
     *     countability_state:string,
     *     countability_reason:string,
     *     lineage:array{
     *         gate_transition_id:?int,
     *         gate_transition_projection_build_id:?string,
     *         gate_transitioned_at:?string
     *     }
     * }
     */
    public function snapshotForWorkflow(
        string $workflowKey,
        ?array $reliabilityRow,
        ?string $activeProjectionBuildId,
    ): array {
        $transition = $this->latestGateTransition($workflowKey, $activeProjectionBuildId);

        $gateState = $transition['new_gate_state'] ?? null;
        $gateStateReason = $transition['reason_code'] ?? $transition['reason'] ?? null;

        [$countabilityState, $countabilityReason] = $this->resolveCountability(
            workflowKey: $workflowKey,
            reliabilityRow: $reliabilityRow,
            activeProjectionBuildId: $activeProjectionBuildId,
        );

        return [
            'gate_state' => $gateState,
            'gate_state_reason' => $gateStateReason,
            'countability_state' => $countabilityState,
            'countability_reason' => $countabilityReason,
            'lineage' => [
                'gate_transition_id' => isset($transition['id']) ? (int) $transition['id'] : null,
                'gate_transition_projection_build_id' => $transition['projection_build_id'] ?? null,
                'gate_transitioned_at' => $transition['transitioned_at'] ?? null,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>|null  $reliabilityRow
     * @return array{0:string,1:string}
     */
    private function resolveCountability(
        string $workflowKey,
        ?array $reliabilityRow,
        ?string $activeProjectionBuildId,
    ): array {
        if ($this->hasOpenIncident($workflowKey, $activeProjectionBuildId)) {
            return ['not_countable', 'incident_open'];
        }

        if ($reliabilityRow === null || ! isset($reliabilityRow['reliability_score'])) {
            return ['insufficient_data', 'missing_reliability_projection'];
        }

        $reliabilityScore = (float) $reliabilityRow['reliability_score'];
        $degradedRate = isset($reliabilityRow['degraded_rate'])
            ? (float) $reliabilityRow['degraded_rate']
            : null;

        $reliabilityThreshold = (float) config('agent.reliability.weighted_reliability_threshold', 95.0);
        $degradedThreshold = (float) config('agent.reliability.degraded_rate_threshold', 3.0);

        if (
            $reliabilityScore >= $reliabilityThreshold
            && ($degradedRate === null || $degradedRate <= $degradedThreshold)
        ) {
            return ['countable', 'reliability_gate_passed'];
        }

        return ['not_countable', 'reliability_gate_not_met'];
    }

    private function hasOpenIncident(string $workflowKey, ?string $activeProjectionBuildId): bool
    {
        $query = DB::table(ProjectionTable::qualified('escalation_incidents'))
            ->where('workflow_key', $workflowKey)
            ->whereIn('status', ['open', 'investigating']);

        if ($activeProjectionBuildId !== null) {
            $query->where(function ($scope) use ($activeProjectionBuildId): void {
                $scope->where('projection_build_id', $activeProjectionBuildId)
                    ->orWhereNull('projection_build_id');
            });
        }

        return $query->exists();
    }

    /**
     * @return array{id:int,new_gate_state:string,reason_code:?string,reason:?string,projection_build_id:?string,transitioned_at:?string}|null
     */
    private function latestGateTransition(string $workflowKey, ?string $activeProjectionBuildId): ?array
    {
        if ($activeProjectionBuildId === null) {
            return null;
        }

        $row = DB::table(ProjectionTable::qualified('workflow_gate_transitions'))
            ->where('workflow_key', $workflowKey)
            ->where('projection_build_id', $activeProjectionBuildId)
            ->orderByDesc('transitioned_at')
            ->orderByDesc('id')
            ->first([
                'id',
                'new_gate_state',
                'reason_code',
                'reason',
                'projection_build_id',
                'transitioned_at',
            ]);

        if ($row === null) {
            return null;
        }

        return [
            'id' => (int) $row->id,
            'new_gate_state' => (string) $row->new_gate_state,
            'reason_code' => isset($row->reason_code) ? (string) $row->reason_code : null,
            'reason' => isset($row->reason) ? (string) $row->reason : null,
            'projection_build_id' => isset($row->projection_build_id) ? (string) $row->projection_build_id : null,
            'transitioned_at' => isset($row->transitioned_at) ? (string) $row->transitioned_at : null,
        ];
    }
}
