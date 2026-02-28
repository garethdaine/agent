<?php

declare(strict_types=1);

namespace App\Messenger\Gateway\Contracts;

use App\Messenger\Gateway\DTOs\WorkerHealthMetadata;
use App\Messenger\Gateway\Enums\WorkerHealthStatus;

/**
 * Interface for gateway workers that manage real-time connections to messenger providers.
 *
 * Gateway workers are responsible for:
 * - Establishing and maintaining persistent connections (WebSocket, long-polling)
 * - Receiving inbound events/messages from the provider
 * - Dispatching events to the processing pipeline
 * - Managing connection lifecycle (start, stop, reconnect)
 * - Reporting health status for monitoring
 *
 * Implementations:
 * - SlackSocketWorker: WebSocket connection via Socket Mode API
 * - TelegramPollingWorker: Long-polling via getUpdates API
 * - DiscordGatewayWorker: WebSocket connection via Discord Gateway
 */
interface GatewayWorkerInterface
{
    /**
     * Initialize and start the worker connection.
     *
     * This method should:
     * - Establish the initial connection to the provider
     * - Begin receiving events
     * - Set up heartbeat/ping-pong mechanisms as required
     * - Transition state to Connected on success
     *
     * @throws \RuntimeException If connection cannot be established
     */
    public function start(): void;

    /**
     * Gracefully stop the worker.
     *
     * This method should:
     * - Stop accepting new events
     * - Close the connection cleanly
     * - Transition state to Disconnected
     *
     * This method should NOT wait for in-flight messages to complete.
     * Use drain() before stop() for graceful shutdown.
     */
    public function stop(): void;

    /**
     * Get the current health status of the worker.
     *
     * Returns the high-level connection state. For detailed diagnostics,
     * use getHealthMetadata().
     */
    public function health(): WorkerHealthStatus;

    /**
     * Force a reconnection to the provider.
     *
     * This method should:
     * - Close the existing connection if any
     * - Attempt to establish a new connection
     * - Use the ReconnectionStrategy for backoff timing if called repeatedly
     * - Transition state to Reconnecting during the process
     *
     * Use this when credentials are rotated or when the connection
     * is suspected to be unhealthy.
     */
    public function reconnect(): void;

    /**
     * Get detailed health metadata for monitoring and diagnostics.
     *
     * Returns additional context beyond the status enum, including:
     * - Last event timestamp
     * - Connection uptime
     * - Error messages if in error state
     */
    public function getHealthMetadata(): WorkerHealthMetadata;

    /**
     * Drain in-flight messages before stopping.
     *
     * This method should:
     * - Stop accepting new events
     * - Wait for currently processing messages to complete
     * - Respect a timeout (typically from config)
     *
     * Called before stop() during graceful shutdown or credential rotation.
     */
    public function drain(): void;

    /**
     * Get the connector account ID this worker is associated with.
     */
    public function getConnectorAccountId(): string;
}
