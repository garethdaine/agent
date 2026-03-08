<?php

declare(strict_types=1);

namespace App\Support\Connectors\Mcp;

use App\Models\AgentConnectorConnection;
use App\Models\Team;

class ConnectorMcpRegistrar
{
    /**
     * Get MCP tool definitions for all connected connectors belonging to a team.
     *
     * Each tool definition includes:
     * - name: {mcp_tool_prefix}.{action_name}
     * - description: Generated from connector and action metadata
     * - input_schema: Request schema for the action
     * - stability: From action manifest or connector risk_level default
     * - scope: {tenant, environment, role}
     *
     * @return array<int, array{name: string, description: string, input_schema: array, stability: string, scope: array}>
     */
    public function getRegisteredTools(Team $team): array
    {
        $connections = AgentConnectorConnection::query()
            ->where('team_id', $team->id)
            ->where('status', AgentConnectorConnection::STATUS_CONNECTED)
            ->with('connector')
            ->get();

        $tools = [];

        foreach ($connections as $connection) {
            $connector = $connection->connector;

            if ($connector === null) {
                continue;
            }

            $prefix = $connector->mcp_tool_prefix;
            $actions = $connector->actions ?? [];

            foreach ($actions as $action) {
                $actionName = $action['name'] ?? null;
                if ($actionName === null) {
                    continue;
                }

                $stability = $action['stability'] ?? $this->defaultStability($connector->risk_level);

                $tools[] = [
                    'name' => "{$prefix}.{$actionName}",
                    'description' => $this->buildDescription($connector->display_name, $actionName, $action),
                    'input_schema' => $this->buildInputSchema($action),
                    'stability' => $stability,
                    'scope' => [
                        'tenant' => $team->id,
                        'environment' => 'production',
                        'role' => 'connector',
                    ],
                ];
            }
        }

        return $tools;
    }

    private function buildDescription(string $displayName, string $actionName, array $action): string
    {
        $method = strtoupper($action['method'] ?? 'GET');
        $path = $action['path'] ?? '';

        return "{$displayName}: {$actionName} ({$method} {$path})";
    }

    private function buildInputSchema(array $action): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'parameters' => [
                    'type' => 'object',
                    'description' => 'Action-specific parameters',
                ],
            ],
        ];
    }

    private function defaultStability(string $riskLevel): string
    {
        return match ($riskLevel) {
            'elevated', 'high' => 'beta',
            default => 'stable',
        };
    }
}
