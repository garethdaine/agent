<?php

declare(strict_types=1);

namespace App\Support\Connectors\Exceptions;

use RuntimeException;

class ConnectorUnhealthyException extends RuntimeException
{
    public function __construct(string $connectorName, float $healthScore)
    {
        parent::__construct("Connector '{$connectorName}' is unhealthy (score: {$healthScore}).");
    }
}
