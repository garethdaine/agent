<?php

declare(strict_types=1);

namespace App\DTOs\Connectors;

use App\Models\AgentConnectorConnection;

class ConnectResult
{
    public function __construct(
        public readonly bool $connected,
        public readonly ?AgentConnectorConnection $connection = null,
        public readonly ?string $authorizationUrl = null,
    ) {}

    public static function connected(AgentConnectorConnection $connection): self
    {
        return new self(connected: true, connection: $connection);
    }

    public static function pendingOAuth(AgentConnectorConnection $connection, string $authorizationUrl): self
    {
        return new self(connected: false, connection: $connection, authorizationUrl: $authorizationUrl);
    }
}
