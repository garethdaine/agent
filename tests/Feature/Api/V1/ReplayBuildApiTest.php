<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReplayBuildApiTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_replay_build_detail_endpoint_exposes_build_scope_and_lineage_fields(): void
    {
        Sanctum::actingAs(User::factory()->create());

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-03-04T14:10:00+00:00'));

        $activeBuildId = (string) str()->uuid();
        $targetBuildId = (string) str()->uuid();

        $this->ensureBuildExists($activeBuildId, 'active');
        $this->ensureBuildExists($targetBuildId, 'rebuilding');
        $this->setBuildState($activeBuildId, $targetBuildId, now()->subSeconds(120));

        $response = $this->getJson('/agent/api/v1/telemetry/replay/builds/'.$targetBuildId);

        $response->assertOk()
            ->assertJsonPath('data.build_id', $targetBuildId)
            ->assertJsonPath('data.status', 'rebuilding')
            ->assertJsonPath('data.is_active', false)
            ->assertJsonPath('data.active_projection_build_id', $activeBuildId)
            ->assertJsonPath('data.rebuilding_build_id', $targetBuildId)
            ->assertJsonPath('data.parity_state', 'pending')
            ->assertJsonPath('data.active_build_age_seconds', null);
    }

    public function test_replay_activation_is_denied_without_parity_pass(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $activeBuildId = (string) str()->uuid();
        $targetBuildId = (string) str()->uuid();

        $this->ensureBuildExists($activeBuildId, 'active');
        $this->ensureBuildExists($targetBuildId, 'rebuilding');
        $this->setBuildState($activeBuildId, $targetBuildId, now()->subMinute());

        $response = $this->postJson('/agent/api/v1/telemetry/replay/builds/'.$targetBuildId.'/activate');

        $response->assertStatus(409)
            ->assertJsonPath('error.code', 'REPLAY_PARITY_REQUIRED')
            ->assertJsonPath('error.details.required_status', 'parity_passed')
            ->assertJsonPath('error.details.current_status', 'rebuilding')
            ->assertJsonPath('error.details.build_id', $targetBuildId);
    }

    public function test_replay_activation_swaps_active_build_pointer_when_parity_passed(): void
    {
        Sanctum::actingAs(User::factory()->create());

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-03-04T14:20:00+00:00'));

        $activeBuildId = (string) str()->uuid();
        $targetBuildId = (string) str()->uuid();

        $this->ensureBuildExists($activeBuildId, 'active');
        $this->ensureBuildExists($targetBuildId, 'parity_passed');
        $this->setBuildState($activeBuildId, $targetBuildId, now()->subMinutes(5));

        $response = $this->postJson('/agent/api/v1/telemetry/replay/builds/'.$targetBuildId.'/activate');

        $response->assertOk()
            ->assertJsonPath('data.active_build_id', $targetBuildId)
            ->assertJsonPath('data.previous_active_build_id', $activeBuildId)
            ->assertJsonPath('data.rebuilding_build_id', null)
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.active_build_age_seconds', 0);

        $state = DB::table($this->projectionTable('telemetry_projection_build_state'))
            ->where('id', 1)
            ->first();

        $this->assertNotNull($state);
        $this->assertSame($targetBuildId, $state->active_projection_build_id);
        $this->assertNull($state->rebuilding_build_id);

        $oldBuild = DB::table($this->projectionTable('telemetry_projection_builds'))
            ->where('id', $activeBuildId)
            ->first();
        $newBuild = DB::table($this->projectionTable('telemetry_projection_builds'))
            ->where('id', $targetBuildId)
            ->first();

        $this->assertNotNull($oldBuild);
        $this->assertNotNull($newBuild);
        $this->assertSame('historical', $oldBuild->status);
        $this->assertSame('active', $newBuild->status);
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
                'activated_at' => null,
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
