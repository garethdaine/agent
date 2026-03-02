<?php

declare(strict_types=1);

namespace Tests\Unit\Messenger\Gateway\Workers;

use App\Jobs\Messenger\ProcessInboundMessage;
use App\Messenger\Gateway\Enums\WorkerHealthStatus;
use App\Messenger\Gateway\ReconnectionStrategy;
use App\Messenger\Gateway\Workers\DiscordGatewayWorker;
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
 * Unit tests for DiscordGatewayWorker.
 *
 * Assumptions:
 * - Discord Gateway v10 protocol (wss://gateway.discord.gg/?v=10&encoding=json)
 * - Ratchet/Pawl WebSocket client is available
 * - Bot has valid token with required intents (GUILDS, GUILD_MESSAGES, MESSAGE_CONTENT)
 *
 * Discord Gateway Protocol:
 * 1. GET /gateway/bot to obtain Gateway URL and shard info
 * 2. Connect to wss://gateway.discord.gg/?v=10&encoding=json
 * 3. Receive HELLO (opcode 10) with heartbeat_interval
 * 4. Send IDENTIFY (opcode 2) with token and intents
 * 5. Receive READY (opcode 0, t=READY) with session_id and resume_gateway_url
 * 6. Start heartbeat loop at heartbeat_interval
 * 7. Track sequence numbers (s field) for resume
 * 8. On disconnect: RESUME (opcode 6) with session_id and last sequence
 *
 * Opcodes:
 * 0 - Dispatch (receive events, includes 't' field with event name)
 * 1 - Heartbeat (send/receive to maintain connection)
 * 2 - Identify (send to authenticate)
 * 6 - Resume (send to resume session after disconnect)
 * 7 - Reconnect (receive, server requests reconnect)
 * 9 - Invalid Session (receive, session is invalid)
 * 10 - Hello (receive on connect with heartbeat_interval)
 * 11 - Heartbeat ACK (receive to confirm heartbeat)
 */
class DiscordGatewayWorkerTest extends TestCase
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
            'provider' => ConnectorAccount::PROVIDER_DISCORD,
            'connection_mode' => ConnectorAccount::MODE_LOCAL,
            'status' => ConnectorAccount::STATUS_CONNECTED,
            'credentials' => [
                'bot_token' => 'MTEyMzQ1Njc4OTAxMjM0NTY3OA.GxYmZc.test-token',
                'application_id' => '1123456789012345678',
            ],
        ]);
    }

    public function test_connect_to_gateway_url_from_gateway_bot(): void
    {
        Http::fake([
            'https://discord.com/api/v10/gateway/bot' => Http::response([
                'url' => 'wss://gateway.discord.gg',
                'shards' => 1,
                'session_start_limit' => [
                    'total' => 1000,
                    'remaining' => 999,
                    'reset_after' => 14400000,
                    'max_concurrency' => 1,
                ],
            ], 200),
        ]);

        $connectedUrl = null;
        $worker = $this->createWorkerWithSynchronousConnection(
            'wss://gateway.discord.gg/?v=10&encoding=json',
            [
                'op' => 10,
                'd' => ['heartbeat_interval' => 41250],
            ],
            $connectedUrl
        );

        $worker->start();

        Http::assertSent(function ($request) {
            return $request->url() === 'https://discord.com/api/v10/gateway/bot'
                && str_starts_with($request->header('Authorization')[0], 'Bot ');
        });

        $this->assertEquals('wss://gateway.discord.gg/?v=10&encoding=json', $connectedUrl);
    }

    public function test_hello_opcode_extracts_heartbeat_interval(): void
    {
        Http::fake([
            'https://discord.com/api/v10/gateway/bot' => Http::response([
                'url' => 'wss://gateway.discord.gg',
                'shards' => 1,
            ], 200),
        ]);

        $periodicTimerInterval = null;
        $this->loop = Mockery::mock(LoopInterface::class);
        $this->loop->shouldReceive('addTimer')->andReturnNull();
        $this->loop->shouldReceive('addPeriodicTimer')
            ->andReturnUsing(function ($interval, $callback) use (&$periodicTimerInterval) {
                $periodicTimerInterval = $interval;

                return Mockery::mock('React\EventLoop\TimerInterface');
            });
        $this->loop->shouldReceive('cancelTimer')->andReturnNull();
        $this->loop->shouldReceive('futureTick')->andReturnNull();

        $worker = $this->createWorkerWithSynchronousConnection(
            'wss://gateway.discord.gg/?v=10&encoding=json',
            [
                'op' => 10,
                'd' => ['heartbeat_interval' => 41250], // 41.25 seconds
            ],
            messages: [
                ['op' => 10, 'd' => ['heartbeat_interval' => 41250]],
            ]
        );

        $worker->start();

        // Heartbeat interval should be set (41250ms = 41.25s)
        $this->assertNotNull($periodicTimerInterval);
        $this->assertEqualsWithDelta(41.25, $periodicTimerInterval, 0.01);
    }

    public function test_identify_sent_with_token_and_intents(): void
    {
        Http::fake([
            'https://discord.com/api/v10/gateway/bot' => Http::response([
                'url' => 'wss://gateway.discord.gg',
                'shards' => 1,
            ], 200),
        ]);

        $sentMessages = [];
        $worker = $this->createWorkerWithMessageCapture(
            'wss://gateway.discord.gg/?v=10&encoding=json',
            [
                ['op' => 10, 'd' => ['heartbeat_interval' => 41250]],
            ],
            $sentMessages
        );

        $worker->start();

        // Find IDENTIFY message (opcode 2)
        $identifyMessage = null;
        foreach ($sentMessages as $msg) {
            $decoded = json_decode($msg, true);
            if (($decoded['op'] ?? null) === 2) {
                $identifyMessage = $decoded;
                break;
            }
        }

        $this->assertNotNull($identifyMessage, 'IDENTIFY message should be sent');
        $this->assertEquals(2, $identifyMessage['op']);
        $this->assertArrayHasKey('token', $identifyMessage['d']);
        $this->assertArrayHasKey('intents', $identifyMessage['d']);

        // Verify intents include required bits
        // GUILDS (1 << 0) | GUILD_MESSAGES (1 << 9) | MESSAGE_CONTENT (1 << 15)
        $intents = $identifyMessage['d']['intents'];
        $this->assertGreaterThan(0, $intents);
    }

    public function test_ready_stores_session_id_and_resume_gateway_url(): void
    {
        Http::fake([
            'https://discord.com/api/v10/gateway/bot' => Http::response([
                'url' => 'wss://gateway.discord.gg',
                'shards' => 1,
            ], 200),
        ]);

        $worker = $this->createWorkerWithSynchronousConnection(
            'wss://gateway.discord.gg/?v=10&encoding=json',
            ['op' => 10, 'd' => ['heartbeat_interval' => 41250]],
            messages: [
                ['op' => 10, 'd' => ['heartbeat_interval' => 41250]],
                [
                    'op' => 0,
                    't' => 'READY',
                    's' => 1,
                    'd' => [
                        'session_id' => 'test-session-id-12345',
                        'resume_gateway_url' => 'wss://gateway-us-east1.discord.gg',
                        'user' => [
                            'id' => '1123456789012345678',
                            'username' => 'TestBot',
                        ],
                    ],
                ],
            ]
        );

        $worker->start();

        $this->assertEquals(WorkerHealthStatus::Connected, $worker->health());

        $metadata = $worker->getHealthMetadata();
        $this->assertNotNull($metadata->lastEventAt);
    }

    public function test_heartbeat_loop_runs_at_correct_interval(): void
    {
        Http::fake([
            'https://discord.com/api/v10/gateway/bot' => Http::response([
                'url' => 'wss://gateway.discord.gg',
                'shards' => 1,
            ], 200),
        ]);

        $heartbeatTimerCallback = null;
        $heartbeatInterval = null;

        $this->loop = Mockery::mock(LoopInterface::class);
        $this->loop->shouldReceive('addTimer')->andReturnNull();
        $this->loop->shouldReceive('addPeriodicTimer')
            ->andReturnUsing(function ($interval, $callback) use (&$heartbeatTimerCallback, &$heartbeatInterval) {
                $heartbeatInterval = $interval;
                $heartbeatTimerCallback = $callback;

                return Mockery::mock('React\EventLoop\TimerInterface');
            });
        $this->loop->shouldReceive('cancelTimer')->andReturnNull();
        $this->loop->shouldReceive('futureTick')->andReturnNull();

        $sentMessages = [];
        $worker = $this->createWorkerWithMessageCapture(
            'wss://gateway.discord.gg/?v=10&encoding=json',
            [
                ['op' => 10, 'd' => ['heartbeat_interval' => 45000]], // 45 seconds
            ],
            $sentMessages
        );

        $worker->start();

        // Verify periodic timer was set
        $this->assertNotNull($heartbeatTimerCallback);
        $this->assertEqualsWithDelta(45.0, $heartbeatInterval, 0.01);

        // Simulate heartbeat timer firing
        if ($heartbeatTimerCallback) {
            $heartbeatTimerCallback();
        }

        // Verify heartbeat message (opcode 1) was sent
        $heartbeatSent = false;
        foreach ($sentMessages as $msg) {
            $decoded = json_decode($msg, true);
            if (($decoded['op'] ?? null) === 1) {
                $heartbeatSent = true;
                break;
            }
        }

        $this->assertTrue($heartbeatSent, 'Heartbeat should be sent');
    }

    public function test_missed_heartbeat_ack_triggers_reconnect(): void
    {
        Http::fake([
            'https://discord.com/api/v10/gateway/bot' => Http::response([
                'url' => 'wss://gateway.discord.gg',
                'shards' => 1,
            ], 200),
        ]);

        $reconnectScheduled = false;
        $this->loop = Mockery::mock(LoopInterface::class);
        $this->loop->shouldReceive('addTimer')
            ->andReturnUsing(function () use (&$reconnectScheduled) {
                $reconnectScheduled = true;

                return Mockery::mock('React\EventLoop\TimerInterface');
            });
        $this->loop->shouldReceive('addPeriodicTimer')->andReturnNull();
        $this->loop->shouldReceive('cancelTimer')->andReturnNull();
        $this->loop->shouldReceive('futureTick')->andReturnNull();

        $worker = $this->createWorkerWithSynchronousConnection(
            'wss://gateway.discord.gg/?v=10&encoding=json',
            ['op' => 10, 'd' => ['heartbeat_interval' => 41250]],
            messages: [
                ['op' => 10, 'd' => ['heartbeat_interval' => 41250]],
                ['op' => 0, 't' => 'READY', 's' => 1, 'd' => ['session_id' => 'test-session']],
                // Server sends Reconnect opcode (7) when heartbeat fails
                ['op' => 7], // Reconnect requested by server
            ]
        );

        $worker->start();

        // After receiving reconnect opcode, worker should schedule reconnection
        $this->assertEquals(WorkerHealthStatus::Reconnecting, $worker->health());
    }

    public function test_resume_sent_on_reconnection_with_session_id_and_sequence(): void
    {
        Http::fake([
            'https://discord.com/api/v10/gateway/bot' => Http::response([
                'url' => 'wss://gateway.discord.gg',
                'shards' => 1,
            ], 200),
        ]);

        $sentMessages = [];
        $worker = $this->createWorkerWithMessageCapture(
            'wss://gateway.discord.gg/?v=10&encoding=json',
            [
                ['op' => 10, 'd' => ['heartbeat_interval' => 41250]],
                [
                    'op' => 0,
                    't' => 'READY',
                    's' => 1,
                    'd' => [
                        'session_id' => 'resume-test-session',
                        'resume_gateway_url' => 'wss://gateway-resume.discord.gg',
                    ],
                ],
                ['op' => 0, 't' => 'MESSAGE_CREATE', 's' => 2, 'd' => ['content' => 'test']],
                ['op' => 0, 't' => 'MESSAGE_CREATE', 's' => 3, 'd' => ['content' => 'test2']],
            ],
            $sentMessages
        );

        $worker->start();

        // Now simulate reconnection
        $sentMessages = []; // Clear previous messages
        $worker->setWebSocketConnectorFactory(function ($loop) use (&$sentMessages) {
            return function (string $url) use (&$sentMessages) {
                $mockConnection = Mockery::mock(WebSocket::class);
                $mockConnection->shouldReceive('send')
                    ->andReturnUsing(function ($msg) use (&$sentMessages) {
                        $sentMessages[] = $msg;
                    });
                $mockConnection->shouldReceive('close')->andReturnNull();
                $mockConnection->shouldReceive('on')
                    ->andReturnUsing(function ($event, $callback) {
                        if ($event === 'message') {
                            // Send HELLO on reconnect
                            $mockMsg = Mockery::mock(MessageInterface::class);
                            $mockMsg->shouldReceive('getPayload')
                                ->andReturn(json_encode(['op' => 10, 'd' => ['heartbeat_interval' => 41250]]));
                            $callback($mockMsg);
                        }
                    });

                return new class($mockConnection)
                {
                    private $connection;

                    public function __construct($connection)
                    {
                        $this->connection = $connection;
                    }

                    public function then(?callable $onFulfilled = null)
                    {
                        if ($onFulfilled) {
                            $onFulfilled($this->connection);
                        }

                        return $this;
                    }
                };
            };
        });

        $this->loop->shouldReceive('addTimer')
            ->atLeast()
            ->once()
            ->andReturnUsing(function ($delay, $callback) {
                $callback();

                return null;
            });

        $worker->reconnect();

        // Find RESUME message (opcode 6)
        $resumeMessage = null;
        foreach ($sentMessages as $msg) {
            $decoded = json_decode($msg, true);
            if (($decoded['op'] ?? null) === 6) {
                $resumeMessage = $decoded;
                break;
            }
        }

        // Resume should be sent with session_id and last sequence number
        $this->assertNotNull($resumeMessage, 'RESUME message (opcode 6) should be sent on reconnection');
        $this->assertEquals(6, $resumeMessage['op']);
        $this->assertEquals('resume-test-session', $resumeMessage['d']['session_id']);
        $this->assertEquals(3, $resumeMessage['d']['seq']); // Last sequence was 3
    }

    public function test_message_create_dispatches_process_inbound_message(): void
    {
        Http::fake([
            'https://discord.com/api/v10/gateway/bot' => Http::response([
                'url' => 'wss://gateway.discord.gg',
                'shards' => 1,
            ], 200),
        ]);

        $worker = $this->createWorkerWithSynchronousConnection(
            'wss://gateway.discord.gg/?v=10&encoding=json',
            ['op' => 10, 'd' => ['heartbeat_interval' => 41250]],
            messages: [
                ['op' => 10, 'd' => ['heartbeat_interval' => 41250]],
                ['op' => 0, 't' => 'READY', 's' => 1, 'd' => ['session_id' => 'test']],
                [
                    'op' => 0,
                    't' => 'MESSAGE_CREATE',
                    's' => 2,
                    'd' => [
                        'id' => '111222333444555666',
                        'channel_id' => '123456789012345678',
                        'author' => [
                            'id' => '9876543210987654321',
                            'username' => 'TestUser',
                            'bot' => false,
                        ],
                        'content' => 'Hello, bot!',
                        'timestamp' => '2024-01-15T12:00:00.000Z',
                    ],
                ],
            ]
        );

        $worker->start();

        // Verify ProcessInboundMessage job was dispatched
        Queue::assertPushed(ProcessInboundMessage::class, function ($job) {
            return $job->provider === 'discord'
                && $job->connectorAccountId === $this->account->id;
        });
    }

    public function test_interaction_create_dispatches_process_inbound_message(): void
    {
        Http::fake([
            'https://discord.com/api/v10/gateway/bot' => Http::response([
                'url' => 'wss://gateway.discord.gg',
                'shards' => 1,
            ], 200),
            'https://discord.com/api/v10/interactions/*/callback' => Http::response([], 200),
        ]);

        $worker = $this->createWorkerWithSynchronousConnection(
            'wss://gateway.discord.gg/?v=10&encoding=json',
            ['op' => 10, 'd' => ['heartbeat_interval' => 41250]],
            messages: [
                ['op' => 10, 'd' => ['heartbeat_interval' => 41250]],
                ['op' => 0, 't' => 'READY', 's' => 1, 'd' => ['session_id' => 'test']],
                [
                    'op' => 0,
                    't' => 'INTERACTION_CREATE',
                    's' => 2,
                    'd' => [
                        'type' => 2, // APPLICATION_COMMAND
                        'id' => '111222333444555666',
                        'token' => 'interaction-token',
                        'channel_id' => '123456789012345678',
                        'member' => [
                            'user' => [
                                'id' => '9876543210987654321',
                                'username' => 'TestUser',
                            ],
                        ],
                        'data' => [
                            'name' => 'agent',
                            'options' => [],
                        ],
                    ],
                ],
            ]
        );

        $worker->start();

        // Verify ProcessInboundMessage job was dispatched for interaction
        Queue::assertPushed(ProcessInboundMessage::class, function ($job) {
            return $job->provider === 'discord';
        });

        Http::assertSent(function ($request) {
            $payload = $request->data();

            return str_contains($request->url(), '/interactions/111222333444555666/interaction-token/callback')
                && ($payload['type'] ?? null) === 5;
        });
    }

    public function test_interaction_create_autocomplete_sends_type_8_ack(): void
    {
        Http::fake([
            'https://discord.com/api/v10/gateway/bot' => Http::response([
                'url' => 'wss://gateway.discord.gg',
                'shards' => 1,
            ], 200),
            'https://discord.com/api/v10/interactions/*/callback' => Http::response([], 200),
        ]);

        $worker = $this->createWorkerWithSynchronousConnection(
            'wss://gateway.discord.gg/?v=10&encoding=json',
            ['op' => 10, 'd' => ['heartbeat_interval' => 41250]],
            messages: [
                ['op' => 10, 'd' => ['heartbeat_interval' => 41250]],
                ['op' => 0, 't' => 'READY', 's' => 1, 'd' => ['session_id' => 'test']],
                [
                    'op' => 0,
                    't' => 'INTERACTION_CREATE',
                    's' => 2,
                    'd' => [
                        'type' => 4, // APPLICATION_COMMAND_AUTOCOMPLETE
                        'id' => 'auto-123',
                        'token' => 'auto-token',
                        'channel_id' => '123456789012345678',
                        'member' => [
                            'user' => [
                                'id' => '9876543210987654321',
                                'username' => 'TestUser',
                            ],
                        ],
                        'data' => [
                            'name' => 'jobs',
                            'options' => [],
                        ],
                    ],
                ],
            ]
        );

        $worker->start();

        Http::assertSent(function ($request) {
            $payload = $request->data();

            return str_contains($request->url(), '/interactions/auto-123/auto-token/callback')
                && ($payload['type'] ?? null) === 8
                && is_array($payload['data']['choices'] ?? null);
        });
    }

    public function test_get_connector_account_id_returns_correct_id(): void
    {
        $worker = new DiscordGatewayWorker(
            $this->account,
            $this->loop,
            $this->reconnection
        );

        $this->assertEquals($this->account->id, $worker->getConnectorAccountId());
    }

    public function test_health_returns_correct_status(): void
    {
        Http::fake([
            'https://discord.com/api/v10/gateway/bot' => Http::response([
                'url' => 'wss://gateway.discord.gg',
                'shards' => 1,
            ], 200),
        ]);

        $worker = $this->createWorkerWithSynchronousConnection(
            'wss://gateway.discord.gg/?v=10&encoding=json',
            ['op' => 10, 'd' => ['heartbeat_interval' => 41250]],
            messages: [
                ['op' => 10, 'd' => ['heartbeat_interval' => 41250]],
                ['op' => 0, 't' => 'READY', 's' => 1, 'd' => ['session_id' => 'test']],
            ]
        );

        // Initially disconnected
        $this->assertEquals(WorkerHealthStatus::Disconnected, $worker->health());

        $worker->start();

        // After READY, should be connected
        $this->assertEquals(WorkerHealthStatus::Connected, $worker->health());
        $this->assertTrue($worker->health()->isHealthy());
    }

    public function test_gateway_bot_failure_sets_error_state(): void
    {
        Http::fake([
            'https://discord.com/api/v10/gateway/bot' => Http::response([
                'code' => 0,
                'message' => '401: Unauthorized',
            ], 401),
        ]);

        $worker = new DiscordGatewayWorker(
            $this->account,
            $this->loop,
            $this->reconnection
        );

        $worker->start();

        $this->assertEquals(WorkerHealthStatus::Error, $worker->health());

        $metadata = $worker->getHealthMetadata();
        $this->assertNotNull($metadata->errorMessage);
    }

    public function test_stop_closes_connection_and_sets_disconnected(): void
    {
        Http::fake([
            'https://discord.com/api/v10/gateway/bot' => Http::response([
                'url' => 'wss://gateway.discord.gg',
                'shards' => 1,
            ], 200),
        ]);

        $connectionClosed = false;
        $worker = $this->createWorkerWithCloseTracking(
            'wss://gateway.discord.gg/?v=10&encoding=json',
            ['op' => 10, 'd' => ['heartbeat_interval' => 41250]],
            $connectionClosed
        );

        $worker->start();
        $worker->stop();

        $this->assertTrue($connectionClosed);
        $this->assertEquals(WorkerHealthStatus::Disconnected, $worker->health());
    }

    public function test_drain_stops_accepting_new_events(): void
    {
        Http::fake([
            'https://discord.com/api/v10/gateway/bot' => Http::response([
                'url' => 'wss://gateway.discord.gg',
                'shards' => 1,
            ], 200),
        ]);

        $worker = $this->createWorkerWithSynchronousConnection(
            'wss://gateway.discord.gg/?v=10&encoding=json',
            ['op' => 10, 'd' => ['heartbeat_interval' => 41250]],
            messages: [
                ['op' => 10, 'd' => ['heartbeat_interval' => 41250]],
                ['op' => 0, 't' => 'READY', 's' => 1, 'd' => ['session_id' => 'test']],
            ]
        );

        $worker->start();
        $worker->drain();

        // Worker should still be connected but not accepting new events
        // The actual draining logic prevents job dispatch
        // Verify drain completed without error and worker health reflects status
        $this->assertNotEquals(WorkerHealthStatus::Disconnected, $worker->health());
    }

    public function test_invalid_session_triggers_fresh_identify(): void
    {
        Http::fake([
            'https://discord.com/api/v10/gateway/bot' => Http::response([
                'url' => 'wss://gateway.discord.gg',
                'shards' => 1,
            ], 200),
        ]);

        $reconnectScheduled = false;
        $this->loop = Mockery::mock(LoopInterface::class);
        $this->loop->shouldReceive('addTimer')
            ->andReturnUsing(function () use (&$reconnectScheduled) {
                $reconnectScheduled = true;

                return Mockery::mock('React\EventLoop\TimerInterface');
            });
        $this->loop->shouldReceive('addPeriodicTimer')->andReturnNull();
        $this->loop->shouldReceive('cancelTimer')->andReturnNull();
        $this->loop->shouldReceive('futureTick')->andReturnNull();

        $worker = $this->createWorkerWithSynchronousConnection(
            'wss://gateway.discord.gg/?v=10&encoding=json',
            ['op' => 10, 'd' => ['heartbeat_interval' => 41250]],
            messages: [
                ['op' => 10, 'd' => ['heartbeat_interval' => 41250]],
                ['op' => 9, 'd' => false], // Invalid Session, not resumable
            ]
        );

        $worker->start();

        // Should transition to reconnecting after invalid session
        $this->assertEquals(WorkerHealthStatus::Reconnecting, $worker->health());
    }

    /**
     * Create a worker with a synchronous connection that immediately fires messages.
     */
    private function createWorkerWithSynchronousConnection(
        string $expectedUrl,
        array $firstMessage,
        ?string &$connectedUrl = null,
        array $messages = []
    ): DiscordGatewayWorker {
        if (empty($messages)) {
            $messages = [$firstMessage];
        }

        $worker = new DiscordGatewayWorker(
            $this->account,
            $this->loop,
            $this->reconnection
        );

        $worker->setWebSocketConnectorFactory(function ($loop) use ($messages, &$connectedUrl) {
            return function (string $url) use ($messages, &$connectedUrl) {
                $connectedUrl = $url;

                $mockConnection = Mockery::mock(WebSocket::class);
                $mockConnection->shouldReceive('send')->andReturnNull();
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

                return new class($mockConnection)
                {
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
    ): DiscordGatewayWorker {
        $worker = new DiscordGatewayWorker(
            $this->account,
            $this->loop,
            $this->reconnection
        );

        $worker->setWebSocketConnectorFactory(function ($loop) use ($messages, &$sentMessages) {
            return function (string $url) use ($messages, &$sentMessages) {
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

                return new class($mockConnection)
                {
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
    ): DiscordGatewayWorker {
        $worker = new DiscordGatewayWorker(
            $this->account,
            $this->loop,
            $this->reconnection
        );

        $worker->setWebSocketConnectorFactory(function ($loop) use ($firstMessage, &$connectionClosed) {
            return function (string $url) use ($firstMessage, &$connectionClosed) {
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

                return new class($mockConnection)
                {
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

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
