<?php

namespace Tests\Unit\Connectors\Telemetry;

use App\Models\AgentConnector;
use App\Models\AgentConnectorConnection;
use App\Models\AgentConnectorInvocation;
use App\Support\Connectors\Telemetry\ConnectorCostAttributor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConnectorCostAttributorTest extends TestCase
{
    use RefreshDatabase;

    private ConnectorCostAttributor $attributor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->attributor = new ConnectorCostAttributor;
    }

    public function test_metered_connector_costs_attributed_to_workflow(): void
    {
        $connector = AgentConnector::factory()->create([
            'cost_model' => 'metered',
            'rate_limits' => [
                'requests_per_minute' => 60,
                'cost_per_call_usd' => 0.01,
            ],
        ]);
        $connection = AgentConnectorConnection::factory()->create([
            'connector_id' => $connector->id,
        ]);
        $invocation = AgentConnectorInvocation::factory()->create([
            'connection_id' => $connection->id,
            'connector_id' => $connector->id,
            'outcome' => AgentConnectorInvocation::OUTCOME_SUCCESS,
        ]);

        $cost = $this->attributor->attributeCost($invocation);

        $this->assertEquals(0.01, $cost);
    }

    public function test_free_connector_has_zero_cost(): void
    {
        $connector = AgentConnector::factory()->create([
            'cost_model' => 'free',
        ]);
        $connection = AgentConnectorConnection::factory()->create([
            'connector_id' => $connector->id,
        ]);
        $invocation = AgentConnectorInvocation::factory()->create([
            'connection_id' => $connection->id,
            'connector_id' => $connector->id,
            'outcome' => AgentConnectorInvocation::OUTCOME_SUCCESS,
        ]);

        $cost = $this->attributor->attributeCost($invocation);

        $this->assertEquals(0.0, $cost);
    }

    public function test_quota_limited_connector_tracks_remaining(): void
    {
        $connector = AgentConnector::factory()->create([
            'cost_model' => 'quota-limited',
            'rate_limits' => [
                'requests_per_minute' => 60,
                'daily_limit' => 5000,
            ],
        ]);
        $connection = AgentConnectorConnection::factory()->create([
            'connector_id' => $connector->id,
            'action_count_24h' => 4000,
        ]);
        $invocation = AgentConnectorInvocation::factory()->create([
            'connection_id' => $connection->id,
            'connector_id' => $connector->id,
            'outcome' => AgentConnectorInvocation::OUTCOME_SUCCESS,
        ]);

        $remaining = $this->attributor->getRemainingQuota($invocation);

        // 5000 daily limit - 4000 used = 1000 remaining
        $this->assertEquals(1000, $remaining);
    }

    public function test_quota_limited_connector_has_zero_cost(): void
    {
        $connector = AgentConnector::factory()->create([
            'cost_model' => 'quota-limited',
            'rate_limits' => [
                'requests_per_minute' => 60,
                'daily_limit' => 5000,
            ],
        ]);
        $connection = AgentConnectorConnection::factory()->create([
            'connector_id' => $connector->id,
        ]);
        $invocation = AgentConnectorInvocation::factory()->create([
            'connection_id' => $connection->id,
            'connector_id' => $connector->id,
        ]);

        $cost = $this->attributor->attributeCost($invocation);

        // Quota-limited connectors have no per-call monetary cost
        $this->assertEquals(0.0, $cost);
    }

    public function test_metered_connector_with_token_based_pricing(): void
    {
        $connector = AgentConnector::factory()->create([
            'cost_model' => 'metered',
            'rate_limits' => [
                'requests_per_minute' => 60,
                'cost_per_token_usd' => 0.001,
            ],
        ]);
        $connection = AgentConnectorConnection::factory()->create([
            'connector_id' => $connector->id,
        ]);
        $invocation = AgentConnectorInvocation::factory()->create([
            'connection_id' => $connection->id,
            'connector_id' => $connector->id,
            'token_usage' => 500,
            'outcome' => AgentConnectorInvocation::OUTCOME_SUCCESS,
        ]);

        $cost = $this->attributor->attributeCost($invocation);

        // 500 tokens * 0.001 per token = 0.5
        $this->assertEquals(0.5, $cost);
    }
}
