<?php

declare(strict_types=1);

namespace App\Services\Reliability;

use App\Enums\GateTransitionSource;
use App\Support\Telemetry\ProjectionTable;
use App\Support\Time\AgentClock;
use Illuminate\Support\Facades\DB;

final class GateTransitionRecorder
{
    public function __construct(
        private readonly AgentClock $clock,
    ) {}

    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public function record(
        string $workflowKey,
        ?string $previousGateState,
        string $newGateState,
        GateTransitionSource|string $source,
        ?string $reasonCode = null,
        ?string $reason = null,
        ?string $actorId = null,
        ?string $runId = null,
        ?string $projectionBuildId = null,
        ?array $metadata = null,
    ): ?int {
        if ($previousGateState !== null && $previousGateState === $newGateState) {
            return null;
        }

        $now = $this->clock->nowUtc();

        return (int) DB::table(ProjectionTable::qualified('workflow_gate_transitions'))->insertGetId([
            'workflow_key' => $workflowKey,
            'previous_gate_state' => $previousGateState,
            'new_gate_state' => $newGateState,
            'source' => $source instanceof GateTransitionSource ? $source->value : $source,
            'reason_code' => $reasonCode,
            'reason' => $reason,
            'actor_id' => $actorId,
            'run_id' => $runId,
            'projection_build_id' => $projectionBuildId,
            'metadata_json' => $metadata !== null ? json_encode($metadata, JSON_THROW_ON_ERROR) : null,
            'transitioned_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
}
