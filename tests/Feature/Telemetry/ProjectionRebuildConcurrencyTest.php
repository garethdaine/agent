<?php

declare(strict_types=1);

namespace Tests\Feature\Telemetry;

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProjectionRebuildConcurrencyTest extends TestCase
{
    use DatabaseTruncation;

    protected array $tablesToTruncate = [
        'agent_projection.telemetry_projection_builds',
        'agent_projection.telemetry_projection_build_state',
    ];

    public function test_second_rebuild_request_returns_deterministic_conflict_response(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $activeBuildId = (string) str()->uuid();
        $rebuildingBuildId = (string) str()->uuid();
        $this->ensureBuildExists($activeBuildId, 'active');
        $this->ensureBuildExists($rebuildingBuildId, 'rebuilding');
        $this->setBuildState($activeBuildId, $rebuildingBuildId);

        Cache::forget('agent:telemetry:projection_rebuild_conflicts');

        $response = $this->postJson('/agent/api/v1/telemetry/replay/builds');

        $response->assertStatus(409)
            ->assertJsonPath('error.code', 'PROJECTION_REBUILD_IN_PROGRESS')
            ->assertJsonPath('error.details.active_build_id', $activeBuildId)
            ->assertJsonPath('error.details.rebuilding_build_id', $rebuildingBuildId)
            ->assertJsonPath('error.message', 'Projection rebuild is already in progress.');

        $this->assertSame(1, (int) Cache::get('agent:telemetry:projection_rebuild_conflicts', 0));
    }

    public function test_only_first_rebuild_start_succeeds_and_rebuilding_row_stays_unique(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $activeBuildId = (string) str()->uuid();
        $this->ensureBuildExists($activeBuildId, 'active');
        $this->setBuildState($activeBuildId, null);

        $first = $this->postJson('/agent/api/v1/telemetry/replay/builds');

        $first->assertStatus(202)
            ->assertJsonPath('data.status', 'rebuilding');

        $createdRebuildId = $first->json('data.build_id');
        $this->assertIsString($createdRebuildId);
        $this->assertNotSame('', trim($createdRebuildId));

        $second = $this->postJson('/agent/api/v1/telemetry/replay/builds');

        $second->assertStatus(409)
            ->assertJsonPath('error.code', 'PROJECTION_REBUILD_IN_PROGRESS')
            ->assertJsonPath('error.details.active_build_id', $activeBuildId)
            ->assertJsonPath('error.details.rebuilding_build_id', $createdRebuildId);

        $this->assertSame(1, $this->rebuildingBuildCount());
    }

    public function test_unique_index_blocks_multiple_rebuilding_rows(): void
    {
        $this->ensureBuildExists((string) str()->uuid(), 'rebuilding');

        try {
            $this->ensureBuildExists((string) str()->uuid(), 'rebuilding');
            $this->fail('Expected unique index violation for status=rebuilding.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString(
                'telemetry_projection_builds_one_rebuilding_idx',
                strtolower($exception->getMessage())
            );
        }

        $this->assertSame(1, $this->rebuildingBuildCount());
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
        DB::table($this->projectionTable('telemetry_projection_builds'))->insert([
            'id' => $buildId,
            'status' => $status,
            'activated_at' => now()->subMinute(),
            'created_at' => now()->subMinute(),
            'updated_at' => now()->subMinute(),
        ]);
    }

    private function rebuildingBuildCount(): int
    {
        return DB::table($this->projectionTable('telemetry_projection_builds'))
            ->where('status', 'rebuilding')
            ->count();
    }

    private function projectionTable(string $table): string
    {
        return DB::getDriverName() === 'pgsql'
            ? 'agent_projection.'.$table
            : $table;
    }
}
