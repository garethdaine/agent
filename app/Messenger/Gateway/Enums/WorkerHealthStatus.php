<?php

declare(strict_types=1);

namespace App\Messenger\Gateway\Enums;

/**
 * Represents the health/connection status of a gateway worker.
 *
 * These values are persisted to the connector_accounts.runtime_state column
 * and must match the database enum values exactly.
 */
enum WorkerHealthStatus: string
{
    case Connected = 'connected';
    case Reconnecting = 'reconnecting';
    case Disconnected = 'disconnected';
    case Error = 'error';

    /**
     * Check if the worker is in a healthy/operational state.
     */
    public function isHealthy(): bool
    {
        return $this === self::Connected;
    }

    /**
     * Check if the worker is attempting to connect.
     */
    public function isConnecting(): bool
    {
        return $this === self::Reconnecting;
    }

    /**
     * Check if the worker requires attention (error or disconnected).
     */
    public function requiresAttention(): bool
    {
        return $this === self::Error || $this === self::Disconnected;
    }

    /**
     * Get a human-readable label for the status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Connected => 'Connected',
            self::Reconnecting => 'Reconnecting',
            self::Disconnected => 'Disconnected',
            self::Error => 'Error',
        };
    }

    /**
     * Get all possible values as an array of strings (for database enum definition).
     *
     * @return array<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $status) => $status->value, self::cases());
    }
}
