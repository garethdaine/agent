<?php

declare(strict_types=1);

namespace Tests\Integration\Telemetry;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ActiveBuildReadScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_runtime_read_returns_only_active_build_projection_data(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $activeBuildId = (string) str()->uuid();
        $historicalBuildId = (string) str()->uuid();

        $this->setBuildState($activeBuildId);
        $this->insertReliabilityRow('eng.code-implementation.v1', 91.4, $historicalBuildId);
        $this->insertReliabilityRow('eng.code-implementation.v1', 97.2, $activeBuildId);

        $response = $this->getJson('/agent/api/v1/workflows/eng.code-implementation.v1/reliability');

        $response->assertOk()
            ->assertJsonPath('data.workflow_key', 'eng.code-implementation.v1')
            ->assertJsonPath('data.active_projection_build_id', $activeBuildId)
            ->assertJsonPath('data.projection_build_id', $activeBuildId)
            ->assertJsonPath('data.reliability_score', 97.2);
    }

    public function test_missing_active_build_returns_explicit_reason_state(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $historicalBuildId = (string) str()->uuid();
        $this->insertReliabilityRow('eng.code-implementation.v1', 93.8, $historicalBuildId);

        $response = $this->getJson('/agent/api/v1/workflows/eng.code-implementation.v1/reliability');

        $response->assertStatus(404)
            ->assertJsonPath('error.code', 'NOT_FOUND')
            ->assertJsonPath('error.details.reason_state', 'missing_active_projection_build')
            ->assertJsonPath('error.details.active_projection_build_id', null)
            ->assertJsonPath('error.details.workflow_key.0', 'eng.code-implementation.v1');
    }

    private function setBuildState(string $activeBuildId): void
    {
        $this->ensureBuildExists($activeBuildId, 'active');

        DB::table($this->projectionTable('telemetry_projection_build_state'))->updateOrInsert(
            ['id' => 1],
            [
                'active_projection_build_id' => $activeBuildId,
                'rebuilding_build_id' => null,
                'activated_at' => now()->subMinute(),
                'updated_at' => now(),
            ]
        );
    }

    private function insertReliabilityRow(string $workflowKey, float $reliabilityScore, string $buildId): void
    {
        $this->ensureBuildExists($buildId, 'historical');

        DB::table($this->projectionTable('workflow_reliability_current'))->insert([
            'workflow_key' => $workflowKey,
            'reliability_score' => $reliabilityScore,
            'projection_build_id' => $buildId,
            'source_high_watermark_ingested_at' => now()->subSeconds(5),
            'updated_at' => now(),
            'created_at' => now(),
        ]);
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

    private function projectionTable(string $table): string
    {
        return DB::getDriverName() === 'pgsql'
            ? 'agent_projection.'.$table
            : $table;
    }
}
