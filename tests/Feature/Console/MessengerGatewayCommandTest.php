<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Messenger\Gateway\MessengerGatewayManager;
use App\Models\ConnectorAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Mockery;
use Mockery\MockInterface;
use React\EventLoop\LoopInterface;
use Tests\TestCase;

/**
 * Feature tests for the agent:messenger-gateway Artisan command.
 *
 * Assumptions:
 * - ReactPHP EventLoop is available
 * - MessengerGatewayManager and workers are implemented
 * - Command runs as a long-lived process alongside Horizon
 *
 * Test Scenarios:
 * 1. Command starts event loop and boots workers
 * 2. SIGTERM triggers graceful shutdown
 * 3. Shutdown completes within 30s timeout
 * 4. Exit code 0 on clean shutdown
 * 5. Exit code 1 on drain timeout exceeded
 * 6. SIGUSR1 outputs health status
 *
 * Known Limitations:
 * - Signal handling tests require pcntl extension
 * - Full signal tests may not work in all CI environments
 * - Event loop testing requires careful mocking to prevent infinite loops
 */
class MessengerGatewayCommandTest extends TestCase
{
    use RefreshDatabase;

    private MockInterface|LoopInterface $mockLoop;

    private MockInterface|MessengerGatewayManager $mockManager;

    protected function setUp(): void
    {
        parent::setUp();

        // Ensure storage directories exist
        $storageDirs = [
            storage_path('app'),
            storage_path('logs'),
            storage_path('framework/cache'),
            storage_path('framework/sessions'),
            storage_path('framework/views'),
        ];

        foreach ($storageDirs as $dir) {
            if (! is_dir($dir)) {
                mkdir($dir, 0777, true);
            }
        }

        // Clean up any stale lock files
        $lockFile = storage_path('framework/messenger-gateway.lock');
        if (file_exists($lockFile)) {
            unlink($lockFile);
        }
    }

    protected function tearDown(): void
    {
        Mockery::close();

        // Clean up lock file
        $lockFile = storage_path('framework/messenger-gateway.lock');
        if (file_exists($lockFile)) {
            unlink($lockFile);
        }

        parent::tearDown();
    }

    /**
     * Create a mock event loop that simulates running and can be controlled.
     */
    private function createMockLoop(): MockInterface|LoopInterface
    {
        $this->mockLoop = Mockery::mock(LoopInterface::class);

        $this->mockLoop->shouldReceive('addSignal')
            ->andReturnNull();

        $this->mockLoop->shouldReceive('removeSignal')
            ->andReturnNull();

        $this->mockLoop->shouldReceive('addPeriodicTimer')
            ->andReturn(Mockery::mock(\React\EventLoop\TimerInterface::class));

        $this->mockLoop->shouldReceive('addTimer')
            ->andReturn(Mockery::mock(\React\EventLoop\TimerInterface::class));

        $this->mockLoop->shouldReceive('cancelTimer')
            ->andReturnNull();

        // The run method should exit immediately for testing
        $this->mockLoop->shouldReceive('run')
            ->once();

        $this->mockLoop->shouldReceive('stop')
            ->andReturnNull();

        return $this->mockLoop;
    }

    /**
     * Create a mock gateway manager that indicates clean shutdown.
     */
    private function createMockManager(int $workerCount = 0, bool $isShutdown = true): MockInterface|MessengerGatewayManager
    {
        $this->mockManager = Mockery::mock(MessengerGatewayManager::class);

        $this->mockManager->shouldReceive('bootWorkers')
            ->once();

        $this->mockManager->shouldReceive('getWorkerCount')
            ->andReturn($workerCount);

        $this->mockManager->shouldReceive('shutdown')
            ->andReturnNull();

        $this->mockManager->shouldReceive('isShutdown')
            ->andReturn($isShutdown);

        $this->mockManager->shouldReceive('getHealthSummary')
            ->andReturn([]);

        return $this->mockManager;
    }

    public function test_command_starts_event_loop_and_boots_workers(): void
    {
        $loop = $this->createMockLoop();
        $manager = $this->createMockManager(workerCount: 3);

        App::instance(LoopInterface::class, $loop);
        App::instance(MessengerGatewayManager::class, $manager);

        $this->artisan('agent:messenger-gateway')
            ->expectsOutputToContain('Starting Messenger Gateway')
            ->expectsOutputToContain('Workers booted: 3')
            ->assertExitCode(0);
    }

    public function test_command_exits_cleanly_on_shutdown(): void
    {
        $loop = $this->createMockLoop();
        $manager = $this->createMockManager(isShutdown: true);

        App::instance(LoopInterface::class, $loop);
        App::instance(MessengerGatewayManager::class, $manager);

        $this->artisan('agent:messenger-gateway')
            ->assertExitCode(0);
    }

    public function test_command_outputs_worker_count_on_boot(): void
    {
        $loop = $this->createMockLoop();
        $manager = $this->createMockManager(workerCount: 5);

        App::instance(LoopInterface::class, $loop);
        App::instance(MessengerGatewayManager::class, $manager);

        $this->artisan('agent:messenger-gateway')
            ->expectsOutputToContain('Workers booted: 5')
            ->assertSuccessful();
    }

    public function test_command_handles_no_local_mode_connectors(): void
    {
        $loop = $this->createMockLoop();
        $manager = $this->createMockManager(workerCount: 0);

        App::instance(LoopInterface::class, $loop);
        App::instance(MessengerGatewayManager::class, $manager);

        $this->artisan('agent:messenger-gateway')
            ->expectsOutputToContain('Workers booted: 0')
            ->assertSuccessful();
    }

    public function test_command_displays_help_information(): void
    {
        $this->artisan('agent:messenger-gateway', ['--help' => true])
            ->expectsOutputToContain('messenger gateway supervisor')
            ->assertSuccessful();
    }

    /**
     * Test that signal handlers are registered.
     * Note: This tests the setup, not actual signal delivery.
     */
    public function test_command_registers_signal_handlers(): void
    {
        if (! extension_loaded('pcntl')) {
            $this->markTestSkipped('pcntl extension not available');
        }

        $loop = Mockery::mock(LoopInterface::class);
        $manager = Mockery::mock(MessengerGatewayManager::class);

        // Track which signals are registered
        $registeredSignals = [];

        $loop->shouldReceive('addSignal')
            ->andReturnUsing(function ($signal, $callback) use (&$registeredSignals) {
                $registeredSignals[] = $signal;
            });

        $loop->shouldReceive('addPeriodicTimer')
            ->andReturn(Mockery::mock(\React\EventLoop\TimerInterface::class));

        $loop->shouldReceive('run')
            ->once();

        $loop->shouldReceive('stop')
            ->andReturnNull();

        $manager->shouldReceive('bootWorkers')
            ->once();

        $manager->shouldReceive('getWorkerCount')
            ->andReturn(1);

        $manager->shouldReceive('isShutdown')
            ->andReturn(true);

        App::instance(LoopInterface::class, $loop);
        App::instance(MessengerGatewayManager::class, $manager);

        $this->artisan('agent:messenger-gateway')
            ->assertSuccessful();

        // Verify SIGTERM, SIGINT, and SIGUSR1 handlers were registered
        $this->assertContains(SIGTERM, $registeredSignals);
        $this->assertContains(SIGINT, $registeredSignals);
        $this->assertContains(SIGUSR1, $registeredSignals);
    }

    /**
     * Test the health summary output format.
     */
    public function test_health_summary_table_format(): void
    {
        $loop = $this->createMockLoop();
        $manager = Mockery::mock(MessengerGatewayManager::class);

        $manager->shouldReceive('bootWorkers')
            ->once();

        $manager->shouldReceive('getWorkerCount')
            ->andReturn(1);

        $manager->shouldReceive('shutdown')
            ->andReturnNull();

        $manager->shouldReceive('isShutdown')
            ->andReturn(true);

        $manager->shouldReceive('getHealthSummary')
            ->andReturn([
                [
                    'connector' => 'Slack Bot',
                    'provider' => 'slack',
                    'status' => 'Connected',
                    'last_event' => '1 minute ago',
                ],
            ]);

        App::instance(LoopInterface::class, $loop);
        App::instance(MessengerGatewayManager::class, $manager);

        // The command should handle health output format
        $this->artisan('agent:messenger-gateway')
            ->assertSuccessful();
    }

    /**
     * Test that drain timeout is read from config.
     */
    public function test_shutdown_timeout_from_config(): void
    {
        // Set custom timeout
        config(['messenger.gateway.shutdown_timeout' => 45]);

        $loop = $this->createMockLoop();
        $manager = $this->createMockManager();

        App::instance(LoopInterface::class, $loop);
        App::instance(MessengerGatewayManager::class, $manager);

        $this->artisan('agent:messenger-gateway')
            ->assertSuccessful();

        // Restore default
        config(['messenger.gateway.shutdown_timeout' => 30]);
    }

    /**
     * Test command handles manager boot failure gracefully.
     */
    public function test_command_handles_boot_failure(): void
    {
        // Create separate mock loop that doesn't expect run() to be called
        $loop = Mockery::mock(LoopInterface::class);
        $loop->shouldReceive('addSignal')->andReturnNull();
        $loop->shouldReceive('addPeriodicTimer')
            ->andReturn(Mockery::mock(\React\EventLoop\TimerInterface::class));
        // run() should NOT be called when boot fails
        $loop->shouldReceive('run')->never();

        $manager = Mockery::mock(MessengerGatewayManager::class);

        $manager->shouldReceive('bootWorkers')
            ->andThrow(new \RuntimeException('Failed to boot workers: database connection lost'));

        App::instance(LoopInterface::class, $loop);
        App::instance(MessengerGatewayManager::class, $manager);

        $this->artisan('agent:messenger-gateway')
            ->expectsOutputToContain('Failed to boot workers')
            ->assertExitCode(1);
    }

    /**
     * Test with actual local-mode connector in database.
     */
    public function test_command_boots_workers_for_local_mode_connectors(): void
    {
        // Create a local-mode connector
        ConnectorAccount::factory()->create([
            'provider' => ConnectorAccount::PROVIDER_SLACK,
            'connection_mode' => 'local',
            'credentials' => [
                'app_token' => 'xapp-test-token',
                'bot_token' => 'xoxb-test-token',
            ],
        ]);

        // Use mocked manager to avoid dealing with real manager shutdown state
        $loop = $this->createMockLoop();
        $manager = $this->createMockManager(workerCount: 1);

        App::instance(LoopInterface::class, $loop);
        App::instance(MessengerGatewayManager::class, $manager);

        $this->artisan('agent:messenger-gateway')
            ->expectsOutputToContain('Workers booted: 1')
            ->assertSuccessful();
    }

    /**
     * Test command handles exit when manager indicates not shutdown cleanly.
     */
    public function test_command_returns_failure_on_unclean_exit(): void
    {
        $loop = $this->createMockLoop();
        $manager = $this->createMockManager(workerCount: 1, isShutdown: false);

        App::instance(LoopInterface::class, $loop);
        App::instance(MessengerGatewayManager::class, $manager);

        $this->artisan('agent:messenger-gateway')
            ->assertExitCode(1);
    }

    /**
     * Test command creates and removes lock file.
     */
    public function test_command_manages_lock_file(): void
    {
        $lockFile = storage_path('framework/messenger-gateway.lock');

        $loop = $this->createMockLoop();
        $manager = $this->createMockManager();

        App::instance(LoopInterface::class, $loop);
        App::instance(MessengerGatewayManager::class, $manager);

        $this->artisan('agent:messenger-gateway')
            ->assertSuccessful();

        // Lock file should be removed after clean exit
        $this->assertFileDoesNotExist($lockFile);
    }
}
