<?php

declare(strict_types=1);

namespace App\Support\Connectors\Exceptions;

use RuntimeException;

class ConnectorNotConnectedException extends RuntimeException
{
    public function __construct(string $connectorName, string $message = '')
    {
        parent::__construct($message ?: "Connector '{$connectorName}' is not connected.");
    }
}
