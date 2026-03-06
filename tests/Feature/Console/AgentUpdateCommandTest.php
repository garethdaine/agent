<?php

namespace Tests\Feature\Console;

use App\Services\Agent\LicenseService;
use App\Support\Agent\LicenseStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgentUpdateCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_runs_successfully_with_valid_license(): void
    {
        $mock = $this->mock(LicenseService::class);
        $mock->shouldReceive('clearCache')->once();
        $mock->shouldReceive('validate')->once()->andReturn(LicenseStatus::valid('pro'));

        $this->artisan('agent:update')
            ->assertExitCode(0);
    }

    public function test_shows_version(): void
    {
        $mock = $this->mock(LicenseService::class);
        $mock->shouldReceive('clearCache')->once();
        $mock->shouldReceive('validate')->once()->andReturn(LicenseStatus::valid());

        $this->artisan('agent:update')
            ->expectsOutputToContain(config('agent.version'))
            ->assertExitCode(0);
    }

    public function test_warns_when_license_is_invalid(): void
    {
        $mock = $this->mock(LicenseService::class);
        $mock->shouldReceive('clearCache')->once();
        $mock->shouldReceive('validate')->once()->andReturn(LicenseStatus::invalid('Expired'));

        $this->artisan('agent:update')
            ->expectsOutputToContain('License validation failed')
            ->assertExitCode(0);
    }
}
