<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WorkflowHealthApiTest extends TestCase
{
    use DatabaseMigrations;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_health_cost_escalation_and_counting_contracts_expose_governance_and_lineage_fields(): void
    {
        Sanctum::actingAs(User::factory()->create());

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-03-04T14:00:00+00:00'));

        config()->set('agent.reliability.weighted_reliability_threshold', 95.0);
        config()->set('agent.reliability.degraded_rate_threshold', 3.0);

        $workflowKey = 'eng.repo-analysis.v1';
        $activeBuildId = (string) str()->uuid();
        $historicalBuildId = (string) str()->uuid();

        $this->ensureBuildExists($activeBuildId, 'active');
        $this->ensureBuildExists($historicalBuildId, 'historical');
        $this->setBuildState($activeBuildId, null, now()->subMinute());

        $this->insertReliabilityRow($workflowKey, 92.0, $historicalBuildId);
        $this->insertReliabilityRow($workflowKey, 98.7, $activeBuildId);

        $activeTransitionId = $this->insertGateTransition(
            workflowKey: $workflowKey,
            projectionBuildId: $activeBuildId,
            previousGateState: 'healthy',
            newGateState: 'hard_gate',
        );

        $this->insertGateTransition(
            workflowKey: $workflowKey,
            projectionBuildId: $historicalBuildId,
            previousGateState: 'warn',
            newGateState: 'healthy',
        );

        $this->insertEscalationIncident(
            workflowKey: $workflowKey,
            projectionBuildId: $activeBuildId,
            status: 'open',
        );

        $this->insertCostRollup(
            workflowKey: $workflowKey,
            projectionBuildId: $activeBuildId,
            canonicalCostUsd: 12.3456,
            providerBilledCostUsd: 10.2345,
            rateCardVersion: 'default',
        );

        DB::table('workflow_monthly_budget_policies')->insert([
            'workflow_key' => $workflowKey,
            'monthly_budget_usd' => 100.0,
            'warning_threshold_percent' => 80.0,
            'enforcement_threshold_percent' => 100.0,
            'is_active' => true,
            'metadata_json' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $healthResponse = $this->getJson('/agent/api/v1/workflows/'.$workflowKey.'/health');

        $healthResponse->assertOk()
            ->assertJsonPath('data.workflow_key', $workflowKey)
            ->assertJsonPath('data.active_projection_build_id', $activeBuildId)
            ->assertJsonPath('data.projection_build_id', $activeBuildId)
            ->assertJsonPath('data.active_build_age_seconds', 60)
            ->assertJsonPath('data.gate_state', 'hard_gate')
            ->assertJsonPath('data.countability_state', 'not_countable')
            ->assertJsonPath('data.countability_reason', 'incident_open')
            ->assertJsonPath('data.lineage.gate_transition_id', $activeTransitionId);

        $costResponse = $this->getJson('/agent/api/v1/workflows/'.$workflowKey.'/cost');

        $costResponse->assertOk()
            ->assertJsonPath('data.workflow_key', $workflowKey)
            ->assertJsonPath('data.active_projection_build_id', $activeBuildId)
            ->assertJsonPath('data.projection_build_id', $activeBuildId)
            ->assertJsonPath('data.active_build_age_seconds', 60)
            ->assertJsonPath('data.latest_rate_card_version', 'default');

        $escalationResponse = $this->getJson('/agent/api/v1/workflows/'.$workflowKey.'/escalations');

        $escalationResponse->assertOk()
            ->assertJsonPath('data.workflow_key', $workflowKey)
            ->assertJsonPath('data.active_projection_build_id', $activeBuildId)
            ->assertJsonPath('data.active_build_age_seconds', 60)
            ->assertJsonPath('data.countability_state', 'not_countable')
            ->assertJsonPath('data.escalations.0.status', 'open')
            ->assertJsonPath('data.escalations.0.projection_build_id', $activeBuildId);

        $countingResponse = $this->getJson('/agent/api/v1/deployments/counting');

        $countingResponse->assertOk()
            ->assertJsonPath('data.active_projection_build_id', $activeBuildId)
            ->assertJsonPath('data.active_build_age_seconds', 60)
            ->assertJsonPath('data.total_workflows', 1)
            ->assertJsonPath('data.counted_workflows', 0)
            ->assertJsonPath('data.workflows.0.workflow_key', $workflowKey)
            ->assertJsonPath('data.workflows.0.gate_state', 'hard_gate')
            ->assertJsonPath('data.workflows.0.countability_state', 'not_countable')
            ->assertJsonPath('data.workflows.0.countability_reason', 'incident_open')
            ->assertJsonPath('data.workflows.0.projection_build_id', $activeBuildId);
    }

    public function test_invalid_workflow_key_is_rejected_deterministically(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/agent/api/v1/workflows/INVALID_WORKFLOW/cost')
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

    private function insertReliabilityRow(string $workflowKey, float $reliabilityScore, string $buildId): void
    {
        DB::table($this->projectionTable('workflow_reliability_current'))->insert([
            'workflow_key' => $workflowKey,
            'reliability_score' => $reliabilityScore,
            'degraded_rate' => 1.2,
            'hard_fail_rate' => 0.0,
            'escalation_events' => 1,
            'projection_build_id' => $buildId,
            'source_high_watermark_ingested_at' => now()->subSeconds(5),
            'updated_at' => now(),
            'created_at' => now(),
        ]);
    }

    private function insertGateTransition(
        string $workflowKey,
        string $projectionBuildId,
        string $previousGateState,
        string $newGateState,
    ): int {
        return (int) DB::table($this->projectionTable('workflow_gate_transitions'))->insertGetId([
            'workflow_key' => $workflowKey,
            'previous_gate_state' => $previousGateState,
            'new_gate_state' => $newGateState,
            'source' => 'reliability_evaluation',
            'reason_code' => 'hard_fail_burst',
            'reason' => 'Deterministic test transition.',
            'actor_id' => 'system:test',
            'run_id' => 'run-001',
            'projection_build_id' => $projectionBuildId,
            'metadata_json' => null,
            'transitioned_at' => now()->subSeconds(10),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function insertEscalationIncident(string $workflowKey, string $projectionBuildId, string $status): void
    {
        DB::table($this->projectionTable('escalation_incidents'))->insert([
            'workflow_key' => $workflowKey,
            'trigger_type' => 'reliability_breach',
            'status' => $status,
            'reason_code' => 'hard_fail_burst',
            'reason' => 'Incident open for deterministic contract check.',
            'opened_at' => now()->subMinutes(2),
            'investigating_at' => null,
            'resolved_at' => null,
            'last_triggered_at' => now()->subSeconds(20),
            'projection_build_id' => $projectionBuildId,
            'metadata_json' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function insertCostRollup(
        string $workflowKey,
        string $projectionBuildId,
        float $canonicalCostUsd,
        float $providerBilledCostUsd,
        string $rateCardVersion,
    ): void {
        DB::table($this->projectionTable('workflow_cost_rollups'))->insert([
            'workflow_key' => $workflowKey,
            'run_id' => 'run-123',
            'budget_month_utc' => now('UTC')->format('Y-m'),
            'rate_card_version' => $rateCardVersion,
            'model' => 'test-model',
            'input_tokens' => 100,
            'output_tokens' => 200,
            'canonical_cost_usd' => $canonicalCostUsd,
            'provider_billed_cost_usd' => $providerBilledCostUsd,
            'projection_build_id' => $projectionBuildId,
            'occurred_at' => now()->subSeconds(30),
            'metadata_json' => null,
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
