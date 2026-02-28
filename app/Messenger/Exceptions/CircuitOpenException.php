<?php

namespace App\Messenger\Exceptions;

use Exception;

/**
 * Thrown when a circuit breaker is open and rejecting requests.
 *
 * This exception indicates that the circuit breaker for a specific connector
 * has tripped due to consecutive failures and is currently in the open state,
 * rejecting all requests until the cooldown period expires.
 */
class CircuitOpenException extends Exception
{
    public function __construct(
        private readonly int|string $connectorId,
        string $message = '',
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        $message = $message ?: "Circuit breaker open for connector {$connectorId}";

        parent::__construct($message, $code, $previous);
    }

    /**
     * Get the connector ID for which the circuit is open.
     */
    public function getConnectorId(): int|string
    {
        return $this->connectorId;
    }
}
