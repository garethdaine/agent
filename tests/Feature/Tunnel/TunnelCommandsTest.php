<?php

declare(strict_types=1);

namespace Tests\Feature\Tunnel;

use App\Repositories\TunnelSettingsRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class TunnelCommandsTest extends TestCase
{
    use RefreshDatabase;

    public function test_tunnel_install_command_outputs_platform_instructions(): void
    {
        Process::fake([
            'which cloudflared' => Process::result(output: '/usr/local/bin/cloudflared', exitCode: 0),
            'cloudflared version' => Process::result(output: 'cloudflared version 2024.6.1', exitCode: 0),
            'cloudflared tunnel list*' => Process::result(output: '[]', exitCode: 0),
        ]);

        $this->artisan('tunnel:install')
            ->expectsOutputToContain('Platform')
            ->expectsOutputToContain('Install Command')
            ->assertExitCode(0);
    }

    public function test_tunnel_status_command_outputs_health_data(): void
    {
        $repository = new TunnelSettingsRepository;
        $repository->update([
            'tunnel_uuid' => 'test-uuid',
            'hostname' => 'test.example.com',
        ], 'active');

        Process::fake([
            'pgrep*' => Process::result(output: '12345', exitCode: 0),
            'which cloudflared' => Process::result(output: '/usr/local/bin/cloudflared', exitCode: 0),
            'cloudflared version' => Process::result(output: 'cloudflared version 2024.6.1', exitCode: 0),
        ]);

        Http::fake([
            'http://localhost:*' => Http::response('', 500),
        ]);

        $this->artisan('tunnel:status')
            ->expectsOutputToContain('Status')
            ->expectsOutputToContain('Hostname')
            ->assertExitCode(0);
    }

    public function test_tunnel_run_command_validates_configuration_exists(): void
    {
        // Ensure unconfigured state
        $repository = new TunnelSettingsRepository;
        $repository->get(); // Creates default unconfigured row

        Process::fake();

        $this->artisan('tunnel:run')
            ->expectsOutputToContain('not configured')
            ->assertExitCode(1);
    }
}
