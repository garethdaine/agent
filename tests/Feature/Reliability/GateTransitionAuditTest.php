<?php

declare(strict_types=1);

namespace Tests\Feature\Reliability;

use App\Enums\GateTransitionSource;
use App\Services\Reliability\GateTransitionRecorder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class GateTransitionAuditTest extends TestCase
{
    use DatabaseMigrations;

    public function test_gate_transition_source_is_persisted_for_auditing(): void
    {
        $service = app(GateTransitionRecorder::class);

        $id = $service->record(
            workflowKey: 'eng.repo-analysis.v1',
            previousGateState: 'healthy',
            newGateState: 'hard_gate',
            source: GateTransitionSource::RELIABILITY_EVALUATION,
            reasonCode: 'hard_fail_burst',
            reason: 'Two consecutive hard failures detected.',
            runId: 'run-123'
        );

        $this->assertIsInt($id);

        $this->assertDatabaseHas($this->projectionTable('workflow_gate_transitions'), [
            'id' => $id,
            'workflow_key' => 'eng.repo-analysis.v1',
            'previous_gate_state' => 'healthy',
            'new_gate_state' => 'hard_gate',
            'source' => GateTransitionSource::RELIABILITY_EVALUATION->value,
            'reason_code' => 'hard_fail_burst',
            'run_id' => 'run-123',
        ]);
    }

    public function test_no_audit_row_is_written_when_gate_state_does_not_change(): void
    {
        $service = app(GateTransitionRecorder::class);

        $inserted = $service->record(
            workflowKey: 'eng.repo-analysis.v1',
            previousGateState: 'healthy',
            newGateState: 'healthy',
            source: GateTransitionSource::RELIABILITY_EVALUATION
        );

        $this->assertNull($inserted);
        $this->assertSame(0, DB::table($this->projectionTable('workflow_gate_transitions'))->count());
    }

    private function projectionTable(string $table): string
    {
        return DB::getDriverName() === 'pgsql'
            ? 'agent_projection.'.$table
            : $table;
    }
}
