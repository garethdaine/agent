<?php

declare(strict_types=1);

namespace Tests\Unit\Telemetry;

use App\Repositories\Projection\WorkflowReliabilityCurrentRepository;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ActiveBuildScopedRepositoryTest extends TestCase
{
    use DatabaseMigrations;

    public function test_repository_returns_only_rows_for_active_projection_build(): void
    {
        $activeBuildId = (string) str()->uuid();
        $historicalBuildId = (string) str()->uuid();

        $this->insertBuildState($activeBuildId);
        $this->insertReliabilityRow('eng.repo-analysis.v1', 97.20, $historicalBuildId);
        $this->insertReliabilityRow('eng.repo-analysis.v1', 99.10, $activeBuildId);

        $repository = app(WorkflowReliabilityCurrentRepository::class);

        $row = $repository->findByWorkflowKey('eng.repo-analysis.v1');

        $this->assertNotNull($row);
        $this->assertSame($activeBuildId, $row['projection_build_id']);
        $this->assertSame(99.1, $row['reliability_score']);
    }

    public function test_repository_returns_null_when_no_active_projection_build_exists(): void
    {
        $buildId = (string) str()->uuid();
        $this->insertReliabilityRow('eng.repo-analysis.v1', 95.0, $buildId);

        $repository = app(WorkflowReliabilityCurrentRepository::class);

        $this->assertNull($repository->findByWorkflowKey('eng.repo-analysis.v1'));
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
