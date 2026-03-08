<?php

declare(strict_types=1);

namespace App\Support\Connectors\Messenger;

use App\DTOs\Messenger\CommandResult;
use App\Models\AgentConnector;
use App\Models\AgentConnectorConnection;
use App\Models\User;
use App\Services\Connectors\ConnectionLifecycleService;

class ConnectorCommandHandler
{
    public function __construct(
        private readonly ConnectionLifecycleService $lifecycleService,
    ) {}

    public function handleCommand(string $command, array $args, User $user): CommandResult
    {
        return match ($command) {
            'connect' => $this->handleConnect($args, $user),
            'list' => $this->handleList($args, $user),
            'test' => $this->handleTest($args, $user),
            'disconnect' => $this->handleDisconnect($args, $user),
            default => CommandResult::failure("Unknown connector command: {$command}"),
        };
    }

    private function handleConnect(array $args, User $user): CommandResult
    {
        $serviceName = $args[0] ?? null;

        if (! $serviceName) {
            return CommandResult::failure('Usage: /connector connect {service}');
        }

        $connector = AgentConnector::where('name', $serviceName)->first();

        if (! $connector) {
            return CommandResult::failure("Connector '{$serviceName}' not found. Use `/connector list connections` to see available services.");
        }

        if ($connector->auth_type === 'oauth2_authorization_code') {
            return CommandResult::success(
                "**{$connector->display_name}** uses OAuth authentication.\n\nTo connect, visit the dashboard and initiate the OAuth flow, or use the API endpoint:\n`POST /agent/api/v1/connectors/{$connector->id}/connect`",
                ['connector_id' => $connector->id, 'auth_type' => 'OAuth']
            );
        }

        return CommandResult::success(
            "**{$connector->display_name}** uses API key authentication.\n\nTo connect, provide your API key via the dashboard or use the API endpoint:\n`POST /agent/api/v1/connectors/{$connector->id}/connect`",
            ['connector_id' => $connector->id, 'auth_type' => $connector->auth_type]
        );
    }

    private function handleList(array $args, User $user): CommandResult
    {
        $team = $user->currentTeam;

        if (! $team) {
            return CommandResult::failure('No team selected.');
        }

        $connections = AgentConnectorConnection::where('team_id', $team->id)
            ->whereIn('status', [
                AgentConnectorConnection::STATUS_CONNECTED,
                AgentConnectorConnection::STATUS_DEGRADED,
                AgentConnectorConnection::STATUS_PENDING,
            ])
            ->with('connector')
            ->get();

        if ($connections->isEmpty()) {
            return CommandResult::success('No services connected. Use `/connector connect {service}` to get started.');
        }

        $lines = ["**Connected Services** ({$connections->count()})\n"];

        foreach ($connections as $connection) {
            $name = $connection->connector->display_name ?? $connection->connector->name;
            $status = $connection->status;
            $health = $connection->health_score;

            $statusIcon = match ($status) {
                AgentConnectorConnection::STATUS_CONNECTED => 'connected',
                AgentConnectorConnection::STATUS_DEGRADED => 'degraded',
                AgentConnectorConnection::STATUS_PENDING => 'pending',
                default => $status,
            };

            $lines[] = "- **{$name}** — {$statusIcon} (health: {$health})";
        }

        return CommandResult::success(
            implode("\n", $lines),
            ['connection_count' => $connections->count()]
        );
    }

    private function handleTest(array $args, User $user): CommandResult
    {
        $serviceName = $args[0] ?? null;

        if (! $serviceName) {
            return CommandResult::failure('Usage: /connector test {service}');
        }

        $team = $user->currentTeam;

        if (! $team) {
            return CommandResult::failure('No team selected.');
        }

        $connector = AgentConnector::where('name', $serviceName)->first();

        if (! $connector) {
            return CommandResult::failure("Connector '{$serviceName}' not found.");
        }

        $connection = AgentConnectorConnection::where('team_id', $team->id)
            ->where('connector_id', $connector->id)
            ->where('status', AgentConnectorConnection::STATUS_CONNECTED)
            ->first();

        if (! $connection) {
            return CommandResult::failure("No active connection to '{$connector->display_name}' not found for your team.");
        }

        $result = $this->lifecycleService->testConnection($connection);

        if ($result->connected) {
            return CommandResult::success(
                "**{$connector->display_name}** is healthy (latency: {$result->latencyMs}ms)",
                ['latency_ms' => $result->latencyMs, 'connected' => true]
            );
        }

        return CommandResult::failure(
            "**{$connector->display_name}** health check failed: {$result->error}"
        );
    }

    private function handleDisconnect(array $args, User $user): CommandResult
    {
        $serviceName = $args[0] ?? null;

        if (! $serviceName) {
            return CommandResult::failure('Usage: /connector disconnect {service}');
        }

        $team = $user->currentTeam;

        if (! $team) {
            return CommandResult::failure('No team selected.');
        }

        $connector = AgentConnector::where('name', $serviceName)->first();

        if (! $connector) {
            return CommandResult::failure("Connector '{$serviceName}' not found.");
        }

        $connection = AgentConnectorConnection::where('team_id', $team->id)
            ->where('connector_id', $connector->id)
            ->where('status', AgentConnectorConnection::STATUS_CONNECTED)
            ->first();

        if (! $connection) {
            return CommandResult::failure("No active connection to '{$connector->display_name}' found for your team.");
        }

        // If no --confirm flag, return confirmation prompt
        $confirmed = in_array('--confirm', $args, true);

        if (! $confirmed) {
            return CommandResult::success(
                "Are you sure you want to disconnect **{$connector->display_name}**? This will revoke all credentials.\n\nTo confirm, run: `/connector disconnect {$serviceName} --confirm`",
                ['connector_id' => $connector->id, 'awaiting_confirmation' => true]
            );
        }

        $this->lifecycleService->disconnect($connection, $user);

        return CommandResult::success(
            "**{$connector->display_name}** has been disconnected and credentials revoked.",
            ['connector_id' => $connector->id, 'disconnected' => true]
        );
    }
}
