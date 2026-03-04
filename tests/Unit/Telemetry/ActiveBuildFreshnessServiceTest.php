<?php

declare(strict_types=1);

namespace Tests\Unit\Telemetry;

use App\Services\Telemetry\ActiveBuildFreshnessService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ActiveBuildFreshnessServiceTest extends TestCase
{
    use DatabaseMigrations;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_it_calculates_active_build_age_seconds_with_utc_clock(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-03-04T12:05:45+00:00'));
        config()->set('agent.projections.stale_after_seconds', 300);

        $activeBuildId = (string) str()->uuid();
        $this->ensureBuildExists($activeBuildId, 'active', now()->subSeconds(345));
        $this->setBuildState($activeBuildId, now()->subSeconds(345));

        $snapshot = app(ActiveBuildFreshnessService::class)->snapshot();

        $this->assertSame(345, $snapshot['active_build_age_seconds']);
        $this->assertTrue($snapshot['active_build_is_stale']);
        $this->assertSame(345, Cache::get('agent:telemetry:active_build_age_seconds'));
    }

    public function test_it_returns_null_age_when_no_active_build_exists(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-03-04T12:05:45+00:00'));
        config()->set('agent.projections.stale_after_seconds', 300);
        Cache::put('agent:telemetry:active_build_age_seconds', 111, 60);

        $this->setBuildState(null, null);

        $snapshot = app(ActiveBuildFreshnessService::class)->snapshot();

        $this->assertNull($snapshot['active_build_age_seconds']);
        $this->assertNull($snapshot['active_build_is_stale']);
        $this->assertNull(Cache::get('agent:telemetry:active_build_age_seconds'));
    }

    private function setBuildState(?string $activeBuildId, mixed $activatedAt): void
    {
        DB::table($this->projectionTable('telemetry_projection_build_state'))->updateOrInsert(
            ['id' => 1],
            [
                'active_projection_build_id' => $activeBuildId,
                'rebuilding_build_id' => null,
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
