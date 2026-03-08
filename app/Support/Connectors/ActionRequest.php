<?php

declare(strict_types=1);

namespace App\Support\Connectors;

use App\Models\AgentConnector;
use App\Models\AgentConnectorConnection;
use App\Models\Team;

class ActionRequest
{
    public function __construct(
        public readonly AgentConnector $connector,
        public readonly string $action,
        public readonly array $parameters,
        public readonly AgentConnectorConnection $connection,
        public readonly Team $team,
        public readonly ?string $runAttemptId = null,
        public readonly ?string $delegateeId = null,
        public readonly ?string $workflowKey = null,
        public readonly ?float $trustScore = null,
    ) {}
}
