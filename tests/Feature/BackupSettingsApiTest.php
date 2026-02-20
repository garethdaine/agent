<?php

namespace Tests\Feature;

use App\Models\AgentBackupSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BackupSettingsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_backup_settings_can_be_loaded_and_updated(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->getJson('/agent/api/v1/backups/settings')
            ->assertOk()
            ->assertJsonPath('data.is_enabled', true)
            ->assertJsonPath('data.timezone', 'UTC')
            ->assertJsonPath('data.run_hour', 2)
            ->assertJsonPath('data.run_minute', 0)
            ->assertJsonPath('data.retention_days', 14);

        $this->putJson('/agent/api/v1/backups/settings', [
            'is_enabled' => true,
            'timezone' => 'Europe/London',
            'run_hour' => 1,
            'run_minute' => 30,
            'retention_days' => 21,
        ])->assertOk()
            ->assertJsonPath('data.timezone', 'Europe/London')
            ->assertJsonPath('data.run_hour', 1)
            ->assertJsonPath('data.run_minute', 30)
            ->assertJsonPath('data.retention_days', 21);

        $setting = AgentBackupSetting::current();
        $this->assertTrue($setting->is_enabled);
        $this->assertSame('Europe/London', $setting->timezone);
        $this->assertSame(1, $setting->run_hour);
        $this->assertSame(30, $setting->run_minute);
        $this->assertSame(21, $setting->retention_days);
        $this->assertSame($user->id, $setting->updated_by_user_id);
    }
}
