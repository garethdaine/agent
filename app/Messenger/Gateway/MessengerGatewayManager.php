<?php

declare(strict_types=1);

namespace App\Messenger\Gateway;

use App\Messenger\Gateway\Contracts\GatewayWorkerInterface;
use App\Messenger\Gateway\Workers\DiscordGatewayWorker;
use App\Messenger\Gateway\Workers\SlackSocketWorker;
use App\Messenger\Gateway\Workers\TelegramPollingWorker;
use App\Models\ConnectorAccount;
use App\Services\Messenger\SlashCommandRegistrar;
use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use React\EventLoop\LoopInterface;
use React\EventLoop\TimerInterface;
use RuntimeException;

/**
 * Supervisor service managing all gateway workers within the messenger gateway process.
 *
 * Responsibilities:
 * - Boot and manage workers for all local-mode connectors
 * - Periodic health checks and database state updates
 * - Credential change detection and graceful restarts
 * - Graceful shutdown with drain timeout
 *
 * This service is designed to run within a single long-lived PHP process
 * alongside Horizon, using ReactPHP for async operations.
 */
class MessengerGatewayManager
{
    /**
     * Active workers keyed by connector_account_id.
     *
     * @var array<string, GatewayWorkerInterface>
     */
    private array $workers = [];

    /**
     * Credential hashes for change detection.
     *
     * @var array<string, string>
     */
    private array $credentialHashes = [];

    /**
     * Factory for creating workers (injectable for testing).
     */
    private ?Closure $workerFactory = null;

    /**
     * Whether the manager is in shutdown state.
     */
    private bool $shuttingDown = false;

    /**
     * Periodic timers for cleanup on shutdown.
     *
     * @var array<TimerInterface>
     */
    private array $timers = [];

    public function __construct(
        private readonly LoopInterface $loop,
    ) {}

    /**
     * Boot all workers for local-mode connectors.
     */
    public function bootWorkers(): void
    {
        $this->setupPeriodicTimers();

        $connectors = ConnectorAccount::localMode()->get();

        Log::info('Booting gateway workers', [
            'connector_count' => $connectors->count(),
        ]);

        foreach ($connectors as $connector) {
            try {
                $this->addWorker($connector);
            } catch (\Throwable $e) {
                Log::error('Failed to boot worker', [
                    'connector_id' => $connector->id,
                    'provider' => $connector->provider,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Add a worker for a connector account.
     */
    public function addWorker(ConnectorAccount $account): void
    {
        if ($this->shuttingDown) {
            throw new RuntimeException('Cannot add worker: manager is shutting down');
        }

        if (isset($this->workers[$account->id])) {
            Log::warning('Worker already exists for connector', [
                'connector_id' => $account->id,
            ]);

            return;
        }

        if ($account->provider === ConnectorAccount::PROVIDER_DISCORD) {
            $this->syncDiscordSlashCommandsIfNeeded($account);
        }

        $worker = $this->createWorker($account);
        $this->workers[$account->id] = $worker;
        $this->credentialHashes[$account->id] = $this->hashCredentials($account);

        try {
            $worker->start();

            Log::info('Worker started', [
                'connector_id' => $account->id,
                'provider' => $account->provider,
            ]);
        } catch (\Throwable $e) {
            unset($this->workers[$account->id], $this->credentialHashes[$account->id]);
            throw $e;
        }
    }

    /**
     * Remove a worker by connector account ID.
     */
    public function removeWorker(string $accountId): void
    {
        if (! isset($this->workers[$accountId])) {
            Log::warning('No worker found for connector', [
                'connector_id' => $accountId,
            ]);

            return;
        }

        $worker = $this->workers[$accountId];

        try {
            $worker->stop();
        } catch (\Throwable $e) {
            Log::error('Error stopping worker', [
                'connector_id' => $accountId,
                'error' => $e->getMessage(),
            ]);
        }

        unset($this->workers[$accountId], $this->credentialHashes[$accountId]);

        Log::info('Worker removed', [
            'connector_id' => $accountId,
        ]);
    }

    /**
     * Restart a worker with fresh credentials.
     *
     * Performs: drain → stop → start with fresh model data.
     */
    public function restartWorker(string $accountId): void
    {
        if (! isset($this->workers[$accountId])) {
            Log::warning('No worker found to restart', [
                'connector_id' => $accountId,
            ]);

            return;
        }

        $worker = $this->workers[$accountId];

        Log::info('Restarting worker', [
            'connector_id' => $accountId,
        ]);

        try {
            // Drain in-flight messages
            $worker->drain();

            // Stop the worker
            $worker->stop();

            // Remove from registry
            unset($this->workers[$accountId]);

            // Refresh the account from database and create new worker
            $account = ConnectorAccount::find($accountId);
            if ($account && $account->isLocalMode()) {
                $this->addWorker($account);
            }
        } catch (\Throwable $e) {
            Log::error('Error restarting worker', [
                'connector_id' => $accountId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Graceful shutdown of all workers.
     *
     * - Stop accepting new events
     * - Drain in-flight messages (max timeout from config)
     * - Stop all workers
     * - Stop the event loop
     */
    public function shutdown(): void
    {
        if ($this->shuttingDown) {
            return;
        }

        $this->shuttingDown = true;
        $timeout = config('messenger.gateway.shutdown_timeout', 30);

        Log::info('Gateway shutdown initiated', [
            'worker_count' => count($this->workers),
            'timeout_seconds' => $timeout,
        ]);

        // Cancel periodic timers
        foreach ($this->timers as $timer) {
            $this->loop->cancelTimer($timer);
        }
        $this->timers = [];

        // Drain and stop all workers
        foreach ($this->workers as $accountId => $worker) {
            try {
                $worker->drain();
                $worker->stop();
            } catch (\Throwable $e) {
                Log::error('Error during worker shutdown', [
                    'connector_id' => $accountId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->workers = [];
        $this->credentialHashes = [];

        Log::info('Gateway shutdown complete');

        $this->loop->stop();
    }

    /**
     * Check if a worker exists for the given connector.
     */
    public function hasWorker(string $accountId): bool
    {
        return isset($this->workers[$accountId]);
    }

    /**
     * Get the count of active workers.
     */
    public function getWorkerCount(): int
    {
        return count($this->workers);
    }

    /**
     * Check if the manager is shutting down.
     */
    public function isShutdown(): bool
    {
        return $this->shuttingDown;
    }

    /**
     * Get health summary for all workers.
     *
     * Returns an array of rows suitable for console table output.
     *
     * @return array<int, array{connector: string, provider: string, status: string, last_event: string}>
     */
    public function getHealthSummary(): array
    {
        $summary = [];

        foreach ($this->workers as $accountId => $worker) {
            $account = ConnectorAccount::find($accountId);
            $status = $worker->health();
            $metadata = $worker->getHealthMetadata();

            $lastEvent = $metadata->lastEventAt
                ? $metadata->lastEventAt->diffForHumans()
                : 'Never';

            $summary[] = [
                'connector' => $account?->name ?? "Account #{$accountId}",
                'provider' => $account?->provider ?? 'Unknown',
                'status' => $status->label(),
                'last_event' => $lastEvent,
            ];
        }

        return $summary;
    }

    /**
     * Get the credential hash for a connector.
     */
    public function getCredentialHash(string $accountId): ?string
    {
        // Refresh from database to get latest
        $account = ConnectorAccount::find($accountId);
        if (! $account) {
            return null;
        }

        return $this->hashCredentials($account);
    }

    /**
     * Check for credential changes on all workers and restart if needed.
     */
    public function checkCredentialChanges(): void
    {
        $localConnectors = ConnectorAccount::localMode()->get()->keyBy('id');

        // Add workers for newly-created local-mode connectors.
        foreach ($localConnectors as $accountId => $account) {
            if (isset($this->workers[$accountId])) {
                continue;
            }

            try {
                $this->addWorker($account);
            } catch (\Throwable $e) {
                Log::error('Failed to add worker for new local connector', [
                    'connector_id' => $accountId,
                    'provider' => $account->provider,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        foreach (array_keys($this->workers) as $accountId) {
            /** @var ConnectorAccount|null $account */
            $account = $localConnectors->get($accountId);
            if (! $account) {
                // Account was deleted or switched out of local mode.
                $this->removeWorker($accountId);

                continue;
            }

            $newHash = $this->hashCredentials($account);
            $oldHash = $this->credentialHashes[$accountId] ?? '';

            if ($newHash !== $oldHash) {
                Log::info('Credential change detected, restarting worker', [
                    'connector_id' => $accountId,
                ]);
                $this->restartWorker($accountId);
            }
        }
    }

    /**
     * Perform health check on all workers and update database state.
     */
    public function performHealthCheck(): void
    {
        Cache::put('messenger_gateway_heartbeat', now(), 300);

        foreach ($this->workers as $accountId => $worker) {
            try {
                $status = $worker->health();
                $metadata = $worker->getHealthMetadata();

                $account = ConnectorAccount::find($accountId);
                if ($account) {
                    $account->updateRuntimeState($status, $metadata->errorMessage);
                }
            } catch (\Throwable $e) {
                Log::error('Error during health check', [
                    'connector_id' => $accountId,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Set a custom worker factory (for testing).
     */
    public function setWorkerFactory(Closure $factory): void
    {
        $this->workerFactory = $factory;
    }

    /**
     * Create a worker for the given connector account.
     */
    private function syncDiscordSlashCommandsIfNeeded(ConnectorAccount $account): void
    {
        $registrar = app(SlashCommandRegistrar::class);
        if (! $registrar->needsUpdate($account)) {
            return;
        }

        try {
            $result = $registrar->register($account);
            if ($result->isSuccessful()) {
                Log::info('Discord slash commands synced on worker start', [
                    'connector_id' => $account->id,
                    'command_count' => $result->getCommandCount(),
                ]);
            } else {
                Log::warning('Discord slash command sync failed on worker start', [
                    'connector_id' => $account->id,
                    'message' => $result->getMessage(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('Discord slash command sync exception on worker start', [
                'connector_id' => $account->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function createWorker(ConnectorAccount $account): GatewayWorkerInterface
    {
        if ($this->workerFactory) {
            return ($this->workerFactory)($account);
        }

        // Create appropriate worker based on provider
        return match ($account->provider) {
            ConnectorAccount::PROVIDER_SLACK => new SlackSocketWorker(
                $account,
                $this->loop,
                new ReconnectionStrategy,
            ),
            ConnectorAccount::PROVIDER_TELEGRAM => new TelegramPollingWorker(
                $account,
                $this->loop,
                new ReconnectionStrategy,
            ),
            ConnectorAccount::PROVIDER_DISCORD => new DiscordGatewayWorker(
                $account,
                $this->loop,
                new ReconnectionStrategy,
            ),
            default => throw new RuntimeException(
                "No worker implementation for provider: {$account->provider}"
            ),
        };
    }

    /**
     * Set up periodic timers for health checks and credential polling.
     */
    private function setupPeriodicTimers(): void
    {
        $healthInterval = config('messenger.gateway.health_check_interval', 10);
        $credentialInterval = config('messenger.gateway.credential_poll_interval', 30);

        // Health check timer
        $this->timers[] = $this->loop->addPeriodicTimer($healthInterval, function () {
            $this->performHealthCheck();
        });

        // Credential poll timer
        $this->timers[] = $this->loop->addPeriodicTimer($credentialInterval, function () {
            $this->checkCredentialChanges();
        });
    }

    /**
     * Generate a hash of connector credentials for change detection.
     */
    private function hashCredentials(ConnectorAccount $account): string
    {
        $credentials = $account->credentials ?? [];

        return hash('sha256', json_encode($credentials));
    }
}
