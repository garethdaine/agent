<?php

declare(strict_types=1);

namespace Tests\Unit\Messenger\Gateway\Workers;

use App\Jobs\Messenger\ProcessInboundMessage;
use App\Messenger\Gateway\DTOs\WorkerHealthMetadata;
use App\Messenger\Gateway\Enums\WorkerHealthStatus;
use App\Messenger\Gateway\ReconnectionStrategy;
use App\Messenger\Gateway\Workers\TelegramPollingWorker;
use App\Models\ConnectorAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Mockery\MockInterface;
use React\EventLoop\LoopInterface;
use Tests\TestCase;

/**
 * Unit tests for TelegramPollingWorker.
 *
 * Assumptions:
 * - Bot token grants access to getUpdates and deleteWebhook APIs
 * - ProcessInboundMessage job handles Telegram update payloads
 * - ReactPHP HTTP client used for async requests (mocked with Laravel HTTP facade for unit tests)
 *
 * Telegram Bot API Long-Polling Protocol:
 * 1. Call deleteWebhook to clear any existing webhook (required before getUpdates)
 * 2. Call getUpdates with offset, timeout, and allowed_updates parameters
 * 3. Process returned updates array
 * 4. Update offset to max(update_id) + 1 after processing
 * 5. Continue polling loop until stopped
 *
 * Testing Approach:
 * These tests use Laravel's HTTP::fake() to mock Telegram API responses synchronously.
 * The worker's polling loop is simulated through explicit pollOnce() calls or by
 * configuring the HTTP mock sequence.
 */
class TelegramPollingWorkerTest extends TestCase
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
            'provider' => ConnectorAccount::PROVIDER_TELEGRAM,
            'connection_mode' => ConnectorAccount::MODE_LOCAL,
            'status' => ConnectorAccount::STATUS_CONNECTED,
            'credentials' => [
                'bot_token' => '123456789:ABCdefGHIjklMNOpqrSTUvwxYZ0123456789',
            ],
        ]);
    }

    public function test_start_calls_delete_webhook_to_clear_any_existing_webhook(): void
    {
        Http::fake([
            'https://api.telegram.org/bot*/deleteWebhook' => Http::response([
                'ok' => true,
                'result' => true,
                'description' => 'Webhook was deleted',
            ], 200),
            'https://api.telegram.org/bot*/getUpdates*' => Http::response([
                'ok' => true,
                'result' => [],
            ], 200),
        ]);

        $worker = new TelegramPollingWorker(
            $this->account,
            $this->loop,
            $this->reconnection
        );

        $worker->start();

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'deleteWebhook');
        });
    }

    public function test_polling_loop_calls_get_updates_with_correct_offset_and_timeout(): void
    {
        Http::fake([
            'https://api.telegram.org/bot*/deleteWebhook' => Http::response([
                'ok' => true,
                'result' => true,
            ], 200),
            'https://api.telegram.org/bot*/getUpdates*' => Http::response([
                'ok' => true,
                'result' => [],
            ], 200),
        ]);

        $worker = new TelegramPollingWorker(
            $this->account,
            $this->loop,
            $this->reconnection
        );

        $worker->start();

        // Verify getUpdates was called with correct parameters
        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), 'getUpdates')) {
                return false;
            }

            $body = json_decode($request->body(), true);

            return ($body['offset'] ?? null) === 0
                && ($body['timeout'] ?? null) === 30
                && in_array('message', $body['allowed_updates'] ?? []);
        });
    }

    public function test_offset_updates_to_max_update_id_plus_one_after_processing(): void
    {
        Http::fake([
            'https://api.telegram.org/bot*/deleteWebhook' => Http::response([
                'ok' => true,
                'result' => true,
            ], 200),
        ]);

        // First poll returns updates with IDs 100, 101, 102
        Http::fake([
            'https://api.telegram.org/bot*/getUpdates*' => Http::sequence()
                ->push([
                    'ok' => true,
                    'result' => [
                        ['update_id' => 100, 'message' => ['text' => 'Hello', 'chat' => ['id' => 123]]],
                        ['update_id' => 101, 'message' => ['text' => 'World', 'chat' => ['id' => 123]]],
                        ['update_id' => 102, 'message' => ['text' => '!', 'chat' => ['id' => 123]]],
                    ],
                ], 200)
                ->push([
                    'ok' => true,
                    'result' => [],
                ], 200),
            'https://api.telegram.org/bot*/deleteWebhook' => Http::response([
                'ok' => true,
                'result' => true,
            ], 200),
        ]);

        $worker = new TelegramPollingWorker(
            $this->account,
            $this->loop,
            $this->reconnection
        );

        $worker->start();

        // Verify offset advanced to 103 (max update_id 102 + 1)
        $this->assertEquals(103, $worker->getOffset());
    }

    public function test_each_update_dispatches_process_inbound_message_job(): void
    {
        Http::fake([
            'https://api.telegram.org/bot*/deleteWebhook' => Http::response([
                'ok' => true,
                'result' => true,
            ], 200),
            'https://api.telegram.org/bot*/getUpdates*' => Http::response([
                'ok' => true,
                'result' => [
                    [
                        'update_id' => 100,
                        'message' => [
                            'message_id' => 1,
                            'text' => 'Hello, world!',
                            'chat' => ['id' => 123, 'type' => 'private'],
                            'from' => ['id' => 456, 'first_name' => 'John'],
                            'date' => time(),
                        ],
                    ],
                    [
                        'update_id' => 101,
                        'message' => [
                            'message_id' => 2,
                            'text' => 'Second message',
                            'chat' => ['id' => 123, 'type' => 'private'],
                            'from' => ['id' => 456, 'first_name' => 'John'],
                            'date' => time(),
                        ],
                    ],
                ],
            ], 200),
        ]);

        $worker = new TelegramPollingWorker(
            $this->account,
            $this->loop,
            $this->reconnection
        );

        $worker->start();

        // Verify ProcessInboundMessage was dispatched for each update
        Queue::assertPushed(ProcessInboundMessage::class, 2);

        Queue::assertPushed(ProcessInboundMessage::class, function ($job) {
            return $job->provider === ConnectorAccount::PROVIDER_TELEGRAM;
        });
    }

    public function test_empty_response_timeout_triggers_immediate_repoll(): void
    {
        $pollCount = 0;

        Http::fake([
            'https://api.telegram.org/bot*/deleteWebhook' => Http::response([
                'ok' => true,
                'result' => true,
            ], 200),
            'https://api.telegram.org/bot*/getUpdates*' => function () use (&$pollCount) {
                $pollCount++;

                return Http::response([
                    'ok' => true,
                    'result' => [], // Empty response (timeout)
                ], 200);
            },
        ]);

        // Configure loop to capture futureTick calls (indicates immediate re-poll)
        $futureTickCalled = false;
        $this->loop = Mockery::mock(LoopInterface::class);
        $this->loop->shouldReceive('addTimer')->andReturnNull();
        $this->loop->shouldReceive('addPeriodicTimer')->andReturnNull();
        $this->loop->shouldReceive('cancelTimer')->andReturnNull();
        $this->loop->shouldReceive('futureTick')
            ->andReturnUsing(function ($callback) use (&$futureTickCalled) {
                $futureTickCalled = true;
                // Don't actually call the callback to avoid infinite loop
            });

        $worker = new TelegramPollingWorker(
            $this->account,
            $this->loop,
            $this->reconnection
        );

        $worker->start();

        // Empty response should trigger futureTick for immediate re-poll
        $this->assertTrue($futureTickCalled, 'Empty response should trigger immediate re-poll via futureTick');
    }

    public function test_http_error_triggers_reconnection_strategy(): void
    {
        Http::fake([
            'https://api.telegram.org/bot*/deleteWebhook' => Http::response([
                'ok' => true,
                'result' => true,
            ], 200),
            'https://api.telegram.org/bot*/getUpdates*' => Http::response([
                'ok' => false,
                'error_code' => 500,
                'description' => 'Internal Server Error',
            ], 500),
        ]);

        $timerScheduled = false;
        $this->loop = Mockery::mock(LoopInterface::class);
        $this->loop->shouldReceive('addTimer')
            ->once()
            ->andReturnUsing(function ($delay, $callback) use (&$timerScheduled) {
                $timerScheduled = true;

                return Mockery::mock('React\EventLoop\TimerInterface');
            });
        $this->loop->shouldReceive('addPeriodicTimer')->andReturnNull();
        $this->loop->shouldReceive('cancelTimer')->andReturnNull();
        $this->loop->shouldReceive('futureTick')->andReturnNull();

        $worker = new TelegramPollingWorker(
            $this->account,
            $this->loop,
            $this->reconnection
        );

        $worker->start();

        // After HTTP error, status should be error and reconnection scheduled
        $this->assertEquals(WorkerHealthStatus::Error, $worker->health());
        $this->assertTrue($timerScheduled, 'Reconnection timer should be scheduled after HTTP error');
    }

    public function test_stop_cancels_pending_requests_and_exits_loop(): void
    {
        Http::fake([
            'https://api.telegram.org/bot*/deleteWebhook' => Http::response([
                'ok' => true,
                'result' => true,
            ], 200),
            'https://api.telegram.org/bot*/getUpdates*' => Http::response([
                'ok' => true,
                'result' => [],
            ], 200),
        ]);

        $worker = new TelegramPollingWorker(
            $this->account,
            $this->loop,
            $this->reconnection
        );

        $worker->start();
        $this->assertTrue($worker->isRunning());

        $worker->stop();

        $this->assertFalse($worker->isRunning());
        $this->assertEquals(WorkerHealthStatus::Disconnected, $worker->health());
    }

    public function test_health_returns_correct_worker_health_status(): void
    {
        Http::fake([
            'https://api.telegram.org/bot*/deleteWebhook' => Http::response([
                'ok' => true,
                'result' => true,
            ], 200),
            'https://api.telegram.org/bot*/getUpdates*' => Http::response([
                'ok' => true,
                'result' => [],
            ], 200),
        ]);

        $worker = new TelegramPollingWorker(
            $this->account,
            $this->loop,
            $this->reconnection
        );

        // Initially disconnected
        $this->assertEquals(WorkerHealthStatus::Disconnected, $worker->health());

        $worker->start();

        // After successful start and first poll, should be connected
        $this->assertEquals(WorkerHealthStatus::Connected, $worker->health());
        $this->assertTrue($worker->health()->isHealthy());

        // Check metadata
        $metadata = $worker->getHealthMetadata();
        $this->assertNull($metadata->errorMessage);
        $this->assertGreaterThanOrEqual(0, $metadata->connectionUptime);
    }

    public function test_get_connector_account_id_returns_correct_id(): void
    {
        $worker = new TelegramPollingWorker(
            $this->account,
            $this->loop,
            $this->reconnection
        );

        $this->assertEquals($this->account->id, $worker->getConnectorAccountId());
    }

    public function test_drain_stops_accepting_new_events(): void
    {
        Http::fake([
            'https://api.telegram.org/bot*/deleteWebhook' => Http::response([
                'ok' => true,
                'result' => true,
            ], 200),
            'https://api.telegram.org/bot*/getUpdates*' => Http::response([
                'ok' => true,
                'result' => [
                    [
                        'update_id' => 100,
                        'message' => [
                            'message_id' => 1,
                            'text' => 'Hello',
                            'chat' => ['id' => 123, 'type' => 'private'],
                            'from' => ['id' => 456, 'first_name' => 'John'],
                            'date' => time(),
                        ],
                    ],
                ],
            ], 200),
        ]);

        $worker = new TelegramPollingWorker(
            $this->account,
            $this->loop,
            $this->reconnection
        );

        $worker->start();

        // First event should be processed
        Queue::assertPushed(ProcessInboundMessage::class, 1);

        // Drain the worker
        $worker->drain();

        // Worker should be in draining state and not poll again
        $this->assertTrue($worker->isDraining());
    }

    public function test_reconnect_stops_current_polling_and_schedules_new_start(): void
    {
        Http::fake([
            'https://api.telegram.org/bot*/deleteWebhook' => Http::response([
                'ok' => true,
                'result' => true,
            ], 200),
            'https://api.telegram.org/bot*/getUpdates*' => Http::response([
                'ok' => true,
                'result' => [],
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

        $worker = new TelegramPollingWorker(
            $this->account,
            $this->loop,
            $this->reconnection
        );

        $worker->start();
        $this->assertEquals(WorkerHealthStatus::Connected, $worker->health());

        $worker->reconnect();

        // Should be in reconnecting state
        $this->assertEquals(WorkerHealthStatus::Reconnecting, $worker->health());
        // Should have scheduled reconnection timer
        $this->assertTrue($timerScheduled, 'Reconnection timer should be scheduled');
    }

    public function test_delete_webhook_failure_sets_error_state(): void
    {
        Http::fake([
            'https://api.telegram.org/bot*/deleteWebhook' => Http::response([
                'ok' => false,
                'error_code' => 401,
                'description' => 'Unauthorized',
            ], 401),
        ]);

        $worker = new TelegramPollingWorker(
            $this->account,
            $this->loop,
            $this->reconnection
        );

        $worker->start();

        $this->assertEquals(WorkerHealthStatus::Error, $worker->health());

        $metadata = $worker->getHealthMetadata();
        $this->assertNotNull($metadata->errorMessage);
        $this->assertStringContainsString('deleteWebhook failed', $metadata->errorMessage);
    }

    public function test_delete_webhook_api_error_includes_description(): void
    {
        Http::fake([
            'https://api.telegram.org/bot*/deleteWebhook' => Http::response([
                'ok' => false,
                'error_code' => 401,
                'description' => 'Unauthorized: invalid bot token',
            ], 200), // Telegram returns 200 with ok=false for API errors
        ]);

        $worker = new TelegramPollingWorker(
            $this->account,
            $this->loop,
            $this->reconnection
        );

        $worker->start();

        $this->assertEquals(WorkerHealthStatus::Error, $worker->health());

        $metadata = $worker->getHealthMetadata();
        $this->assertNotNull($metadata->errorMessage);
        $this->assertStringContainsString('Unauthorized', $metadata->errorMessage);
    }

    public function test_handles_callback_query_updates(): void
    {
        Http::fake([
            'https://api.telegram.org/bot*/deleteWebhook' => Http::response([
                'ok' => true,
                'result' => true,
            ], 200),
            'https://api.telegram.org/bot*/getUpdates*' => Http::response([
                'ok' => true,
                'result' => [
                    [
                        'update_id' => 100,
                        'callback_query' => [
                            'id' => 'callback123',
                            'from' => ['id' => 456, 'first_name' => 'John'],
                            'message' => [
                                'message_id' => 1,
                                'chat' => ['id' => 123, 'type' => 'private'],
                            ],
                            'data' => 'button_clicked',
                        ],
                    ],
                ],
            ], 200),
        ]);

        $worker = new TelegramPollingWorker(
            $this->account,
            $this->loop,
            $this->reconnection
        );

        $worker->start();

        // Verify callback_query update was dispatched
        Queue::assertPushed(ProcessInboundMessage::class, 1);
    }

    public function test_missing_bot_token_sets_error_state(): void
    {
        $accountWithoutToken = ConnectorAccount::factory()->create([
            'provider' => ConnectorAccount::PROVIDER_TELEGRAM,
            'connection_mode' => ConnectorAccount::MODE_LOCAL,
            'credentials' => [], // No bot_token
        ]);

        $worker = new TelegramPollingWorker(
            $accountWithoutToken,
            $this->loop,
            $this->reconnection
        );

        $worker->start();

        $this->assertEquals(WorkerHealthStatus::Error, $worker->health());

        $metadata = $worker->getHealthMetadata();
        $this->assertNotNull($metadata->errorMessage);
        $this->assertStringContainsString('bot_token', $metadata->errorMessage);
    }

    public function test_preserves_offset_across_polling_cycles(): void
    {
        Http::fake([
            'https://api.telegram.org/bot*/deleteWebhook' => Http::response([
                'ok' => true,
                'result' => true,
            ], 200),
            'https://api.telegram.org/bot*/getUpdates*' => Http::sequence()
                ->push([
                    'ok' => true,
                    'result' => [
                        ['update_id' => 100, 'message' => ['text' => 'First', 'chat' => ['id' => 123]]],
                    ],
                ], 200)
                ->push([
                    'ok' => true,
                    'result' => [
                        ['update_id' => 105, 'message' => ['text' => 'Second', 'chat' => ['id' => 123]]],
                    ],
                ], 200)
                ->push([
                    'ok' => true,
                    'result' => [],
                ], 200),
        ]);

        // Mock futureTick to actually call the callback for testing polling progression
        $callbacks = [];
        $this->loop = Mockery::mock(LoopInterface::class);
        $this->loop->shouldReceive('addTimer')->andReturnNull();
        $this->loop->shouldReceive('addPeriodicTimer')->andReturnNull();
        $this->loop->shouldReceive('cancelTimer')->andReturnNull();
        $this->loop->shouldReceive('futureTick')
            ->andReturnUsing(function ($callback) use (&$callbacks) {
                $callbacks[] = $callback;
            });

        $worker = new TelegramPollingWorker(
            $this->account,
            $this->loop,
            $this->reconnection
        );

        $worker->start();
        $this->assertEquals(101, $worker->getOffset()); // First batch: 100 + 1

        // Execute first re-poll callback
        if (! empty($callbacks)) {
            array_shift($callbacks)();
        }

        $this->assertEquals(106, $worker->getOffset()); // Second batch: 105 + 1
    }

    public function test_api_url_uses_correct_bot_token(): void
    {
        $customToken = '987654321:ZYXwvuTSRqponMLKjihGFEdcbaABCDEFGHI';
        $accountWithCustomToken = ConnectorAccount::factory()->create([
            'provider' => ConnectorAccount::PROVIDER_TELEGRAM,
            'connection_mode' => ConnectorAccount::MODE_LOCAL,
            'credentials' => [
                'bot_token' => $customToken,
            ],
        ]);

        Http::fake([
            "https://api.telegram.org/bot{$customToken}/deleteWebhook" => Http::response([
                'ok' => true,
                'result' => true,
            ], 200),
            "https://api.telegram.org/bot{$customToken}/getUpdates*" => Http::response([
                'ok' => true,
                'result' => [],
            ], 200),
        ]);

        $worker = new TelegramPollingWorker(
            $accountWithCustomToken,
            $this->loop,
            $this->reconnection
        );

        $worker->start();

        Http::assertSent(function ($request) use ($customToken) {
            return str_contains($request->url(), "bot{$customToken}");
        });
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
