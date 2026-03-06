<?php

namespace Tests\Feature\Console;

use App\Services\Agent\LicenseService;
use App\Support\Agent\LicenseStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgentCheckLicenseCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_exits_zero_when_license_is_valid(): void
    {
        $mock = $this->mock(LicenseService::class);
        $mock->shouldReceive('clearCache')->once();
        $mock->shouldReceive('validate')->once()->andReturn(LicenseStatus::valid('pro', '2027-01-01'));
        $mock->shouldReceive('instanceFingerprint')->once()->andReturn(str_repeat('a', 64));

        config([
            'agent.license.key' => 'test-key-123',
            'agent.license.validation_url' => 'https://agent-ops.com/api/license/validate',
            'app.url' => 'https://production.example.com',
        ]);

        $this->artisan('agent:check-license')
            ->assertExitCode(0);
    }

    public function test_exits_one_when_license_is_invalid(): void
    {
        $mock = $this->mock(LicenseService::class);
        $mock->shouldReceive('clearCache')->once();
        $mock->shouldReceive('validate')->once()->andReturn(LicenseStatus::invalid('License expired'));
        $mock->shouldReceive('instanceFingerprint')->once()->andReturn(str_repeat('b', 64));

        config([
            'agent.license.key' => 'test-key-123',
            'agent.license.validation_url' => 'https://agent-ops.com/api/license/validate',
            'app.url' => 'https://production.example.com',
        ]);

        $this->artisan('agent:check-license')
            ->assertExitCode(1);
    }

    public function test_displays_license_key_masked(): void
    {
        $mock = $this->mock(LicenseService::class);
        $mock->shouldReceive('clearCache')->once();
        $mock->shouldReceive('validate')->once()->andReturn(LicenseStatus::valid());
        $mock->shouldReceive('instanceFingerprint')->once()->andReturn(str_repeat('c', 64));

        config([
            'agent.license.key' => 'abcdef-ghijkl-mnopqr-stuvwx',
            'agent.license.validation_url' => 'https://agent-ops.com/api/license/validate',
            'app.url' => 'https://production.example.com',
        ]);

        $this->artisan('agent:check-license')
            ->expectsOutputToContain('abcd')
            ->assertExitCode(0);
    }

    public function test_shows_bypassed_for_dev_domains(): void
    {
        $mock = $this->mock(LicenseService::class);
        $mock->shouldReceive('clearCache')->once();
        $mock->shouldReceive('validate')->once()->andReturn(LicenseStatus::bypassed());
        $mock->shouldReceive('instanceFingerprint')->once()->andReturn(str_repeat('d', 64));

        config([
            'agent.license.key' => '',
            'agent.license.validation_url' => 'https://agent-ops.com/api/license/validate',
            'app.url' => 'https://myapp.test',
        ]);

        $this->artisan('agent:check-license')
            ->expectsOutputToContain('development')
            ->assertExitCode(0);
    }
}
