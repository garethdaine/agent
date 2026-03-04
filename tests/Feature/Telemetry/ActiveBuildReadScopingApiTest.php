<?php

declare(strict_types=1);

namespace Tests\Feature\Telemetry;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ActiveBuildReadScopingApiTest extends TestCase
{
    use DatabaseMigrations;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_reliability_api_only_returns_active_build_projection_row(): void
    {
        Sanctum::actingAs(User::factory()->create());

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-03-04T12:00:00+00:00'));

        $activeBuildId = (string) str()->uuid();
        $historicalBuildId = (string) str()->uuid();

        $this->insertBuildState($activeBuildId);
        $this->insertReliabilityRow('eng.repo-analysis.v1', 93.4, $historicalBuildId);
        $this->insertReliabilityRow('eng.repo-analysis.v1', 98.7, $activeBuildId);

        $response = $this->getJson('/agent/api/v1/workflows/eng.repo-analysis.v1/reliability');

        $response->assertOk()
            ->assertJsonPath('data.workflow_key', 'eng.repo-analysis.v1')
            ->assertJsonPath('data.active_projection_build_id', $activeBuildId)
            ->assertJsonPath('data.projection_build_id', $activeBuildId)
            ->assertJsonPath('data.active_build_age_seconds', 60)
            ->assertJsonPath('data.reliability_score', 98.7);

        $this->assertNotSame(
            93.4,
            $response->json('data.reliability_score'),
            'Historical-build projection row should never be served by runtime API reads.'
        );

        $healthResponse = $this->getJson('/agent/api/v1/workflows/eng.repo-analysis.v1/health');

        $healthResponse->assertOk()
            ->assertJsonPath('data.active_projection_build_id', $activeBuildId)
            ->assertJsonPath('data.active_build_age_seconds', 60);
    }

    private function insertBuildState(string $activeBuildId): void
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
