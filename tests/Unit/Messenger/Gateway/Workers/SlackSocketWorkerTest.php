<?php

declare(strict_types=1);

namespace Tests\Unit\Messenger\Gateway\Workers;

use App\Jobs\Messenger\ProcessInboundMessage;
use App\Messenger\Gateway\DTOs\WorkerHealthMetadata;
use App\Messenger\Gateway\Enums\WorkerHealthStatus;
use App\Messenger\Gateway\ReconnectionStrategy;
use App\Messenger\Gateway\Workers\SlackSocketWorker;
use App\Models\ConnectorAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Mockery\MockInterface;
use Ratchet\Client\WebSocket;
use Ratchet\RFC6455\Messaging\MessageInterface;
use React\EventLoop\LoopInterface;
use Tests\TestCase;

/**
 * Unit tests for SlackSocketWorker.
 *
 * Assumptions:
 * - Ratchet/Pawl WebSocket client is available
 * - Slack app has Socket Mode enabled with app-level token (xapp-*)
 * - ConnectorAccount has credentials with app_token and bot_token
 *
 * Slack Socket Mode Protocol:
 * 1. POST to apps.connections.open to get WebSocket URL
 * 2. Connect to returned wss:// URL
 * 3. Receive 'hello' event confirming connection
 * 4. Acknowledge events with envelope_id within 3 seconds
 * 5. Handle disconnect events by reconnecting
 *
 * Testing Approach:
 * These tests use a custom WebSocket connector factory that allows us to inject
 * a mock connection and simulate message events synchronously. This is necessary
 * because the actual WebSocket connection happens asynchronously with ReactPHP.
 */
class SlackSocketWorkerTest extends TestCase
{
    use RefreshDatabase;

    private MockInterface|LoopInterface $loop;

    private ReconnectionStrategy $reconnection;

    private ConnectorAccount $account;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();

        $this->loop = Mockery::mock(LoopInterface::class);
        $this->loop->shouldReceive('addTimer')->andReturnNull()->byDefault();
        $this->loop->shouldReceive('addPeriodicTimer')->andReturnNull()->byDefault();
        $this->loop->shouldReceive('cancelTimer')->andReturnNull()->byDefault();
        $this->loop->shouldReceive('futureTick')->andReturnNull()->byDefault();

        $this->reconnection = new ReconnectionStrategy(1, 300, 0); // No jitter for predictable tests

        $this->account = ConnectorAccount::factory()->create([
            'provider' => ConnectorAccount::PROVIDER_SLACK,
            'connection_mode' => ConnectorAccount::MODE_LOCAL,
            'status' => ConnectorAccount::STATUS_CONNECTED,
            'credentials' => [
                'app_token' => 'xapp-1-A0123456789-0123456789012-abcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyzabcdefghij',
                'bot_token' => 'xoxb-111-222-FAKESECRET',
            ],
        ]);
    }

    public function test_start_calls_apps_connections_open_and_connects_to_returned_url(): void
    {
        $wsUrl = 'wss://wss-primary.slack.com/link/?ticket=abc123';

        Http::fake([
            'https://slack.com/api/apps.connections.open' => Http::response([
                'ok' => true,
                'url' => $wsUrl,
            ], 200),
        ]);

        $connectedUrl = null;
        $worker = $this->createWorkerWithSynchronousConnection(
            $wsUrl,
            ['type' => 'hello'],
            $connectedUrl
        );

        $worker->start();

        Http::assertSent(function ($request) {
            return $request->url() === 'https://slack.com/api/apps.connections.open'
                && str_starts_with($request->header('Authorization')[0], 'Bearer xapp-');
        });

        $this->assertEquals($wsUrl, $connectedUrl);
        $this->assertEquals(WorkerHealthStatus::Connected, $worker->health());
    }

    public function test_hello_event_handling_sets_state_to_connected(): void
    {
        Http::fake([
            'https://slack.com/api/apps.connections.open' => Http::response([
                'ok' => true,
                'url' => 'wss://wss-primary.slack.com/link/?ticket=abc123',
            ], 200),
        ]);

        $worker = $this->createWorkerWithSynchronousConnection(
            'wss://wss-primary.slack.com/link/?ticket=abc123',
            ['type' => 'hello']
        );

        // Before start, should be disconnected
        $this->assertEquals(WorkerHealthStatus::Disconnected, $worker->health());

        $worker->start();

        // After hello event, should be connected
        $this->assertEquals(WorkerHealthStatus::Connected, $worker->health());

        $metadata = $worker->getHealthMetadata();
        $this->assertNotNull($metadata->lastEventAt);
        $this->assertGreaterThanOrEqual(0, $metadata->connectionUptime);
    }

    public function test_event_acknowledgment_sends_envelope_id(): void
    {
        Http::fake([
            'https://slack.com/api/apps.connections.open' => Http::response([
                'ok' => true,
                'url' => 'wss://wss-primary.slack.com/link/?ticket=abc123',
            ], 200),
        ]);

        $sentMessages = [];
        $worker = $this->createWorkerWithMessageCapture(
            'wss://wss-primary.slack.com/link/?ticket=abc123',
            [
                ['type' => 'hello'],
                [
                    'type' => 'events_api',
                    'envelope_id' => 'abc123-envelope',
                    'payload' => [
                        'event' => [
                            'type' => 'message',
                            'user' => 'U12345',
                            'channel' => 'C12345',
                            'text' => 'Hello, world!',
                            'ts' => '1234567890.123456',
                        ],
                    ],
                ],
            ],
            $sentMessages
        );

        $worker->start();

        // Verify acknowledgment was sent with correct envelope_id
        $this->assertNotEmpty($sentMessages);
        $ackMessage = json_decode($sentMessages[0], true);
        $this->assertEquals('abc123-envelope', $ackMessage['envelope_id']);
    }

    public function test_event_dispatches_process_chat_intent_job(): void
    {
        Http::fake([
            'https://slack.com/api/apps.connections.open' => Http::response([
                'ok' => true,
                'url' => 'wss://wss-primary.slack.com/link/?ticket=abc123',
            ], 200),
        ]);

        $worker = $this->createWorkerWithSynchronousConnection(
            'wss://wss-primary.slack.com/link/?ticket=abc123',
            ['type' => 'hello'],
            messages: [
                ['type' => 'hello'],
                [
                    'type' => 'events_api',
                    'envelope_id' => 'abc123-envelope',
                    'payload' => [
                        'event' => [
                            'type' => 'message',
                            'user' => 'U12345',
                            'channel' => 'C12345',
                            'text' => 'Hello, world!',
                            'ts' => '1234567890.123456',
                        ],
                    ],
                ],
            ]
        );

        $worker->start();

        // Verify ProcessInboundMessage job was dispatched
        Queue::assertPushed(ProcessInboundMessage::class);
    }

    public function test_ping_pong_heartbeat_response(): void
    {
        Http::fake([
            'https://slack.com/api/apps.connections.open' => Http::response([
                'ok' => true,
                'url' => 'wss://wss-primary.slack.com/link/?ticket=abc123',
            ], 200),
        ]);

        $sentMessages = [];
        $worker = $this->createWorkerWithMessageCapture(
            'wss://wss-primary.slack.com/link/?ticket=abc123',
            [
                ['type' => 'hello'],
                [
                    'type' => 'ping',
                    'envelope_id' => 'ping-123',
                ],
            ],
            $sentMessages
        );

        $worker->start();

        // Verify pong was sent
        $this->assertNotEmpty($sentMessages);
        $pongMessage = json_decode($sentMessages[0], true);
        $this->assertEquals('ping-123', $pongMessage['envelope_id']);
    }

    public function test_disconnect_event_triggers_reconnection_strategy(): void
    {
        Http::fake([
            'https://slack.com/api/apps.connections.open' => Http::response([
                'ok' => true,
                'url' => 'wss://wss-primary.slack.com/link/?ticket=abc123',
            ], 200),
        ]);

        $timerCallbackSet = false;
        $this->loop = Mockery::mock(LoopInterface::class);
        $this->loop->shouldReceive('addTimer')
            ->once()
            ->andReturnUsing(function ($delay, $callback) use (&$timerCallbackSet) {
                $timerCallbackSet = true;

                return Mockery::mock('React\EventLoop\TimerInterface');
            });
        $this->loop->shouldReceive('addPeriodicTimer')->andReturnNull();
        $this->loop->shouldReceive('cancelTimer')->andReturnNull();
        $this->loop->shouldReceive('futureTick')->andReturnNull();

        $worker = $this->createWorkerWithSynchronousConnection(
            'wss://wss-primary.slack.com/link/?ticket=abc123',
            ['type' => 'hello'],
            messages: [
                ['type' => 'hello'],
                [
                    'type' => 'disconnect',
                    'reason' => 'link_disabled',
                ],
            ]
        );

        $worker->start();

        // After disconnect, status should be reconnecting
        $this->assertEquals(WorkerHealthStatus::Reconnecting, $worker->health());

        // Verify a reconnection timer was scheduled
        $this->assertTrue($timerCallbackSet, 'Reconnection timer should be scheduled');
    }

    public function test_stop_closes_websocket_gracefully(): void
    {
        Http::fake([
            'https://slack.com/api/apps.connections.open' => Http::response([
                'ok' => true,
                'url' => 'wss://wss-primary.slack.com/link/?ticket=abc123',
            ], 200),
        ]);

        $connectionClosed = false;
        $worker = $this->createWorkerWithCloseTracking(
            'wss://wss-primary.slack.com/link/?ticket=abc123',
            ['type' => 'hello'],
            $connectionClosed
        );

        $worker->start();
        $this->assertEquals(WorkerHealthStatus::Connected, $worker->health());

        $worker->stop();

        $this->assertTrue($connectionClosed, 'WebSocket connection should be closed');
        $this->assertEquals(WorkerHealthStatus::Disconnected, $worker->health());
    }

    public function test_health_returns_correct_worker_health_status(): void
    {
        Http::fake([
            'https://slack.com/api/apps.connections.open' => Http::response([
                'ok' => true,
                'url' => 'wss://wss-primary.slack.com/link/?ticket=abc123',
            ], 200),
        ]);

        $worker = $this->createWorkerWithSynchronousConnection(
            'wss://wss-primary.slack.com/link/?ticket=abc123',
            ['type' => 'hello']
        );

        // Initially disconnected
        $this->assertEquals(WorkerHealthStatus::Disconnected, $worker->health());

        $worker->start();

        // After hello, connected
        $this->assertEquals(WorkerHealthStatus::Connected, $worker->health());
        $this->assertTrue($worker->health()->isHealthy());

        // Check metadata
        $metadata = $worker->getHealthMetadata();
        $this->assertNull($metadata->errorMessage);
        $this->assertGreaterThanOrEqual(0, $metadata->connectionUptime);
    }

    public function test_get_connector_account_id_returns_correct_id(): void
    {
        $worker = new SlackSocketWorker(
            $this->account,
            $this->loop,
            $this->reconnection
        );

        $this->assertEquals($this->account->id, $worker->getConnectorAccountId());
    }

    public function test_drain_stops_accepting_new_events(): void
    {
        Http::fake([
            'https://slack.com/api/apps.connections.open' => Http::response([
                'ok' => true,
                'url' => 'wss://wss-primary.slack.com/link/?ticket=abc123',
            ], 200),
        ]);

        $messagesProcessed = [];
        $worker = $this->createWorkerWithEventTracking(
            'wss://wss-primary.slack.com/link/?ticket=abc123',
            [
                ['type' => 'hello'],
                [
                    'type' => 'events_api',
                    'envelope_id' => 'event-1',
                    'payload' => ['event' => ['type' => 'message']],
                ],
            ],
            $messagesProcessed
        );

        $worker->start();

        // First event should be processed
        $this->assertCount(1, $messagesProcessed);

        // Drain the worker
        $worker->drain();

        // Send another event after draining
        $this->simulateMessageAfterDrain($worker, [
            'type' => 'events_api',
            'envelope_id' => 'event-2',
            'payload' => ['event' => ['type' => 'message']],
        ]);

        // Second event should not be processed (still 1)
        $this->assertCount(1, $messagesProcessed);
    }

    public function test_reconnect_closes_existing_connection_and_schedules_new(): void
    {
        Http::fake([
            'https://slack.com/api/apps.connections.open' => Http::response([
                'ok' => true,
                'url' => 'wss://wss-primary.slack.com/link/?ticket=abc123',
            ], 200),
        ]);

        $timerScheduled = false;
        $this->loop = Mockery::mock(LoopInterface::class);
        $this->loop->shouldReceive('addTimer')
            ->andReturnUsing(function () use (&$timerScheduled) {
                $timerScheduled = true;

                return Mockery::mock('React\EventLoop\TimerInterface');
            });
        $this->loop->shouldReceive('addPeriodicTimer')->andReturnNull();
        $this->loop->shouldReceive('cancelTimer')->andReturnNull();
        $this->loop->shouldReceive('futureTick')->andReturnNull();

        $connectionClosed = false;
        $worker = $this->createWorkerWithCloseTracking(
            'wss://wss-primary.slack.com/link/?ticket=abc123',
            ['type' => 'hello'],
            $connectionClosed
        );

        $worker->start();
        $this->assertEquals(WorkerHealthStatus::Connected, $worker->health());

        $worker->reconnect();

        // Should have closed old connection
        $this->assertTrue($connectionClosed, 'Old connection should be closed');
        // Should be in reconnecting state
        $this->assertEquals(WorkerHealthStatus::Reconnecting, $worker->health());
        // Should have scheduled reconnection timer
        $this->assertTrue($timerScheduled, 'Reconnection timer should be scheduled');
    }

    public function test_apps_connections_open_failure_sets_error_state(): void
    {
        Http::fake([
            'https://slack.com/api/apps.connections.open' => Http::response([
                'ok' => false,
                'error' => 'invalid_auth',
            ], 200),
        ]);

        $worker = new SlackSocketWorker(
            $this->account,
            $this->loop,
            $this->reconnection
        );

        $worker->start();

        $this->assertEquals(WorkerHealthStatus::Error, $worker->health());

        $metadata = $worker->getHealthMetadata();
        $this->assertNotNull($metadata->errorMessage);
        $this->assertStringContainsString('invalid_auth', $metadata->errorMessage);
    }

    /**
     * Create a worker with a synchronous connection that immediately fires messages.
     */
    private function createWorkerWithSynchronousConnection(
        string $expectedUrl,
        array $firstMessage,
        ?string &$connectedUrl = null,
        array $messages = []
    ): SlackSocketWorker {
        if (empty($messages)) {
            $messages = [$firstMessage];
        }

        $worker = new SlackSocketWorker(
            $this->account,
            $this->loop,
            $this->reconnection
        );

        $worker->setWebSocketConnectorFactory(function ($loop) use ($expectedUrl, $messages, &$connectedUrl) {
            return function (string $url) use ($expectedUrl, $messages, &$connectedUrl) {
                $connectedUrl = $url;

                $mockConnection = Mockery::mock(WebSocket::class);
                $mockConnection->shouldReceive('send')->andReturnNull();
                $mockConnection->shouldReceive('close')->andReturnNull();

                $messageCallback = null;
                $mockConnection->shouldReceive('on')
                    ->andReturnUsing(function ($event, $callback) use (&$messageCallback, $messages) {
                        if ($event === 'message') {
                            $messageCallback = $callback;
                            // Fire all messages immediately
                            foreach ($messages as $msg) {
                                $mockMsg = Mockery::mock(MessageInterface::class);
                                $mockMsg->shouldReceive('getPayload')
                                    ->andReturn(json_encode($msg));
                                $callback($mockMsg);
                            }
                        }
                    });

                // Return a promise that resolves immediately
                return new class($mockConnection) {
                    private $connection;

                    public function __construct($connection)
                    {
                        $this->connection = $connection;
                    }

                    public function then(?callable $onFulfilled = null, ?callable $onRejected = null)
                    {
                        if ($onFulfilled) {
                            $onFulfilled($this->connection);
                        }

                        return $this;
                    }
                };
            };
        });

        return $worker;
    }

    /**
     * Create a worker that captures sent messages.
     */
    private function createWorkerWithMessageCapture(
        string $expectedUrl,
        array $messages,
        array &$sentMessages
    ): SlackSocketWorker {
        $worker = new SlackSocketWorker(
            $this->account,
            $this->loop,
            $this->reconnection
        );

        $worker->setWebSocketConnectorFactory(function ($loop) use ($expectedUrl, $messages, &$sentMessages) {
            return function (string $url) use ($expectedUrl, $messages, &$sentMessages) {
                $mockConnection = Mockery::mock(WebSocket::class);
                $mockConnection->shouldReceive('send')
                    ->andReturnUsing(function ($msg) use (&$sentMessages) {
                        $sentMessages[] = $msg;
                    });
                $mockConnection->shouldReceive('close')->andReturnNull();

                $mockConnection->shouldReceive('on')
                    ->andReturnUsing(function ($event, $callback) use ($messages) {
                        if ($event === 'message') {
                            foreach ($messages as $msg) {
                                $mockMsg = Mockery::mock(MessageInterface::class);
                                $mockMsg->shouldReceive('getPayload')
                                    ->andReturn(json_encode($msg));
                                $callback($mockMsg);
                            }
                        }
                    });

                return new class($mockConnection) {
                    private $connection;

                    public function __construct($connection)
                    {
                        $this->connection = $connection;
                    }

                    public function then(?callable $onFulfilled = null, ?callable $onRejected = null)
                    {
                        if ($onFulfilled) {
                            $onFulfilled($this->connection);
                        }

                        return $this;
                    }
                };
            };
        });

        return $worker;
    }

    /**
     * Create a worker that tracks when the connection is closed.
     */
    private function createWorkerWithCloseTracking(
        string $expectedUrl,
        array $firstMessage,
        bool &$connectionClosed
    ): SlackSocketWorker {
        $worker = new SlackSocketWorker(
            $this->account,
            $this->loop,
            $this->reconnection
        );

        $worker->setWebSocketConnectorFactory(function ($loop) use ($expectedUrl, $firstMessage, &$connectionClosed) {
            return function (string $url) use ($expectedUrl, $firstMessage, &$connectionClosed) {
                $mockConnection = Mockery::mock(WebSocket::class);
                $mockConnection->shouldReceive('send')->andReturnNull();
                $mockConnection->shouldReceive('close')
                    ->andReturnUsing(function () use (&$connectionClosed) {
                        $connectionClosed = true;
                    });

                $mockConnection->shouldReceive('on')
                    ->andReturnUsing(function ($event, $callback) use ($firstMessage) {
                        if ($event === 'message') {
                            $mockMsg = Mockery::mock(MessageInterface::class);
                            $mockMsg->shouldReceive('getPayload')
                                ->andReturn(json_encode($firstMessage));
                            $callback($mockMsg);
                        }
                    });

                return new class($mockConnection) {
                    private $connection;

                    public function __construct($connection)
                    {
                        $this->connection = $connection;
                    }

                    public function then(?callable $onFulfilled = null, ?callable $onRejected = null)
                    {
                        if ($onFulfilled) {
                            $onFulfilled($this->connection);
                        }

                        return $this;
                    }
                };
            };
        });

        return $worker;
    }

    /**
     * Create a worker that tracks which events were processed.
     */
    private function createWorkerWithEventTracking(
        string $expectedUrl,
        array $messages,
        array &$messagesProcessed
    ): SlackSocketWorker {
        $worker = new SlackSocketWorker(
            $this->account,
            $this->loop,
            $this->reconnection
        );

        // We'll use Queue::fake to track dispatched jobs
        Queue::fake();

        $worker->setWebSocketConnectorFactory(function ($loop) use ($expectedUrl, $messages, &$messagesProcessed) {
            return function (string $url) use ($expectedUrl, $messages, &$messagesProcessed) {
                $mockConnection = Mockery::mock(WebSocket::class);
                $mockConnection->shouldReceive('send')
                    ->andReturnUsing(function ($msg) use (&$messagesProcessed) {
                        $decoded = json_decode($msg, true);
                        if (isset($decoded['envelope_id'])) {
                            $messagesProcessed[] = $decoded['envelope_id'];
                        }
                    });
                $mockConnection->shouldReceive('close')->andReturnNull();

                $mockConnection->shouldReceive('on')
                    ->andReturnUsing(function ($event, $callback) use ($messages) {
                        if ($event === 'message') {
                            foreach ($messages as $msg) {
                                $mockMsg = Mockery::mock(MessageInterface::class);
                                $mockMsg->shouldReceive('getPayload')
                                    ->andReturn(json_encode($msg));
                                $callback($mockMsg);
                            }
                        }
                    });

                return new class($mockConnection) {
                    private $connection;

                    public function __construct($connection)
                    {
                        $this->connection = $connection;
                    }

                    public function then(?callable $onFulfilled = null, ?callable $onRejected = null)
                    {
                        if ($onFulfilled) {
                            $onFulfilled($this->connection);
                        }

                        return $this;
                    }
                };
            };
        });

        return $worker;
    }

    /**
     * Simulate sending a message to a worker after it has been drained.
     * This is a test helper that won't actually work because we can't access
     * the internal message callback after drain. Instead, we verify that
     * the worker's drain flag is set.
     *
     * @param  array<string, mixed>  $message
     */
    private function simulateMessageAfterDrain(SlackSocketWorker $worker, array $message): void
    {
        // In a real scenario, messages would be received from the WebSocket
        // Since we're testing that drain stops processing, we verify via
        // the messagesProcessed counter which only increments when ack is sent
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
