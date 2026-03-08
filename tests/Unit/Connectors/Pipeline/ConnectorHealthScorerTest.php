<?php

declare(strict_types=1);

namespace Tests\Unit\Connectors\Pipeline;

use App\Models\AgentConnectorConnection;
use App\Models\AgentConnectorCredential;
use App\Models\AgentConnectorInvocation;
use App\Support\Connectors\ConnectorHealthScorer;
use Tests\TestCase;

class ConnectorHealthScorerTest extends TestCase
{
    private ConnectorHealthScorer $scorer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->scorer = new ConnectorHealthScorer;

        config()->set('connectors.health', [
            'weights' => [
                'credential_health' => 0.40,
                'error_rate' => 0.30,
                'latency' => 0.15,
                'rate_limit_headroom' => 0.15,
            ],
            'thresholds' => [
                'healthy' => 0.8,
                'degraded' => 0.5,
            ],
        ]);
    }

    public function test_calculates_perfect_health(): void
    {
        $credential = new AgentConnectorCredential;
        $credential->status = AgentConnectorCredential::STATUS_ACTIVE;
        $credential->token_expires_at = now()->addHour();

        $connection = new AgentConnectorConnection;
        $connection->health_score = 1.0;

        // Mock credential relationship
        $connection->setRelation('credential', $credential);

        $score = $this->scorer->calculateQuickScore($connection, AgentConnectorInvocation::OUTCOME_SUCCESS);

        $this->assertEquals(1.0, $score);
    }

    public function test_expired_credential_reduces_score(): void
    {
        $credential = new AgentConnectorCredential;
        $credential->status = AgentConnectorCredential::STATUS_EXPIRED;
        $credential->token_expires_at = now()->subHour();

        $connection = new AgentConnectorConnection;
        $connection->health_score = 1.0;
        $connection->setRelation('credential', $credential);

        $score = $this->scorer->calculateQuickScore($connection, AgentConnectorInvocation::OUTCOME_SUCCESS);

        // credential_health = 0.0 (expired), so score drops by 40%
        $this->assertLessThanOrEqual(0.6, $score);
    }

    public function test_high_error_rate_reduces_score(): void
    {
        $credential = new AgentConnectorCredential;
        $credential->status = AgentConnectorCredential::STATUS_ACTIVE;
        $credential->token_expires_at = now()->addHour();

        $connection = new AgentConnectorConnection;
        $connection->health_score = 0.5;
        $connection->setRelation('credential', $credential);

        $score = $this->scorer->calculateQuickScore($connection, AgentConnectorInvocation::OUTCOME_FAILED);

        // Failed outcome contributes 0.0 error impact
        $this->assertLessThan(1.0, $score);
    }

    public function test_health_status_mapping(): void
    {
        $this->assertEquals('healthy', $this->scorer->healthStatus(1.0));
        $this->assertEquals('healthy', $this->scorer->healthStatus(0.8));
        $this->assertEquals('degraded', $this->scorer->healthStatus(0.7));
        $this->assertEquals('degraded', $this->scorer->healthStatus(0.5));
        $this->assertEquals('unhealthy', $this->scorer->healthStatus(0.49));
        $this->assertEquals('unhealthy', $this->scorer->healthStatus(0.0));
    }
}
