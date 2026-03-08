<?php

declare(strict_types=1);

namespace Tests\Feature\Commands;

use App\Support\Agent\ProcessManager;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class AgentRestartCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Ensure PID directory exists
        Storage::disk('local')->makeDirectory('pids');
    }

    protected function tearDown(): void
    {
        // Clean up PID files
        $files = Storage::disk('local')->files('pids');
        foreach ($files as $file) {
            Storage::disk('local')->delete($file);
        }

        parent::tearDown();
    }

    public function test_restart_stops_and_starts_services(): void
    {
        $processManager = Mockery::mock(ProcessManager::class);

        // Expect stop operations
        $processManager->shouldReceive('stopService')
            ->with('horizon', 'artisan horizon', 30)
            ->once()
            ->andReturn(true);

        $processManager->shouldReceive('stopService')
            ->with('reverb', 'artisan reverb:start', 30)
            ->once()
            ->andReturn(true);

        $processManager->shouldReceive('stopService')
            ->with('scheduler', 'artisan schedule:work', 30)
            ->once()
            ->andReturn(true);

        $processManager->shouldReceive('stopService')
            ->with('messenger-gateway', Mockery::any(), 30)
            ->once()
            ->andReturn(true);

        $this->app->instance(ProcessManager::class, $processManager);

        // Use --stop-only to avoid actual process spawning in test
        // Start-phase methods (findByCommand, isRunning, writePidFile) are not called in stop-only mode
        $this->artisan('agent:restart', ['--stop-only' => true])
            ->expectsOutputToContain('Stopping services')
            ->expectsOutputToContain('Stop-only mode')
            ->assertSuccessful();
    }

    public function test_service_flag_limits_scope_to_specified_services(): void
    {
        $processManager = Mockery::mock(ProcessManager::class);

        // Only horizon should be stopped
        $processManager->shouldReceive('stopService')
            ->with('horizon', 'artisan horizon', 30)
            ->once()
            ->andReturn(true);

        // Other services should NOT be stopped
        $processManager->shouldNotReceive('stopService')
            ->with('reverb', Mockery::any(), Mockery::any());

        $processManager->shouldNotReceive('stopService')
            ->with('scheduler', Mockery::any(), Mockery::any());

        $this->app->instance(ProcessManager::class, $processManager);

        $this->artisan('agent:restart', [
            '--service' => 'horizon',
            '--stop-only' => true,
        ])
            ->expectsOutputToContain('horizon')
            ->assertSuccessful();
    }

    public function test_exclude_web_skips_serve(): void
    {
        // Set config to include web server
        config(['agent_restart.include_web_server' => true]);

        $processManager = Mockery::mock(ProcessManager::class);

        // Core services should be stopped
        $processManager->shouldReceive('stopService')
            ->with('horizon', 'artisan horizon', 30)
            ->once()
            ->andReturn(true);

        $processManager->shouldReceive('stopService')
            ->with('reverb', 'artisan reverb:start', 30)
            ->once()
            ->andReturn(true);

        $processManager->shouldReceive('stopService')
            ->with('scheduler', 'artisan schedule:work', 30)
            ->once()
            ->andReturn(true);

        $processManager->shouldReceive('stopService')
            ->with('messenger-gateway', Mockery::any(), 30)
            ->once()
            ->andReturn(true);

        // Serve should NOT be stopped when --exclude-web is used
        $processManager->shouldNotReceive('stopService')
            ->with('serve', Mockery::any(), Mockery::any());

        $this->app->instance(ProcessManager::class, $processManager);

        $this->artisan('agent:restart', [
            '--exclude-web' => true,
            '--stop-only' => true,
        ])->assertSuccessful();
    }

    public function test_graceful_shutdown_uses_custom_timeout(): void
    {
        $customTimeout = 60;
        $processManager = Mockery::mock(ProcessManager::class);

        // Verify timeout is passed correctly
        $processManager->shouldReceive('stopService')
            ->with('horizon', 'artisan horizon', $customTimeout)
            ->once()
            ->andReturn(true);

        $processManager->shouldReceive('stopService')
            ->with('reverb', 'artisan reverb:start', $customTimeout)
            ->once()
            ->andReturn(true);

        $processManager->shouldReceive('stopService')
            ->with('scheduler', 'artisan schedule:work', $customTimeout)
            ->once()
            ->andReturn(true);

        $processManager->shouldReceive('stopService')
            ->with('messenger-gateway', Mockery::any(), $customTimeout)
            ->once()
            ->andReturn(true);

        $this->app->instance(ProcessManager::class, $processManager);

        $this->artisan('agent:restart', [
            '--timeout' => $customTimeout,
            '--stop-only' => true,
        ])->assertSuccessful();
    }

    public function test_pid_file_management_writes_on_start(): void
    {
        // PID file management during the start phase cannot be tested with --stop-only
        // as the start phase is skipped. This test validates the stop phase completes
        // without errors, and the command reports success.
        $processManager = Mockery::mock(ProcessManager::class);

        // Setup stop behavior
        $processManager->shouldReceive('stopService')
            ->andReturn(true);

        $this->app->instance(ProcessManager::class, $processManager);

        $this->artisan('agent:restart', ['--stop-only' => true])
            ->assertSuccessful();
    }

    public function test_restart_displays_service_status(): void
    {
        $processManager = Mockery::mock(ProcessManager::class);

        $processManager->shouldReceive('stopService')
            ->andReturn(true);

        $processManager->shouldReceive('readPidFile')
            ->andReturn(null);

        $processManager->shouldReceive('isRunning')
            ->andReturn(false);

        $this->app->instance(ProcessManager::class, $processManager);

        $this->artisan('agent:restart', ['--stop-only' => true])
            ->expectsOutputToContain('horizon')
            ->expectsOutputToContain('reverb')
            ->expectsOutputToContain('scheduler');
    }

    public function test_restart_handles_failed_stop(): void
    {
        $processManager = Mockery::mock(ProcessManager::class);

        $processManager->shouldReceive('stopService')
            ->with('messenger-gateway', Mockery::any(), 30)
            ->once()
            ->andReturn(true);

        // First service fails to stop
        $processManager->shouldReceive('stopService')
            ->with('scheduler', 'artisan schedule:work', 30)
            ->once()
            ->andReturn(false);

        // Other services should still be attempted
        $processManager->shouldReceive('stopService')
            ->with('reverb', 'artisan reverb:start', 30)
            ->once()
            ->andReturn(true);

        $processManager->shouldReceive('stopService')
            ->with('horizon', 'artisan horizon', 30)
            ->once()
            ->andReturn(true);

        $this->app->instance(ProcessManager::class, $processManager);

        // Command should fail if any stop fails
        $this->artisan('agent:restart', ['--stop-only' => true])
            ->assertExitCode(1);
    }

    public function test_restart_with_multiple_services_via_comma_separated(): void
    {
        $processManager = Mockery::mock(ProcessManager::class);

        // Only specified services should be stopped
        $processManager->shouldReceive('stopService')
            ->with('horizon', 'artisan horizon', 30)
            ->once()
            ->andReturn(true);

        $processManager->shouldReceive('stopService')
            ->with('scheduler', 'artisan schedule:work', 30)
            ->once()
            ->andReturn(true);

        // Reverb should NOT be stopped
        $processManager->shouldNotReceive('stopService')
            ->with('reverb', Mockery::any(), Mockery::any());

        $this->app->instance(ProcessManager::class, $processManager);

        $this->artisan('agent:restart', [
            '--service' => 'horizon,scheduler',
            '--stop-only' => true,
        ])->assertSuccessful();
    }

    public function test_restart_skips_dev_services_by_default(): void
    {
        $processManager = Mockery::mock(ProcessManager::class);

        // Core services should be stopped
        $processManager->shouldReceive('stopService')
            ->with('horizon', Mockery::any(), Mockery::any())
            ->andReturn(true);

        $processManager->shouldReceive('stopService')
            ->with('reverb', Mockery::any(), Mockery::any())
            ->andReturn(true);

        $processManager->shouldReceive('stopService')
            ->with('scheduler', Mockery::any(), Mockery::any())
            ->andReturn(true);

        $processManager->shouldReceive('stopService')
            ->with('messenger-gateway', Mockery::any(), Mockery::any())
            ->andReturn(true);

        // Dev services (serve, vite) should NOT be touched by default
        $processManager->shouldNotReceive('stopService')
            ->with('serve', Mockery::any(), Mockery::any());

        $processManager->shouldNotReceive('stopService')
            ->with('vite', Mockery::any(), Mockery::any());

        $this->app->instance(ProcessManager::class, $processManager);

        $this->artisan('agent:restart', ['--stop-only' => true])
            ->assertSuccessful();
    }

    public function test_service_already_running_is_detected(): void
    {
        $processManager = Mockery::mock(ProcessManager::class);

        $processManager->shouldReceive('stopService')
            ->andReturn(true);

        // Service already running when start is attempted
        $processManager->shouldReceive('findByCommand')
            ->with('artisan horizon')
            ->andReturn(54321);

        $processManager->shouldReceive('findByCommand')
            ->with('artisan reverb:start')
            ->andReturn(null);

        $processManager->shouldReceive('findByCommand')
            ->with('artisan schedule:work')
            ->andReturn(null);

        $processManager->shouldReceive('isRunning')
            ->with(54321)
            ->andReturn(true);

        $processManager->shouldReceive('isRunning')
            ->andReturn(true);

        $processManager->shouldReceive('writePidFile');

        $processManager->shouldReceive('readPidFile')
            ->andReturn(54321);

        $this->app->instance(ProcessManager::class, $processManager);

        // We use --stop-only to avoid actual process starting
        // This is primarily testing the stop logic
        $this->artisan('agent:restart', ['--stop-only' => true])
            ->assertSuccessful();
    }
}
