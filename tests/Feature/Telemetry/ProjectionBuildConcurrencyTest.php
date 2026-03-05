<?php

declare(strict_types=1);

namespace Tests\Feature\Telemetry;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProjectionBuildConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_rebuild_conflict_payload_is_deterministic_with_explicit_reason_state(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $activeBuildId = (string) str()->uuid();
        $rebuildingBuildId = (string) str()->uuid();

        $this->ensureBuildExists($activeBuildId, 'active');
        $this->ensureBuildExists($rebuildingBuildId, 'rebuilding');
        $this->setBuildState($activeBuildId, $rebuildingBuildId);

        $response = $this->postJson('/agent/api/v1/telemetry/replay/builds');

        $response->assertStatus(409)
            ->assertJsonPath('error.code', 'PROJECTION_REBUILD_IN_PROGRESS')
            ->assertJsonPath('error.details.active_build_id', $activeBuildId)
            ->assertJsonPath('error.details.rebuilding_build_id', $rebuildingBuildId)
            ->assertJsonPath('error.details.conflict_reason_state', 'rebuilding_build_exists');
    }

    public function test_only_one_rebuilding_build_is_present_after_repeated_start_requests(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $activeBuildId = (string) str()->uuid();
        $this->ensureBuildExists($activeBuildId, 'active');
        $this->setBuildState($activeBuildId, null);

        $first = $this->postJson('/agent/api/v1/telemetry/replay/builds');
        $second = $this->postJson('/agent/api/v1/telemetry/replay/builds');

        $first->assertStatus(202)
            ->assertJsonPath('data.status', 'rebuilding');

        $second->assertStatus(409)
            ->assertJsonPath('error.code', 'PROJECTION_REBUILD_IN_PROGRESS');

        $this->assertSame(
            1,
            DB::table($this->projectionTable('telemetry_projection_builds'))
                ->where('status', 'rebuilding')
                ->count()
        );
    }

    private function setBuildState(?string $activeBuildId, ?string $rebuildingBuildId): void
    {
        DB::table($this->projectionTable('telemetry_projection_build_state'))->updateOrInsert(
            ['id' => 1],
            [
                'active_projection_build_id' => $activeBuildId,
                'rebuilding_build_id' => $rebuildingBuildId,
                'activated_at' => now()->subMinute(),
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

    private function projectionTable(string $table): string
    {
        return DB::getDriverName() === 'pgsql'
            ? 'agent_projection.'.$table
            : $table;
    }
}
