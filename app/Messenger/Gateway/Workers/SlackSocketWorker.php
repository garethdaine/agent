<?php

declare(strict_types=1);

namespace App\Messenger\Gateway\Workers;

use App\Jobs\Messenger\ProcessInboundMessage;
use App\Messenger\Gateway\Contracts\GatewayWorkerInterface;
use App\Messenger\Gateway\DTOs\WorkerHealthMetadata;
use App\Messenger\Gateway\Enums\WorkerHealthStatus;
use App\Messenger\Gateway\ReconnectionStrategy;
use App\Models\ConnectorAccount;
use Carbon\CarbonInterface;
use Closure;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Ratchet\Client\WebSocket;
use Ratchet\RFC6455\Messaging\MessageInterface;
use React\EventLoop\LoopInterface;

/**
 * Gateway worker for Slack Socket Mode API v2.
 *
 * Socket Mode allows the application to receive events from Slack over a WebSocket
 * connection instead of requiring a public webhook URL. This is useful for local
 * development or environments without public internet access.
 *
 * Connection Flow:
 * 1. Call apps.connections.open with app-level token to get WebSocket URL
 * 2. Connect to the returned wss:// URL
 * 3. Receive 'hello' event confirming connection
 * 4. Process events and acknowledge with envelope_id within 3 seconds
 * 5. Respond to 'disconnect' events by reconnecting
 *
 * Token Requirements:
 * - App token (xapp-*): For Socket Mode WebSocket connection
 * - Bot token (xoxb-*): For API calls (sending messages, etc.)
 *
 * @see https://api.slack.com/apis/connections/socket
 */
class SlackSocketWorker implements GatewayWorkerInterface
{
    private const SLACK_API_BASE = 'https://slack.com/api';

    private ?WebSocket $connection = null;

    private WorkerHealthStatus $status = WorkerHealthStatus::Disconnected;

    private ?CarbonInterface $connectedAt = null;

    private ?CarbonInterface $lastEventAt = null;

    private ?string $errorMessage = null;

    private bool $draining = false;

    /**
     * Count of in-flight operations (dispatched jobs awaiting completion).
     */
    private int $pendingOperations = 0;

    /**
     * Timeout in seconds for graceful drain (configurable via messenger.gateway.shutdown_timeout).
     */
    private int $drainTimeoutSeconds = 30;

    /**
     * Factory for creating WebSocket connectors (injectable for testing).
     */
    private ?Closure $webSocketConnectorFactory = null;

    public function __construct(
        private readonly ConnectorAccount $account,
        private readonly LoopInterface $loop,
        private readonly ReconnectionStrategy $reconnection,
    ) {}

    /**
     * Inject a custom WebSocket connector factory for testing.
     */
    public function setWebSocketConnectorFactory(Closure $factory): void
    {
        $this->webSocketConnectorFactory = $factory;
    }

    /**
     * {@inheritdoc}
     */
    public function start(): void
    {
        $this->draining = false;
        $this->status = WorkerHealthStatus::Reconnecting;
        $this->errorMessage = null;

        Log::info('SlackSocketWorker starting', [
            'account_id' => $this->account->id,
        ]);

        // Step 1: Get WebSocket URL from apps.connections.open
        $wsUrl = $this->getWebSocketUrl();

        if ($wsUrl === null) {
            return; // Error already set
        }

        // Step 2: Connect to WebSocket
        $this->connectWebSocket($wsUrl);
    }

    /**
     * {@inheritdoc}
     */
    public function stop(): void
    {
        Log::info('SlackSocketWorker stopping', [
            'account_id' => $this->account->id,
        ]);

        if ($this->connection !== null) {
            $this->connection->close();
            $this->connection = null;
        }

        $this->status = WorkerHealthStatus::Disconnected;
        $this->connectedAt = null;
    }

    /**
     * {@inheritdoc}
     */
    public function health(): WorkerHealthStatus
    {
        return $this->status;
    }

    /**
     * {@inheritdoc}
     */
    public function reconnect(): void
    {
        Log::info('SlackSocketWorker reconnecting', [
            'account_id' => $this->account->id,
        ]);

        // Close existing connection if any
        if ($this->connection !== null) {
            $this->connection->close();
            $this->connection = null;
        }

        $this->status = WorkerHealthStatus::Reconnecting;
        $this->connectedAt = null;

        // Schedule reconnection with backoff
        $this->scheduleReconnect();
    }

    /**
     * {@inheritdoc}
     *
     * Gracefully drains the worker by:
     * 1. Setting the draining flag to stop accepting new events
     * 2. Polling every 100ms to check if all pending operations have completed
     * 3. Stopping the loop when operations complete or timeout is reached
     */
    public function drain(): void
    {
        Log::info('SlackSocketWorker draining', [
            'account_id' => $this->account->id,
            'pending_operations' => $this->pendingOperations,
        ]);

        $this->draining = true;
        $this->drainTimeoutSeconds = (int) config('messenger.gateway.shutdown_timeout', 30);
        $startTime = time();

        // Poll every 100ms to check if pending operations have completed
        $timer = $this->loop->addPeriodicTimer(0.1, function ($timer) use ($startTime) {
            // All operations completed - drain successful
            if ($this->pendingOperations === 0) {
                $this->loop->cancelTimer($timer);
                Log::info('SlackSocketWorker drain completed', [
                    'account_id' => $this->account->id,
                ]);
                $this->loop->stop();

                return;
            }

            // Timeout exceeded - force stop with warning
            if ((time() - $startTime) >= $this->drainTimeoutSeconds) {
                $this->loop->cancelTimer($timer);
                Log::warning('SlackSocketWorker drain timeout exceeded', [
                    'account_id' => $this->account->id,
                    'pending_operations' => $this->pendingOperations,
                    'timeout_seconds' => $this->drainTimeoutSeconds,
                ]);
                $this->loop->stop();
            }
        });
    }

    /**
     * {@inheritdoc}
     */
    public function getHealthMetadata(): WorkerHealthMetadata
    {
        if ($this->status === WorkerHealthStatus::Error) {
            return WorkerHealthMetadata::error(
                $this->errorMessage ?? 'Unknown error',
                $this->lastEventAt
            );
        }

        if ($this->status === WorkerHealthStatus::Disconnected) {
            return WorkerHealthMetadata::disconnected($this->lastEventAt);
        }

        $uptime = $this->connectedAt !== null
            ? (int) $this->connectedAt->diffInSeconds(now())
            : 0;

        return WorkerHealthMetadata::connected(
            $this->lastEventAt ?? now(),
            $uptime
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getConnectorAccountId(): string
    {
        return (string) $this->account->id;
    }

    /**
     * Get WebSocket URL from Slack's apps.connections.open API.
     */
    private function getWebSocketUrl(): ?string
    {
        $appToken = $this->account->credentials['app_token'] ?? null;

        if (empty($appToken)) {
            $this->setError('Missing app_token in credentials');

            return null;
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$appToken,
                'Content-Type' => 'application/x-www-form-urlencoded',
            ])->post(self::SLACK_API_BASE.'/apps.connections.open');

            if (! $response->successful()) {
                $this->setError('apps.connections.open failed: HTTP '.$response->status());

                return null;
            }

            $data = $response->json();

            if (! ($data['ok'] ?? false)) {
                $error = $data['error'] ?? 'unknown_error';
                $this->setError('apps.connections.open failed: '.$error);

                return null;
            }

            $url = $data['url'] ?? null;

            if (empty($url)) {
                $this->setError('apps.connections.open returned no URL');

                return null;
            }

            Log::debug('Got WebSocket URL from Slack', [
                'account_id' => $this->account->id,
                'url' => substr($url, 0, 50).'...',
            ]);

            return $url;
        } catch (\Exception $e) {
            $this->setError('apps.connections.open exception: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Connect to the WebSocket URL.
     */
    private function connectWebSocket(string $url): void
    {
        $connector = $this->createWebSocketConnector();

        $connector($url)->then(
            function (WebSocket $conn) {
                $this->connection = $conn;

                Log::debug('WebSocket connected', [
                    'account_id' => $this->account->id,
                ]);

                // Set up message handler
                $conn->on('message', function (MessageInterface $msg) {
                    $this->handleMessage($msg);
                });

                // Set up close handler
                $conn->on('close', function ($code = null, $reason = null) {
                    Log::info('WebSocket closed', [
                        'account_id' => $this->account->id,
                        'code' => $code,
                        'reason' => $reason,
                    ]);

                    $this->connection = null;

                    // Only reconnect if not deliberately stopped
                    if ($this->status !== WorkerHealthStatus::Disconnected && ! $this->draining) {
                        $this->status = WorkerHealthStatus::Reconnecting;
                        $this->scheduleReconnect();
                    }
                });

                // Set up error handler
                $conn->on('error', function (\Exception $e) {
                    Log::error('WebSocket error', [
                        'account_id' => $this->account->id,
                        'error' => $e->getMessage(),
                    ]);

                    $this->setError('WebSocket error: '.$e->getMessage());
                });
            },
            function (\Exception $e) {
                Log::error('WebSocket connection failed', [
                    'account_id' => $this->account->id,
                    'error' => $e->getMessage(),
                ]);

                $this->setError('WebSocket connection failed: '.$e->getMessage());
                $this->scheduleReconnect();
            }
        );
    }

    /**
     * Handle incoming WebSocket message.
     */
    private function handleMessage(MessageInterface $msg): void
    {
        $payload = $msg->getPayload();
        $data = json_decode($payload, true);

        if (! is_array($data)) {
            Log::warning('Invalid JSON from WebSocket', [
                'account_id' => $this->account->id,
                'payload' => substr($payload, 0, 100),
            ]);

            return;
        }

        $type = $data['type'] ?? null;

        Log::debug('Received WebSocket message', [
            'account_id' => $this->account->id,
            'type' => $type,
        ]);

        match ($type) {
            'hello' => $this->handleHello($data),
            'disconnect' => $this->handleDisconnect($data),
            'ping' => $this->handlePing($data),
            default => $this->handleEvent($data),
        };
    }

    /**
     * Handle 'hello' event - connection confirmed.
     *
     * @param  array<string, mixed>  $data
     */
    private function handleHello(array $data): void
    {
        Log::info('Slack Socket Mode connected', [
            'account_id' => $this->account->id,
            'connection_info' => $data['connection_info'] ?? null,
        ]);

        $this->status = WorkerHealthStatus::Connected;
        $this->connectedAt = now();
        $this->lastEventAt = now();
        $this->errorMessage = null;

        // Record successful connection for reconnection strategy
        $this->reconnection->recordConnectionStart();
    }

    /**
     * Handle 'disconnect' event - server requests reconnection.
     *
     * @param  array<string, mixed>  $data
     */
    private function handleDisconnect(array $data): void
    {
        $reason = $data['reason'] ?? 'unknown';

        Log::info('Slack disconnect event received', [
            'account_id' => $this->account->id,
            'reason' => $reason,
        ]);

        $this->status = WorkerHealthStatus::Reconnecting;
        $this->connectedAt = null;

        // Close connection and schedule reconnect
        if ($this->connection !== null) {
            $this->connection->close();
            $this->connection = null;
        }

        $this->scheduleReconnect();
    }

    /**
     * Handle 'ping' event - respond with acknowledgment.
     *
     * @param  array<string, mixed>  $data
     */
    private function handlePing(array $data): void
    {
        $envelopeId = $data['envelope_id'] ?? null;

        if ($envelopeId !== null && $this->connection !== null) {
            $this->sendAcknowledgment($envelopeId);
        }

        $this->lastEventAt = now();
    }

    /**
     * Handle other events (events_api, interactive, etc.).
     *
     * @param  array<string, mixed>  $data
     */
    private function handleEvent(array $data): void
    {
        // Don't process events if draining
        if ($this->draining) {
            Log::debug('Ignoring event during drain', [
                'account_id' => $this->account->id,
                'type' => $data['type'] ?? 'unknown',
            ]);

            return;
        }

        $envelopeId = $data['envelope_id'] ?? null;
        $payload = $data['payload'] ?? null;

        if ($envelopeId === null) {
            // No envelope_id means no acknowledgment needed
            return;
        }

        // Dispatch to ProcessChatIntent job for processing
        if ($payload !== null) {
            $this->dispatchEvent($payload);
        }

        // Acknowledge the event
        $this->sendAcknowledgment($envelopeId);
        $this->lastEventAt = now();
    }

    /**
     * Dispatch event to ProcessInboundMessage job.
     *
     * ProcessInboundMessage handles:
     * - Parsing the raw payload via the SlackAdapter
     * - Identity link validation
     * - Session management
     * - Message creation with idempotency
     * - Dispatching to ProcessChatIntent
     *
     * The pending operations counter is incremented before dispatch and
     * decremented after, ensuring graceful drain waits for all dispatches.
     *
     * @param  array<string, mixed>  $payload
     */
    private function dispatchEvent(array $payload): void
    {
        $this->pendingOperations++;

        try {
            Log::debug('Dispatching event to ProcessInboundMessage', [
                'account_id' => $this->account->id,
                'event_type' => $payload['event']['type'] ?? 'unknown',
                'pending_operations' => $this->pendingOperations,
            ]);

            ProcessInboundMessage::dispatch(
                connectorAccountId: (string) $this->account->id,
                provider: $this->account->provider,
                payload: $payload
            );
        } finally {
            $this->pendingOperations--;
        }
    }

    /**
     * Send acknowledgment for an envelope.
     */
    private function sendAcknowledgment(string $envelopeId): void
    {
        if ($this->connection === null) {
            return;
        }

        $ack = json_encode(['envelope_id' => $envelopeId]);
        $this->connection->send($ack);

        Log::debug('Sent acknowledgment', [
            'account_id' => $this->account->id,
            'envelope_id' => $envelopeId,
        ]);
    }

    /**
     * Schedule a reconnection attempt with exponential backoff.
     */
    private function scheduleReconnect(): void
    {
        // Check if connection was stable and reset attempts if so
        $this->reconnection->resetIfStable();

        $delay = $this->reconnection->nextDelay();

        Log::info('Scheduling reconnection', [
            'account_id' => $this->account->id,
            'delay' => $delay,
            'attempt' => $this->reconnection->getAttemptCount(),
        ]);

        $this->loop->addTimer($delay, function () {
            $this->start();
        });
    }

    /**
     * Set error state with message.
     */
    private function setError(string $message): void
    {
        Log::error('SlackSocketWorker error', [
            'account_id' => $this->account->id,
            'error' => $message,
        ]);

        $this->status = WorkerHealthStatus::Error;
        $this->errorMessage = $message;
        $this->connectedAt = null;
    }

    /**
     * Create a WebSocket connector.
     *
     * Uses the injected factory for testing or creates a real connector.
     */
    private function createWebSocketConnector(): callable
    {
        if ($this->webSocketConnectorFactory !== null) {
            return ($this->webSocketConnectorFactory)($this->loop);
        }

        // Use Ratchet/Pawl connector
        $connector = new \Ratchet\Client\Connector($this->loop);

        return function (string $url) use ($connector) {
            return $connector($url);
        };
    }
}
