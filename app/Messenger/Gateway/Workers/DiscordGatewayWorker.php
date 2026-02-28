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
use React\EventLoop\TimerInterface;

/**
 * Gateway worker for Discord Gateway WebSocket v10.
 *
 * Discord Gateway allows the application to receive real-time events over a WebSocket
 * connection. This is the local mode alternative to webhook-based interactions.
 *
 * Gateway Protocol (v10):
 * 1. GET /gateway/bot to obtain Gateway URL and shard info
 * 2. Connect to wss://gateway.discord.gg/?v=10&encoding=json
 * 3. Receive HELLO (opcode 10) with heartbeat_interval
 * 4. Send IDENTIFY (opcode 2) with token and intents
 * 5. Receive READY (opcode 0, t=READY) with session_id and resume_gateway_url
 * 6. Start heartbeat loop at heartbeat_interval
 * 7. Track sequence numbers (s field) for resume capability
 * 8. On disconnect: attempt RESUME (opcode 6) with session_id and last sequence
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
 *
 * Required Intents:
 * - GUILDS (1 << 0) = 1
 * - GUILD_MESSAGES (1 << 9) = 512
 * - MESSAGE_CONTENT (1 << 15) = 32768 (privileged)
 * - DIRECT_MESSAGES (1 << 12) = 4096
 *
 * @see https://discord.com/developers/docs/topics/gateway
 */
class DiscordGatewayWorker implements GatewayWorkerInterface
{
    private const DISCORD_API_BASE = 'https://discord.com/api/v10';

    private const GATEWAY_VERSION = 10;

    private const GATEWAY_ENCODING = 'json';

    // Discord Gateway opcodes
    private const OP_DISPATCH = 0;

    private const OP_HEARTBEAT = 1;

    private const OP_IDENTIFY = 2;

    private const OP_RESUME = 6;

    private const OP_RECONNECT = 7;

    private const OP_INVALID_SESSION = 9;

    private const OP_HELLO = 10;

    private const OP_HEARTBEAT_ACK = 11;

    // Required intents: GUILDS | GUILD_MESSAGES | MESSAGE_CONTENT | DIRECT_MESSAGES
    private const DEFAULT_INTENTS = 37377; // 1 | 512 | 32768 | 4096

    private ?WebSocket $connection = null;

    private WorkerHealthStatus $status = WorkerHealthStatus::Disconnected;

    private ?CarbonInterface $connectedAt = null;

    private ?CarbonInterface $lastEventAt = null;

    private ?string $errorMessage = null;

    private bool $draining = false;

    // Discord Gateway state
    private ?string $sessionId = null;

    private ?string $resumeGatewayUrl = null;

    private ?int $lastSequence = null;

    private ?TimerInterface $heartbeatTimer = null;

    private bool $heartbeatAckReceived = true;

    private float $heartbeatInterval = 41.25; // Default, will be updated from HELLO

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

        Log::info('DiscordGatewayWorker starting', [
            'account_id' => $this->account->id,
        ]);

        // Step 1: Get Gateway URL from /gateway/bot
        $gatewayInfo = $this->getGatewayInfo();

        if ($gatewayInfo === null) {
            return; // Error already set
        }

        $gatewayUrl = $gatewayInfo['url'];

        // Append version and encoding
        $wsUrl = sprintf('%s/?v=%d&encoding=%s', $gatewayUrl, self::GATEWAY_VERSION, self::GATEWAY_ENCODING);

        // Step 2: Connect to WebSocket
        $this->connectWebSocket($wsUrl);
    }

    /**
     * {@inheritdoc}
     */
    public function stop(): void
    {
        Log::info('DiscordGatewayWorker stopping', [
            'account_id' => $this->account->id,
        ]);

        $this->cancelHeartbeat();

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
        Log::info('DiscordGatewayWorker reconnecting', [
            'account_id' => $this->account->id,
            'has_session' => $this->sessionId !== null,
        ]);

        $this->cancelHeartbeat();

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
     */
    public function drain(): void
    {
        Log::info('DiscordGatewayWorker draining', [
            'account_id' => $this->account->id,
        ]);

        $this->draining = true;
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
     * Get Gateway info from Discord's /gateway/bot endpoint.
     *
     * @return array{url: string, shards: int}|null
     */
    private function getGatewayInfo(): ?array
    {
        $botToken = $this->account->credentials['bot_token'] ?? null;

        if (empty($botToken)) {
            $this->setError('Missing bot_token in credentials');

            return null;
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bot '.$botToken,
            ])->get(self::DISCORD_API_BASE.'/gateway/bot');

            if (! $response->successful()) {
                $message = $response->json()['message'] ?? 'HTTP '.$response->status();
                $this->setError('/gateway/bot failed: '.$message);

                return null;
            }

            $data = $response->json();
            $url = $data['url'] ?? null;

            if (empty($url)) {
                $this->setError('/gateway/bot returned no URL');

                return null;
            }

            Log::debug('Got Gateway info from Discord', [
                'account_id' => $this->account->id,
                'url' => $url,
                'shards' => $data['shards'] ?? 1,
            ]);

            return [
                'url' => $url,
                'shards' => $data['shards'] ?? 1,
            ];
        } catch (\Exception $e) {
            $this->setError('/gateway/bot exception: '.$e->getMessage());

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
                    $this->cancelHeartbeat();

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

        $opcode = $data['op'] ?? null;
        $sequence = $data['s'] ?? null;
        $eventType = $data['t'] ?? null;
        $eventData = $data['d'] ?? null;

        // Update sequence number for resume
        if ($sequence !== null) {
            $this->lastSequence = $sequence;
        }

        Log::debug('Received Gateway message', [
            'account_id' => $this->account->id,
            'opcode' => $opcode,
            'event_type' => $eventType,
        ]);

        match ($opcode) {
            self::OP_HELLO => $this->handleHello($eventData),
            self::OP_HEARTBEAT_ACK => $this->handleHeartbeatAck(),
            self::OP_DISPATCH => $this->handleDispatch($eventType, $eventData, $data),
            self::OP_RECONNECT => $this->handleReconnect(),
            self::OP_INVALID_SESSION => $this->handleInvalidSession($eventData),
            self::OP_HEARTBEAT => $this->sendHeartbeat(), // Server requests immediate heartbeat
            default => Log::debug('Unhandled opcode', ['opcode' => $opcode]),
        };
    }

    /**
     * Handle HELLO (opcode 10) - extract heartbeat interval and send IDENTIFY.
     *
     * @param  array<string, mixed>|null  $data
     */
    private function handleHello(?array $data): void
    {
        $heartbeatIntervalMs = $data['heartbeat_interval'] ?? 41250;
        $this->heartbeatInterval = $heartbeatIntervalMs / 1000; // Convert to seconds

        Log::info('Discord Gateway HELLO received', [
            'account_id' => $this->account->id,
            'heartbeat_interval_ms' => $heartbeatIntervalMs,
        ]);

        // Start heartbeat loop
        $this->startHeartbeat();

        // Decide whether to IDENTIFY or RESUME
        if ($this->sessionId !== null && $this->lastSequence !== null) {
            $this->sendResume();
        } else {
            $this->sendIdentify();
        }
    }

    /**
     * Handle Heartbeat ACK (opcode 11).
     */
    private function handleHeartbeatAck(): void
    {
        $this->heartbeatAckReceived = true;
        $this->lastEventAt = now();

        Log::debug('Heartbeat ACK received', [
            'account_id' => $this->account->id,
        ]);
    }

    /**
     * Handle DISPATCH (opcode 0) - process events.
     *
     * @param  array<string, mixed>|null  $data
     * @param  array<string, mixed>  $fullPayload
     */
    private function handleDispatch(?string $eventType, ?array $data, array $fullPayload): void
    {
        $this->lastEventAt = now();

        match ($eventType) {
            'READY' => $this->handleReady($data),
            'RESUMED' => $this->handleResumed(),
            'MESSAGE_CREATE' => $this->handleMessageCreate($data, $fullPayload),
            'INTERACTION_CREATE' => $this->handleInteractionCreate($data, $fullPayload),
            default => Log::debug('Unhandled dispatch event', ['type' => $eventType]),
        };
    }

    /**
     * Handle READY event - store session info.
     *
     * @param  array<string, mixed>|null  $data
     */
    private function handleReady(?array $data): void
    {
        $this->sessionId = $data['session_id'] ?? null;
        $this->resumeGatewayUrl = $data['resume_gateway_url'] ?? null;

        $user = $data['user'] ?? [];

        Log::info('Discord Gateway READY', [
            'account_id' => $this->account->id,
            'session_id' => $this->sessionId,
            'bot_username' => $user['username'] ?? 'unknown',
        ]);

        $this->status = WorkerHealthStatus::Connected;
        $this->connectedAt = now();
        $this->errorMessage = null;

        // Record successful connection for reconnection strategy
        $this->reconnection->recordConnectionStart();
    }

    /**
     * Handle RESUMED event - session resumed successfully.
     */
    private function handleResumed(): void
    {
        Log::info('Discord Gateway session resumed', [
            'account_id' => $this->account->id,
            'session_id' => $this->sessionId,
        ]);

        $this->status = WorkerHealthStatus::Connected;
        $this->connectedAt = now();
        $this->errorMessage = null;

        $this->reconnection->recordConnectionStart();
    }

    /**
     * Handle MESSAGE_CREATE event.
     *
     * @param  array<string, mixed>|null  $data
     * @param  array<string, mixed>  $fullPayload
     */
    private function handleMessageCreate(?array $data, array $fullPayload): void
    {
        if ($this->draining) {
            return;
        }

        // Skip bot messages
        $author = $data['author'] ?? [];
        if ($author['bot'] ?? false) {
            return;
        }

        Log::debug('Dispatching MESSAGE_CREATE to ProcessInboundMessage', [
            'account_id' => $this->account->id,
            'message_id' => $data['id'] ?? null,
        ]);

        ProcessInboundMessage::dispatch(
            connectorAccountId: (string) $this->account->id,
            provider: $this->account->provider,
            payload: $fullPayload
        );
    }

    /**
     * Handle INTERACTION_CREATE event.
     *
     * @param  array<string, mixed>|null  $data
     * @param  array<string, mixed>  $fullPayload
     */
    private function handleInteractionCreate(?array $data, array $fullPayload): void
    {
        if ($this->draining) {
            return;
        }

        Log::debug('Dispatching INTERACTION_CREATE to ProcessInboundMessage', [
            'account_id' => $this->account->id,
            'interaction_id' => $data['id'] ?? null,
            'type' => $data['type'] ?? null,
        ]);

        ProcessInboundMessage::dispatch(
            connectorAccountId: (string) $this->account->id,
            provider: $this->account->provider,
            payload: $fullPayload
        );
    }

    /**
     * Handle RECONNECT (opcode 7) - server requests reconnection.
     */
    private function handleReconnect(): void
    {
        Log::info('Discord Gateway RECONNECT received', [
            'account_id' => $this->account->id,
        ]);

        $this->status = WorkerHealthStatus::Reconnecting;
        $this->cancelHeartbeat();

        if ($this->connection !== null) {
            $this->connection->close();
            $this->connection = null;
        }

        $this->scheduleReconnect();
    }

    /**
     * Handle INVALID_SESSION (opcode 9).
     *
     * @param  mixed  $resumable  Whether the session can be resumed (boolean)
     */
    private function handleInvalidSession(mixed $resumable): void
    {
        $canResume = $resumable === true;

        Log::info('Discord Gateway INVALID_SESSION', [
            'account_id' => $this->account->id,
            'can_resume' => $canResume,
        ]);

        if (! $canResume) {
            // Clear session data for fresh IDENTIFY
            $this->sessionId = null;
            $this->lastSequence = null;
        }

        $this->status = WorkerHealthStatus::Reconnecting;
        $this->cancelHeartbeat();

        if ($this->connection !== null) {
            $this->connection->close();
            $this->connection = null;
        }

        // Wait 1-5 seconds before reconnecting (per Discord docs)
        $delay = rand(1, 5);
        $this->loop->addTimer($delay, function () {
            $this->start();
        });
    }

    /**
     * Send IDENTIFY (opcode 2).
     */
    private function sendIdentify(): void
    {
        $botToken = $this->account->credentials['bot_token'] ?? '';
        $intents = $this->account->credentials['intents'] ?? self::DEFAULT_INTENTS;

        $payload = [
            'op' => self::OP_IDENTIFY,
            'd' => [
                'token' => $botToken,
                'intents' => $intents,
                'properties' => [
                    'os' => PHP_OS,
                    'browser' => 'Agent',
                    'device' => 'Agent',
                ],
            ],
        ];

        $this->send($payload);

        Log::debug('Sent IDENTIFY', [
            'account_id' => $this->account->id,
            'intents' => $intents,
        ]);
    }

    /**
     * Send RESUME (opcode 6).
     */
    private function sendResume(): void
    {
        $botToken = $this->account->credentials['bot_token'] ?? '';

        $payload = [
            'op' => self::OP_RESUME,
            'd' => [
                'token' => $botToken,
                'session_id' => $this->sessionId,
                'seq' => $this->lastSequence,
            ],
        ];

        $this->send($payload);

        Log::debug('Sent RESUME', [
            'account_id' => $this->account->id,
            'session_id' => $this->sessionId,
            'seq' => $this->lastSequence,
        ]);
    }

    /**
     * Start the heartbeat timer.
     */
    private function startHeartbeat(): void
    {
        $this->cancelHeartbeat();

        $this->heartbeatTimer = $this->loop->addPeriodicTimer(
            $this->heartbeatInterval,
            function () {
                $this->onHeartbeatTick();
            }
        );

        Log::debug('Started heartbeat timer', [
            'account_id' => $this->account->id,
            'interval' => $this->heartbeatInterval,
        ]);
    }

    /**
     * Cancel the heartbeat timer.
     */
    private function cancelHeartbeat(): void
    {
        if ($this->heartbeatTimer !== null) {
            $this->loop->cancelTimer($this->heartbeatTimer);
            $this->heartbeatTimer = null;
        }
    }

    /**
     * Called on each heartbeat interval.
     */
    private function onHeartbeatTick(): void
    {
        // Check if previous heartbeat was acknowledged
        if (! $this->heartbeatAckReceived) {
            Log::warning('Missed heartbeat ACK, reconnecting', [
                'account_id' => $this->account->id,
            ]);

            $this->reconnect();

            return;
        }

        $this->heartbeatAckReceived = false;
        $this->sendHeartbeat();
    }

    /**
     * Send HEARTBEAT (opcode 1).
     */
    private function sendHeartbeat(): void
    {
        $payload = [
            'op' => self::OP_HEARTBEAT,
            'd' => $this->lastSequence,
        ];

        $this->send($payload);

        Log::debug('Sent heartbeat', [
            'account_id' => $this->account->id,
            'seq' => $this->lastSequence,
        ]);
    }

    /**
     * Send a payload to the WebSocket.
     *
     * @param  array<string, mixed>  $payload
     */
    private function send(array $payload): void
    {
        if ($this->connection === null) {
            return;
        }

        $this->connection->send(json_encode($payload));
    }

    /**
     * Schedule a reconnection attempt with exponential backoff.
     */
    private function scheduleReconnect(): void
    {
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
        Log::error('DiscordGatewayWorker error', [
            'account_id' => $this->account->id,
            'error' => $message,
        ]);

        $this->status = WorkerHealthStatus::Error;
        $this->errorMessage = $message;
        $this->connectedAt = null;
    }

    /**
     * Create a WebSocket connector.
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
