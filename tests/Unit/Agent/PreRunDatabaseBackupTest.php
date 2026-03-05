<?php

namespace Tests\Unit\Agent;

use App\Models\AgentJobRun;
use App\Support\Agent\PreRunDatabaseBackup;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class PreRunDatabaseBackupTest extends TestCase
{
    public function test_it_skips_backup_during_unit_tests(): void
    {
        $run = $this->makeStubRun();
        $service = new PreRunDatabaseBackup;

        $result = $service->backup($run);

        $this->assertTrue($result['ok']);
        $this->assertTrue($result['skipped']);
        $this->assertSame(0, $result['duration_ms']);
        $this->assertStringContainsString('unit tests', $result['message']);
    }

    public function test_it_returns_expected_result_structure(): void
    {
        $run = $this->makeStubRun();
        $service = new PreRunDatabaseBackup;

        $result = $service->backup($run);

        $this->assertArrayHasKey('ok', $result);
        $this->assertArrayHasKey('attempted_at', $result);
        $this->assertArrayHasKey('message', $result);
        $this->assertArrayHasKey('output', $result);
        $this->assertArrayHasKey('skipped', $result);
        $this->assertArrayHasKey('duration_ms', $result);
        $this->assertIsBool($result['ok']);
        $this->assertIsBool($result['skipped']);
        $this->assertIsInt($result['duration_ms']);
        $this->assertIsString($result['attempted_at']);
        $this->assertIsString($result['message']);
    }

    public function test_result_contains_valid_iso8601_timestamp(): void
    {
        $run = $this->makeStubRun();
        $service = new PreRunDatabaseBackup;

        $result = $service->backup($run);

        $parsed = CarbonImmutable::parse($result['attempted_at']);
        $this->assertInstanceOf(CarbonImmutable::class, $parsed);
    }

    private function makeStubRun(): AgentJobRun
    {
        $run = new AgentJobRun;
        $run->id = 42;
        $run->agent_job_id = 7;

        return $run;
    }
}
