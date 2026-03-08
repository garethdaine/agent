<?php

declare(strict_types=1);

namespace App\Support\Connectors;

use App\Models\AgentConnectorConnection;
use App\Models\AgentConnectorCredential;
use App\Models\AgentConnectorInvocation;

class ConnectorHealthScorer
{
    public function calculateQuickScore(AgentConnectorConnection $connection, string $latestOutcome): float
    {
        $weights = config('connectors.health.weights');
        $currentScore = (float) $connection->health_score;

        $credentialHealth = $this->getCredentialHealthComponent($connection);

        $errorImpact = match ($latestOutcome) {
            AgentConnectorInvocation::OUTCOME_SUCCESS => 1.0,
            AgentConnectorInvocation::OUTCOME_RATE_LIMITED => 0.7,
            AgentConnectorInvocation::OUTCOME_TIMEOUT => 0.5,
            AgentConnectorInvocation::OUTCOME_AUTH_FAILED => 0.3,
            default => 0.0,
        };

        $errorRate = ($currentScore * 0.7) + ($errorImpact * 0.3);

        $latencyScore = 1.0;
        $rateLimitHeadroom = $latestOutcome === AgentConnectorInvocation::OUTCOME_RATE_LIMITED ? 0.2 : 1.0;

        $score = ($credentialHealth * $weights['credential_health'])
            + ($errorRate * $weights['error_rate'])
            + ($latencyScore * $weights['latency'])
            + ($rateLimitHeadroom * $weights['rate_limit_headroom']);

        return round(max(0.0, min(1.0, $score)), 2);
    }

    public function calculateFullScore(AgentConnectorConnection $connection): float
    {
        $weights = config('connectors.health.weights');

        $credentialHealth = $this->getCredentialHealthComponent($connection);

        $invocations = AgentConnectorInvocation::where('connection_id', $connection->id)
            ->where('created_at', '>=', now()->subHour())
            ->get();

        $errorRate = 1.0;
        if ($invocations->isNotEmpty()) {
            $failedCount = $invocations->whereNotIn('outcome', [AgentConnectorInvocation::OUTCOME_SUCCESS])->count();
            $errorRate = 1.0 - ($failedCount / $invocations->count());
        }

        $latencyScore = 1.0;
        if ($invocations->isNotEmpty()) {
            $durations = $invocations->pluck('duration_ms')->sort()->values();
            $p95Index = (int) ceil($durations->count() * 0.95) - 1;
            $p95 = $durations[$p95Index] ?? 0;
            $timeout = config('connectors.default_action_timeout_seconds', 30) * 1000;
            $latencyScore = max(0.0, 1.0 - ($p95 / $timeout));
        }

        $rateLimits = $connection->connector->rate_limits ?? [];
        $dailyLimit = $rateLimits['daily_limit'] ?? null;
        $rateLimitHeadroom = 1.0;
        if ($dailyLimit && $connection->action_count_24h > 0) {
            $rateLimitHeadroom = max(0.0, 1.0 - ($connection->action_count_24h / $dailyLimit));
        }

        $score = ($credentialHealth * $weights['credential_health'])
            + ($errorRate * $weights['error_rate'])
            + ($latencyScore * $weights['latency'])
            + ($rateLimitHeadroom * $weights['rate_limit_headroom']);

        return round(max(0.0, min(1.0, $score)), 2);
    }

    public function healthStatus(float $score): string
    {
        $thresholds = config('connectors.health.thresholds');

        if ($score >= $thresholds['healthy']) {
            return 'healthy';
        }

        if ($score >= $thresholds['degraded']) {
            return 'degraded';
        }

        return 'unhealthy';
    }

    private function getCredentialHealthComponent(AgentConnectorConnection $connection): float
    {
        $credential = $connection->relationLoaded('credential')
            ? $connection->credential
            : AgentConnectorCredential::where('team_id', $connection->team_id)
                ->where('connector_id', $connection->connector_id)
                ->where('status', '!=', AgentConnectorCredential::STATUS_REVOKED)
                ->first();

        if (! $credential) {
            return 0.0;
        }

        if ($credential->status === 'revoked' || $credential->status === 'expired') {
            return 0.0;
        }

        if ($credential->status === 'degraded') {
            return 0.5;
        }

        if ($credential->token_expires_at && $credential->token_expires_at->isPast()) {
            return 0.0;
        }

        return 1.0;
    }
}
