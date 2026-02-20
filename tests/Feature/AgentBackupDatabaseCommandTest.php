<?php

namespace Tests\Feature;

use App\Models\AgentBackupSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgentBackupDatabaseCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_exits_without_running_when_disabled(): void
    {
        AgentBackupSetting::query()->create([
            'is_enabled' => false,
            'timezone' => 'UTC',
            'run_hour' => 2,
            'run_minute' => 0,
            'retention_days' => 14,
        ]);

        $this->artisan('agent:backup-database')
            ->assertExitCode(0);

        $setting = AgentBackupSetting::current();
        $this->assertNull($setting->last_run_at);
        $this->assertNull($setting->last_status);
        $this->assertNull($setting->last_error);
    }
}
