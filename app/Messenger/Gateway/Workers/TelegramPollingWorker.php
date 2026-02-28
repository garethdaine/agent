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
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use React\EventLoop\LoopInterface;

/**
 * Gateway worker for Telegram Bot API long-polling via getUpdates.
 *
 * Long-polling allows the application to receive updates from Telegram without
 * requiring a public webhook URL. This is useful for local development or
 * environments without public internet access.
 *
 * Connection Flow:
 * 1. Call deleteWebhook to clear any existing webhook (required before getUpdates)
 * 2. Call getUpdates with offset, timeout, and allowed_updates parameters
 * 3. Process returned updates array
 * 4. Update offset to max(update_id) + 1 after processing
 * 5. Continue polling loop until stopped
 *
 * Token Requirements:
 * - Bot token: {numeric_bot_id}:{alphanumeric_secret}
 *
 * @see https://core.telegram.org/bots/api#getupdates
 */
class TelegramPollingWorker implements GatewayWorkerInterface
{
    private const TELEGRAM_API_BASE = 'https://api.telegram.org';

    /**
     * Long-poll timeout in seconds.
     * Telegram recommends 30 seconds for long-polling.
     */
    private const POLL_TIMEOUT = 30;

    /**
     * Update types to receive via getUpdates.
     *
     * @var array<string>
     */
    private const ALLOWED_UPDATES = ['message', 'callback_query'];

    private int $offset = 0;

    private bool $running = false;

    private bool $draining = false;

    private WorkerHealthStatus $status = WorkerHealthStatus::Disconnected;

    private ?CarbonInterface $connectedAt = null;

    private ?CarbonInterface $lastEventAt = null;

    private ?string $errorMessage = null;

    public function __construct(
        private readonly ConnectorAccount $account,
        private readonly LoopInterface $loop,
        private readonly ReconnectionStrategy $reconnection,
    ) {}

    /**
     * {@inheritdoc}
     */
    public function start(): void
    {
        $this->draining = false;
        $this->running = true;
        $this->status = WorkerHealthStatus::Reconnecting;
        $this->errorMessage = null;

        Log::info('TelegramPollingWorker starting', [
            'account_id' => $this->account->id,
        ]);

        // Validate bot token exists
        $botToken = $this->account->credentials['bot_token'] ?? null;
        if (empty($botToken)) {
            $this->setError('Missing bot_token in credentials');

            return;
        }

        // Step 1: Delete any existing webhook
        if (! $this->deleteWebhook()) {
            return; // Error already set
        }

        // Step 2: Begin polling
        $this->status = WorkerHealthStatus::Connected;
        $this->connectedAt = now();
        $this->lastEventAt = now();
        $this->reconnection->recordConnectionStart();

        $this->poll();
    }

    /**
     * {@inheritdoc}
     */
    public function stop(): void
    {
        Log::info('TelegramPollingWorker stopping', [
            'account_id' => $this->account->id,
        ]);

        $this->running = false;
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
        Log::info('TelegramPollingWorker reconnecting', [
            'account_id' => $this->account->id,
        ]);

        $this->running = false;
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
        Log::info('TelegramPollingWorker draining', [
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
     * Get the current update offset.
     */
    public function getOffset(): int
    {
        return $this->offset;
    }

    /**
     * Check if the worker is currently running.
     */
    public function isRunning(): bool
    {
        return $this->running;
    }

    /**
     * Check if the worker is draining.
     */
    public function isDraining(): bool
    {
        return $this->draining;
    }

    /**
     * Delete any existing webhook before starting long-polling.
     *
     * This is required because Telegram only allows either webhook or getUpdates,
     * not both simultaneously.
     */
    private function deleteWebhook(): bool
    {
        $url = $this->apiUrl('deleteWebhook');

        try {
            $response = Http::timeout(10)->post($url, [
                'drop_pending_updates' => false,
            ]);

            if (! $response->successful()) {
                $this->setError('deleteWebhook failed: HTTP '.$response->status());

                return false;
            }

            $data = $response->json();

            if (! ($data['ok'] ?? false)) {
                $error = $data['description'] ?? 'Unknown error';
                $this->setError('deleteWebhook failed: '.$error);

                return false;
            }

            Log::debug('Telegram webhook deleted', [
                'account_id' => $this->account->id,
            ]);

            return true;

        } catch (\Exception $e) {
            $this->setError('deleteWebhook exception: '.$e->getMessage());

            return false;
        }
    }

    /**
     * Poll for updates via getUpdates API.
     */
    private function poll(): void
    {
        if (! $this->running || $this->draining) {
            return;
        }

        $url = $this->apiUrl('getUpdates');
        $params = [
            'offset' => $this->offset,
            'timeout' => self::POLL_TIMEOUT,
            'allowed_updates' => self::ALLOWED_UPDATES,
        ];

        try {
            // Use a longer timeout for long-polling (poll timeout + buffer)
            $response = Http::timeout(self::POLL_TIMEOUT + 10)->post($url, $params);

            if (! $response->successful()) {
                $this->setError('getUpdates failed: HTTP '.$response->status());
                $this->scheduleReconnect();

                return;
            }

            $data = $response->json();

            if (! ($data['ok'] ?? false)) {
                $error = $data['description'] ?? 'Unknown error';
                $this->setError('getUpdates failed: '.$error);
                $this->scheduleReconnect();

                return;
            }

            $updates = $data['result'] ?? [];
            $this->processUpdates($updates);

            // Schedule next poll
            if ($this->running && ! $this->draining) {
                $this->loop->futureTick(function () {
                    $this->poll();
                });
            }

        } catch (\Exception $e) {
            $this->setError('getUpdates exception: '.$e->getMessage());
            $this->scheduleReconnect();
        }
    }

    /**
     * Process updates received from getUpdates.
     *
     * @param  array<int, array<string, mixed>>  $updates
     */
    private function processUpdates(array $updates): void
    {
        foreach ($updates as $update) {
            $updateId = $update['update_id'] ?? 0;

            // Dispatch to ProcessInboundMessage job
            $this->dispatchUpdate($update);

            // Update offset to max(update_id) + 1
            $this->offset = max($this->offset, $updateId + 1);
        }

        if (! empty($updates)) {
            $this->lastEventAt = now();

            Log::debug('Processed Telegram updates', [
                'account_id' => $this->account->id,
                'count' => count($updates),
                'new_offset' => $this->offset,
            ]);
        }
    }

    /**
     * Dispatch update to ProcessInboundMessage job.
     *
     * @param  array<string, mixed>  $update
     */
    private function dispatchUpdate(array $update): void
    {
        Log::debug('Dispatching Telegram update to ProcessInboundMessage', [
            'account_id' => $this->account->id,
            'update_id' => $update['update_id'] ?? null,
        ]);

        ProcessInboundMessage::dispatch(
            connectorAccountId: (string) $this->account->id,
            provider: $this->account->provider,
            payload: $update
        );
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
        Log::error('TelegramPollingWorker error', [
            'account_id' => $this->account->id,
            'error' => $message,
        ]);

        $this->status = WorkerHealthStatus::Error;
        $this->errorMessage = $message;
        $this->connectedAt = null;
        $this->running = false;
    }

    /**
     * Build Telegram API URL for a given method.
     */
    private function apiUrl(string $method): string
    {
        $token = $this->account->credentials['bot_token'] ?? '';

        return sprintf('%s/bot%s/%s', self::TELEGRAM_API_BASE, $token, $method);
    }
}
