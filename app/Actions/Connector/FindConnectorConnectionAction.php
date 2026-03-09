<?php

declare(strict_types=1);

namespace App\Actions\Connector;

use App\Models\AgentConnectorConnection;

class FindConnectorConnectionAction
{
    public function findActiveByTeamAndConnector(int $teamId, string $connectorId): AgentConnectorConnection
    {
        /** @var AgentConnectorConnection */
        return AgentConnectorConnection::where('team_id', $teamId)
            ->where('connector_id', $connectorId)
            ->whereNot('status', AgentConnectorConnection::STATUS_DISCONNECTED)
            ->firstOrFail();
    }

    public function findByTeamAndConnector(int $teamId, string $connectorId): AgentConnectorConnection
    {
        /** @var AgentConnectorConnection */
        return AgentConnectorConnection::where('team_id', $teamId)
            ->where('connector_id', $connectorId)
            ->firstOrFail();
    }

    public function findConnectedForConnector(string $connectorId): ?AgentConnectorConnection
    {
        /** @var AgentConnectorConnection|null */
        return AgentConnectorConnection::where('connector_id', $connectorId)
            ->where('status', AgentConnectorConnection::STATUS_CONNECTED)
            ->first();
    }
}
