<?php

declare(strict_types=1);

namespace App\Support\Connectors\Telemetry;

use App\Models\AgentConnector;
use App\Models\AgentConnectorConnection;
use App\Models\AgentConnectorInvocation;

class ConnectorCostAttributor
{
    /**
     * Attribute the cost of a connector invocation based on the connector's cost model.
     *
     * For 'metered' connectors: calculates per-call cost from declared pricing
     * (cost_per_call_usd or cost_per_token_usd × token_usage).
     * For 'free' and 'quota-limited' connectors: returns 0.0.
     */
    public function attributeCost(AgentConnectorInvocation $invocation): float
    {
        $connector = $this->resolveConnector($invocation);

        if ($connector === null) {
            return 0.0;
        }

        $costModel = $connector->cost_model ?? 'free';

        if ($costModel !== 'metered') {
            return 0.0;
        }

        $rateLimits = $connector->rate_limits ?? [];

        // Token-based pricing
        if (isset($rateLimits['cost_per_token_usd']) && $invocation->token_usage > 0) {
            return round((float) $rateLimits['cost_per_token_usd'] * (int) $invocation->token_usage, 6);
        }

        // Per-call pricing
        if (isset($rateLimits['cost_per_call_usd'])) {
            return round((float) $rateLimits['cost_per_call_usd'], 6);
        }

        return 0.0;
    }

    /**
     * Get the remaining quota for a quota-limited connector invocation.
     *
     * Returns null for non-quota-limited connectors or when no daily_limit is configured.
     */
    public function getRemainingQuota(AgentConnectorInvocation $invocation): ?int
    {
        $connector = $this->resolveConnector($invocation);

        if ($connector === null || ($connector->cost_model ?? 'free') !== 'quota-limited') {
            return null;
        }

        $rateLimits = $connector->rate_limits ?? [];
        $dailyLimit = $rateLimits['daily_limit'] ?? null;

        if ($dailyLimit === null) {
            return null;
        }

        $connection = $this->resolveConnection($invocation);
        $usedCount = $connection?->action_count_24h ?? 0;

        return max(0, (int) $dailyLimit - (int) $usedCount);
    }

    private function resolveConnector(AgentConnectorInvocation $invocation): ?AgentConnector
    {
        if ($invocation->relationLoaded('connector')) {
            return $invocation->connector;
        }

        return AgentConnector::find($invocation->connector_id);
    }

    private function resolveConnection(AgentConnectorInvocation $invocation): ?AgentConnectorConnection
    {
        if ($invocation->relationLoaded('connection')) {
            return $invocation->connection;
        }

        return AgentConnectorConnection::find($invocation->connection_id);
    }
}
