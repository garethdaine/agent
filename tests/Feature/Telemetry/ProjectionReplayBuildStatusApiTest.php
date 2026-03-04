<?php

declare(strict_types=1);

namespace Tests\Feature\Telemetry;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProjectionReplayBuildStatusApiTest extends TestCase
{
    use DatabaseMigrations;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_replay_build_status_api_includes_active_build_age_seconds(): void
    {
        Sanctum::actingAs(User::factory()->create());

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-03-04T12:10:00+00:00'));

        config()->set('agent.projections.stale_after_seconds', 300);

        $activeBuildId = (string) str()->uuid();
        $this->ensureBuildExists($activeBuildId, 'active', now()->subSeconds(301));
        $this->setBuildState($activeBuildId, null, now()->subSeconds(301));

        $response = $this->getJson('/agent/api/v1/telemetry/replay/active-build');

        $response->assertOk()
            ->assertJsonPath('data.active_build_id', $activeBuildId)
            ->assertJsonPath('data.active_build_age_seconds', 301)
            ->assertJsonPath('data.active_build_is_stale', true)
            ->assertJsonPath('data.stale_after_seconds', 300);
    }

    public function test_replay_build_status_api_returns_null_age_when_no_active_build_exists(): void
    {
        Sanctum::actingAs(User::factory()->create());

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-03-04T12:10:00+00:00'));

        $this->setBuildState(null, null, null);

        $response = $this->getJson('/agent/api/v1/telemetry/replay/active-build');

        $response->assertOk()
            ->assertJsonPath('data.active_build_id', null)
            ->assertJsonPath('data.active_build_age_seconds', null)
            ->assertJsonPath('data.active_build_is_stale', null);
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

    private function ensureBuildExists(string $buildId, string $status, mixed $activatedAt): void
    {
        DB::table($this->projectionTable('telemetry_projection_builds'))->updateOrInsert(
            ['id' => $buildId],
            [
                'status' => $status,
                'activated_at' => $activatedAt,
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
