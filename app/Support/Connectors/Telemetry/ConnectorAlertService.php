<?php

declare(strict_types=1);

namespace App\Support\Connectors\Telemetry;

use App\DTOs\Messenger\SystemNotificationPayload;
use App\Models\AgentConnectorConnection;
use App\Services\Messenger\SystemNotificationDispatcher;

class ConnectorAlertService
{
    public function __construct(
        private readonly SystemNotificationDispatcher $dispatcher,
    ) {}

    /**
     * Check if a connection's credential is nearing expiry and alert if so.
     */
    public function checkCredentialExpiry(AgentConnectorConnection $connection, int $daysThreshold = 7): void
    {
        $credential = $connection->relationLoaded('credential')
            ? $connection->credential
            : $connection->credential()->first();

        if ($credential === null || $credential->token_expires_at === null) {
            return;
        }

        $daysUntilExpiry = now()->diffInDays($credential->token_expires_at, false);

        if ($daysUntilExpiry > $daysThreshold || $daysUntilExpiry < 0) {
            return;
        }

        $connectorName = $this->getConnectorDisplayName($connection);

        $this->dispatcher->dispatch(new SystemNotificationPayload(
            type: 'connector.credential_expiring',
            userId: (int) ($connection->connected_by ?? 0),
            severity: SystemNotificationPayload::SEVERITY_WARNING,
            title: "{$connectorName} credentials expiring soon",
            body: "The credentials for **{$connectorName}** will expire in {$daysUntilExpiry} days. Please refresh or reconnect to avoid service disruption.",
            context: [
                'connection_id' => $connection->id,
                'connector_id' => $connection->connector_id,
                'expires_at' => $credential->token_expires_at->toIso8601String(),
                'days_remaining' => $daysUntilExpiry,
            ],
        ));
    }

    /**
     * Check rate limit utilization and alert at 80% (warning) or 100% (critical).
     */
    public function checkRateLimitUtilization(AgentConnectorConnection $connection): void
    {
        $connector = $connection->relationLoaded('connector')
            ? $connection->connector
            : $connection->connector()->first();

        if ($connector === null) {
            return;
        }

        $rateLimits = $connector->rate_limits ?? [];
        $dailyLimit = $rateLimits['daily_limit'] ?? null;

        if ($dailyLimit === null || $dailyLimit <= 0) {
            return;
        }

        $used = (int) $connection->action_count_24h;
        $utilization = $used / (int) $dailyLimit;
        $connectorName = $connector->display_name ?? $connector->name;

        if ($utilization >= 1.0) {
            $this->dispatcher->dispatch(new SystemNotificationPayload(
                type: 'connector.rate_limit_exhausted',
                userId: (int) ($connection->connected_by ?? 0),
                severity: SystemNotificationPayload::SEVERITY_ERROR,
                title: "{$connectorName} rate limit exhausted",
                body: "The daily API quota for **{$connectorName}** has been fully consumed ({$used}/{$dailyLimit} requests). Further requests will be rate-limited until the quota resets.",
                context: [
                    'connection_id' => $connection->id,
                    'connector_id' => $connection->connector_id,
                    'daily_limit' => $dailyLimit,
                    'used' => $used,
                    'utilization_percent' => round($utilization * 100, 1),
                ],
            ));

            return;
        }

        if ($utilization >= 0.8) {
            $this->dispatcher->dispatch(new SystemNotificationPayload(
                type: 'connector.rate_limit_warning',
                userId: (int) ($connection->connected_by ?? 0),
                severity: SystemNotificationPayload::SEVERITY_WARNING,
                title: "{$connectorName} approaching rate limit",
                body: "The daily API quota for **{$connectorName}** is at " . round($utilization * 100, 1) . "% ({$used}/{$dailyLimit} requests).",
                context: [
                    'connection_id' => $connection->id,
                    'connector_id' => $connection->connector_id,
                    'daily_limit' => $dailyLimit,
                    'used' => $used,
                    'utilization_percent' => round($utilization * 100, 1),
                ],
            ));
        }
    }

    /**
     * Check connection health score and alert if degraded.
     */
    public function checkHealthScore(AgentConnectorConnection $connection): void
    {
        $healthScore = (float) $connection->health_score;
        $degradedThreshold = (float) config('connectors.health.thresholds.degraded', 0.5);

        if ($healthScore >= $degradedThreshold) {
            return;
        }

        $connectorName = $this->getConnectorDisplayName($connection);

        $this->dispatcher->dispatch(new SystemNotificationPayload(
            type: 'connector.health_degraded',
            userId: (int) ($connection->connected_by ?? 0),
            severity: SystemNotificationPayload::SEVERITY_WARNING,
            title: "{$connectorName} health degraded",
            body: "The health score for **{$connectorName}** has dropped to " . round($healthScore * 100, 1) . "%. Investigate recent failures and consider reconnecting if issues persist.",
            context: [
                'connection_id' => $connection->id,
                'connector_id' => $connection->connector_id,
                'health_score' => $healthScore,
            ],
        ));
    }

    private function getConnectorDisplayName(AgentConnectorConnection $connection): string
    {
        $connector = $connection->relationLoaded('connector')
            ? $connection->connector
            : $connection->connector()->first();

        return $connector?->display_name ?? $connector?->name ?? 'Unknown Connector';
    }
}
