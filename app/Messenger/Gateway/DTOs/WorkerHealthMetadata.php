<?php

declare(strict_types=1);

namespace App\Messenger\Gateway\DTOs;

use Carbon\CarbonInterface;

/**
 * Data transfer object containing additional health metadata for a gateway worker.
 *
 * This supplements the WorkerHealthStatus enum with detailed diagnostic information
 * useful for monitoring, logging, and debugging worker issues.
 */
readonly class WorkerHealthMetadata
{
    public function __construct(
        /**
         * Timestamp of the last event received by this worker.
         * Null if no events have been received yet.
         */
        public ?CarbonInterface $lastEventAt,

        /**
         * Number of seconds the worker has been continuously connected.
         * Zero if not currently connected.
         */
        public int $connectionUptime,

        /**
         * Error message if the worker is in an error state.
         * Null if no error.
         */
        public ?string $errorMessage = null,
    ) {}

    /**
     * Create a metadata instance for a healthy connected worker.
     */
    public static function connected(CarbonInterface $lastEventAt, int $uptimeSeconds): self
    {
        return new self(
            lastEventAt: $lastEventAt,
            connectionUptime: $uptimeSeconds,
            errorMessage: null,
        );
    }

    /**
     * Create a metadata instance for a worker in error state.
     */
    public static function error(string $errorMessage, ?CarbonInterface $lastEventAt = null): self
    {
        return new self(
            lastEventAt: $lastEventAt,
            connectionUptime: 0,
            errorMessage: $errorMessage,
        );
    }

    /**
     * Create a metadata instance for a disconnected worker.
     */
    public static function disconnected(?CarbonInterface $lastEventAt = null): self
    {
        return new self(
            lastEventAt: $lastEventAt,
            connectionUptime: 0,
            errorMessage: null,
        );
    }

    /**
     * Check if the worker has any recent activity.
     */
    public function hasRecentActivity(int $thresholdSeconds = 300): bool
    {
        if ($this->lastEventAt === null) {
            return false;
        }

        return $this->lastEventAt->diffInSeconds(now()) <= $thresholdSeconds;
    }

    /**
     * Convert to array for logging or serialization.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'last_event_at' => $this->lastEventAt?->toIso8601String(),
            'connection_uptime' => $this->connectionUptime,
            'error_message' => $this->errorMessage,
        ];
    }
}
