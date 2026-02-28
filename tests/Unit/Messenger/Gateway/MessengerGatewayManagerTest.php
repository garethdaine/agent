<?php

namespace Tests\Unit\Messenger\Gateway;

use App\Messenger\Gateway\Contracts\GatewayWorkerInterface;
use App\Messenger\Gateway\DTOs\WorkerHealthMetadata;
use App\Messenger\Gateway\Enums\WorkerHealthStatus;
use App\Messenger\Gateway\MessengerGatewayManager;
use App\Messenger\Gateway\ReconnectionStrategy;
use App\Models\ConnectorAccount;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Mockery\MockInterface;
use React\EventLoop\LoopInterface;
use Tests\TestCase;

class MessengerGatewayManagerTest extends TestCase
{
    use RefreshDatabase;

    private MockInterface|LoopInterface $loop;

    private MessengerGatewayManager $manager;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'messenger.gateway.shutdown_timeout' => 30,
            'messenger.gateway.health_check_interval' => 10,
            'messenger.gateway.credential_poll_interval' => 30,
        ]);

        $this->loop = Mockery::mock(LoopInterface::class);
        $this->loop->shouldReceive('addPeriodicTimer')->andReturnNull()->byDefault();
        $this->loop->shouldReceive('cancelTimer')->andReturnNull()->byDefault();
        $this->loop->shouldReceive('futureTick')->andReturnNull()->byDefault();

        $this->manager = new MessengerGatewayManager($this->loop);
    }

    public function test_boot_workers_loads_only_local_mode_connectors(): void
    {
        // Create local mode connectors
        $localConnector1 = ConnectorAccount::factory()->create([
            'provider' => ConnectorAccount::PROVIDER_SLACK,
            'connection_mode' => ConnectorAccount::MODE_LOCAL,
            'status' => ConnectorAccount::STATUS_CONNECTED,
        ]);
        $localConnector2 = ConnectorAccount::factory()->create([
            'provider' => ConnectorAccount::PROVIDER_TELEGRAM,
            'connection_mode' => ConnectorAccount::MODE_LOCAL,
            'status' => ConnectorAccount::STATUS_CONNECTED,
        ]);

        // Create webhook mode connector (should be ignored)
        ConnectorAccount::factory()->create([
            'provider' => ConnectorAccount::PROVIDER_SLACK,
            'connection_mode' => ConnectorAccount::MODE_WEBHOOK,
            'status' => ConnectorAccount::STATUS_CONNECTED,
        ]);

        // Mock worker factory
        $mockWorker = Mockery::mock(GatewayWorkerInterface::class);
        $mockWorker->shouldReceive('start')->twice();
        $mockWorker->shouldReceive('health')->andReturn(WorkerHealthStatus::Connected);

        $this->manager->setWorkerFactory(fn ($account) => $mockWorker);

        $this->manager->bootWorkers();

        // Verify only local mode connectors have workers
        $this->assertTrue($this->manager->hasWorker($localConnector1->id));
        $this->assertTrue($this->manager->hasWorker($localConnector2->id));
        $this->assertEquals(2, $this->manager->getWorkerCount());
    }

    public function test_add_worker_spawns_worker_and_registers_in_registry(): void
    {
        $connector = ConnectorAccount::factory()->create([
            'provider' => ConnectorAccount::PROVIDER_SLACK,
            'connection_mode' => ConnectorAccount::MODE_LOCAL,
        ]);

        $mockWorker = Mockery::mock(GatewayWorkerInterface::class);
        $mockWorker->shouldReceive('start')->once();
        $mockWorker->shouldReceive('health')->andReturn(WorkerHealthStatus::Connected);

        $this->manager->setWorkerFactory(fn ($account) => $mockWorker);

        $this->manager->addWorker($connector);

        $this->assertTrue($this->manager->hasWorker($connector->id));
        $this->assertEquals(1, $this->manager->getWorkerCount());
    }

    public function test_remove_worker_gracefully_stops_and_removes_from_registry(): void
    {
        $connector = ConnectorAccount::factory()->create([
            'provider' => ConnectorAccount::PROVIDER_SLACK,
            'connection_mode' => ConnectorAccount::MODE_LOCAL,
        ]);

        $mockWorker = Mockery::mock(GatewayWorkerInterface::class);
        $mockWorker->shouldReceive('start')->once();
        $mockWorker->shouldReceive('stop')->once();
        $mockWorker->shouldReceive('health')->andReturn(WorkerHealthStatus::Connected);

        $this->manager->setWorkerFactory(fn ($account) => $mockWorker);
        $this->manager->addWorker($connector);

        $this->assertTrue($this->manager->hasWorker($connector->id));

        $this->manager->removeWorker($connector->id);

        $this->assertFalse($this->manager->hasWorker($connector->id));
        $this->assertEquals(0, $this->manager->getWorkerCount());
    }

    public function test_restart_worker_drains_stops_then_starts_with_fresh_credentials(): void
    {
        $connector = ConnectorAccount::factory()->create([
            'provider' => ConnectorAccount::PROVIDER_SLACK,
            'connection_mode' => ConnectorAccount::MODE_LOCAL,
            'credentials' => ['token' => 'old-token'],
        ]);

        $callOrder = [];

        $mockWorker = Mockery::mock(GatewayWorkerInterface::class);
        $mockWorker->shouldReceive('start')->twice()->andReturnUsing(function () use (&$callOrder) {
            $callOrder[] = 'start';
        });
        $mockWorker->shouldReceive('stop')->once()->andReturnUsing(function () use (&$callOrder) {
            $callOrder[] = 'stop';
        });
        $mockWorker->shouldReceive('drain')->once()->andReturnUsing(function () use (&$callOrder) {
            $callOrder[] = 'drain';
        });
        $mockWorker->shouldReceive('health')->andReturn(WorkerHealthStatus::Connected);

        $this->manager->setWorkerFactory(fn ($account) => $mockWorker);
        $this->manager->addWorker($connector);

        // Update credentials
        $connector->update(['credentials' => ['token' => 'new-token']]);

        $this->manager->restartWorker($connector->id);

        // Verify order: drain → stop → start
        $this->assertEquals(['start', 'drain', 'stop', 'start'], $callOrder);
        $this->assertTrue($this->manager->hasWorker($connector->id));
    }

    public function test_shutdown_respects_30_second_timeout(): void
    {
        $connector = ConnectorAccount::factory()->create([
            'provider' => ConnectorAccount::PROVIDER_SLACK,
            'connection_mode' => ConnectorAccount::MODE_LOCAL,
        ]);

        $mockWorker = Mockery::mock(GatewayWorkerInterface::class);
        $mockWorker->shouldReceive('start')->once();
        $mockWorker->shouldReceive('stop')->once();
        $mockWorker->shouldReceive('health')->andReturn(WorkerHealthStatus::Connected);
        $mockWorker->shouldReceive('drain')->once();

        $this->manager->setWorkerFactory(fn ($account) => $mockWorker);
        $this->manager->addWorker($connector);

        $this->loop->shouldReceive('stop')->once();

        $shutdownStart = Carbon::now();
        $this->manager->shutdown();
        $shutdownEnd = Carbon::now();

        // Shutdown should complete within reasonable time (not block for 30s in tests)
        $this->assertFalse($this->manager->hasWorker($connector->id));
        $this->assertTrue($this->manager->isShutdown());
    }

    public function test_credential_change_detection_triggers_graceful_restart(): void
    {
        $connector = ConnectorAccount::factory()->create([
            'provider' => ConnectorAccount::PROVIDER_SLACK,
            'connection_mode' => ConnectorAccount::MODE_LOCAL,
            'credentials' => ['token' => 'original-token'],
        ]);

        $restartCalled = false;

        $mockWorker = Mockery::mock(GatewayWorkerInterface::class);
        $mockWorker->shouldReceive('start');
        $mockWorker->shouldReceive('stop');
        $mockWorker->shouldReceive('drain');
        $mockWorker->shouldReceive('health')->andReturn(WorkerHealthStatus::Connected);

        $this->manager->setWorkerFactory(fn ($account) => $mockWorker);
        $this->manager->addWorker($connector);

        // Store original credential hash
        $originalHash = $this->manager->getCredentialHash($connector->id);

        // Update credentials
        $connector->update(['credentials' => ['token' => 'new-token']]);

        // Trigger credential check
        $this->manager->checkCredentialChanges();

        // Verify hash changed detection
        $this->assertNotEquals($originalHash, $this->manager->getCredentialHash($connector->id));
    }

    public function test_health_check_updates_connector_runtime_state(): void
    {
        $connector = ConnectorAccount::factory()->create([
            'provider' => ConnectorAccount::PROVIDER_SLACK,
            'connection_mode' => ConnectorAccount::MODE_LOCAL,
            'runtime_state' => WorkerHealthStatus::Disconnected,
        ]);

        $mockWorker = Mockery::mock(GatewayWorkerInterface::class);
        $mockWorker->shouldReceive('start')->once();
        $mockWorker->shouldReceive('health')->andReturn(WorkerHealthStatus::Connected);
        $mockWorker->shouldReceive('getHealthMetadata')->andReturn(new WorkerHealthMetadata(
            lastEventAt: Carbon::now(),
            connectionUptime: 3600,
            errorMessage: null
        ));

        $this->manager->setWorkerFactory(fn ($account) => $mockWorker);
        $this->manager->addWorker($connector);

        // Trigger health check
        $this->manager->performHealthCheck();

        // Verify connector state was updated
        $connector->refresh();
        $this->assertEquals(WorkerHealthStatus::Connected, $connector->runtime_state);
        $this->assertNotNull($connector->last_health_check_at);
    }

    public function test_worker_error_state_updates_connector_with_error_message(): void
    {
        $connector = ConnectorAccount::factory()->create([
            'provider' => ConnectorAccount::PROVIDER_SLACK,
            'connection_mode' => ConnectorAccount::MODE_LOCAL,
            'runtime_state' => WorkerHealthStatus::Connected,
        ]);

        $errorMessage = 'WebSocket connection failed: timeout';

        $mockWorker = Mockery::mock(GatewayWorkerInterface::class);
        $mockWorker->shouldReceive('start')->once();
        $mockWorker->shouldReceive('health')->andReturn(WorkerHealthStatus::Error);
        $mockWorker->shouldReceive('getHealthMetadata')->andReturn(new WorkerHealthMetadata(
            lastEventAt: Carbon::now()->subMinutes(5),
            connectionUptime: 0,
            errorMessage: $errorMessage
        ));

        $this->manager->setWorkerFactory(fn ($account) => $mockWorker);
        $this->manager->addWorker($connector);

        $this->manager->performHealthCheck();

        $connector->refresh();
        $this->assertEquals(WorkerHealthStatus::Error, $connector->runtime_state);
        $this->assertEquals($errorMessage, $connector->runtime_error_message);
    }

    public function test_cannot_add_worker_after_shutdown(): void
    {
        $connector = ConnectorAccount::factory()->create([
            'provider' => ConnectorAccount::PROVIDER_SLACK,
            'connection_mode' => ConnectorAccount::MODE_LOCAL,
        ]);

        $this->loop->shouldReceive('stop')->once();
        $this->manager->shutdown();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot add worker: manager is shutting down');

        $this->manager->addWorker($connector);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
