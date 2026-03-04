<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GateTransitionsApiTest extends TestCase
{
    use DatabaseMigrations;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_gate_transitions_endpoint_returns_active_build_scoped_rows_with_lineage_fields(): void
    {
        Sanctum::actingAs(User::factory()->create());

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-03-04T14:30:00+00:00'));

        $workflowKey = 'eng.code-implementation.v1';
        $activeBuildId = (string) str()->uuid();
        $historicalBuildId = (string) str()->uuid();

        $this->ensureBuildExists($activeBuildId, 'active');
        $this->ensureBuildExists($historicalBuildId, 'historical');
        $this->setBuildState($activeBuildId, null, now()->subSeconds(75));

        $this->insertTransition($workflowKey, $historicalBuildId, 'warn', 'healthy', 'run-old');
        $activeTransitionId = $this->insertTransition($workflowKey, $activeBuildId, 'healthy', 'hard_gate', 'run-new');

        $response = $this->getJson('/agent/api/v1/workflows/'.$workflowKey.'/gate-transitions');

        $response->assertOk()
            ->assertJsonPath('data.workflow_key', $workflowKey)
            ->assertJsonPath('data.active_projection_build_id', $activeBuildId)
            ->assertJsonPath('data.active_build_age_seconds', 75)
            ->assertJsonPath('data.transitions.0.id', $activeTransitionId)
            ->assertJsonPath('data.transitions.0.projection_build_id', $activeBuildId)
            ->assertJsonPath('data.transitions.0.new_gate_state', 'hard_gate')
            ->assertJsonPath('data.transitions.0.source', 'reliability_evaluation')
            ->assertJsonPath('data.transitions.0.run_id', 'run-new');

        $this->assertCount(1, $response->json('data.transitions'));
    }

    public function test_invalid_workflow_key_for_gate_transitions_is_rejected_deterministically(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/agent/api/v1/workflows/INVALID_WORKFLOW/gate-transitions')
            ->assertStatus(404);
    }

    private function setBuildState(?string $activeBuildId, ?string $rebuildingBuildId, mixed $activatedAt): void
    {
        DB::table($this->projectionTable('telemetry_projection_build_state'))->updateOrInsert(
            ['id' => 1],
            [
                'active_projection_build_id' => $activeBuildId,
                'rebuilding_build_id' => $rebuildingBuildId,
                'activated_at' => $activatedAt,
                'updated_at' => now(),
            ]
        );
    }

    private function ensureBuildExists(string $buildId, string $status): void
    {
        DB::table($this->projectionTable('telemetry_projection_builds'))->updateOrInsert(
            ['id' => $buildId],
            [
                'status' => $status,
                'activated_at' => now()->subMinute(),
                'created_at' => now()->subMinute(),
                'updated_at' => now()->subMinute(),
            ]
        );
    }

    private function insertTransition(
        string $workflowKey,
        string $projectionBuildId,
        string $previousGateState,
        string $newGateState,
        string $runId,
    ): int {
        return (int) DB::table($this->projectionTable('workflow_gate_transitions'))->insertGetId([
            'workflow_key' => $workflowKey,
            'previous_gate_state' => $previousGateState,
            'new_gate_state' => $newGateState,
            'source' => 'reliability_evaluation',
            'reason_code' => 'test_transition',
            'reason' => 'Deterministic gate transition API test row.',
            'actor_id' => 'system:test',
            'run_id' => $runId,
            'projection_build_id' => $projectionBuildId,
            'metadata_json' => null,
            'transitioned_at' => now()->subSeconds(30),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function projectionTable(string $table): string
    {
        return DB::getDriverName() === 'pgsql'
            ? 'agent_projection.'.$table
            : $table;
    }
}
