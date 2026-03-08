<?php

declare(strict_types=1);

namespace App\Support\Connectors\Exceptions;

use RuntimeException;

class CircuitOpenException extends RuntimeException
{
    public function __construct(
        string $connectorId,
        public readonly int $recoversInSeconds,
    ) {
        parent::__construct("Circuit breaker is open for connector '{$connectorId}'. Recovers in {$recoversInSeconds} seconds.");
    }
}
