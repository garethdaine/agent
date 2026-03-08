<?php

namespace Tests\Unit\Connectors\Telemetry;

use App\Models\AgentConnector;
use App\Models\AgentConnectorConnection;
use App\Models\AgentConnectorInvocation;
use App\Support\Connectors\Telemetry\ConnectorReliabilityContributor;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConnectorReliabilityContributorTest extends TestCase
{
    use RefreshDatabase;

    private ConnectorReliabilityContributor $contributor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->contributor = new ConnectorReliabilityContributor;
    }

    public function test_connector_failures_reduce_workflow_reliability(): void
    {
        $connector = AgentConnector::factory()->create();
        $connection = AgentConnectorConnection::factory()->create([
            'connector_id' => $connector->id,
        ]);

        $workflowKey = 'test-workflow-key';
        $from = Carbon::now()->subHour();
        $to = Carbon::now();

        // Create 5 failed invocations and 5 successful ones
        AgentConnectorInvocation::factory()->count(5)->create([
            'connection_id' => $connection->id,
            'connector_id' => $connector->id,
            'workflow_key' => $workflowKey,
            'outcome' => AgentConnectorInvocation::OUTCOME_FAILED,
            'created_at' => Carbon::now()->subMinutes(30),
        ]);
        AgentConnectorInvocation::factory()->count(5)->create([
            'connection_id' => $connection->id,
            'connector_id' => $connector->id,
            'workflow_key' => $workflowKey,
            'outcome' => AgentConnectorInvocation::OUTCOME_SUCCESS,
            'created_at' => Carbon::now()->subMinutes(30),
        ]);

        $contribution = $this->contributor->getReliabilityContribution($workflowKey, $from, $to);

        // 50% success rate → contribution should be 0.5
        $this->assertEquals(0.5, $contribution);
    }

    public function test_connector_successes_maintain_reliability(): void
    {
        $connector = AgentConnector::factory()->create();
        $connection = AgentConnectorConnection::factory()->create([
            'connector_id' => $connector->id,
        ]);

        $workflowKey = 'test-workflow-all-success';
        $from = Carbon::now()->subHour();
        $to = Carbon::now();

        AgentConnectorInvocation::factory()->count(10)->create([
            'connection_id' => $connection->id,
            'connector_id' => $connector->id,
            'workflow_key' => $workflowKey,
            'outcome' => AgentConnectorInvocation::OUTCOME_SUCCESS,
            'created_at' => Carbon::now()->subMinutes(30),
        ]);

        $contribution = $this->contributor->getReliabilityContribution($workflowKey, $from, $to);

        $this->assertEquals(1.0, $contribution);
    }

    public function test_reliability_contribution_is_weighted(): void
    {
        $connector = AgentConnector::factory()->create();
        $connection = AgentConnectorConnection::factory()->create([
            'connector_id' => $connector->id,
        ]);

        $workflowKey = 'test-workflow-weighted';
        $from = Carbon::now()->subHour();
        $to = Carbon::now();

        // 8 successes, 2 failures → 80% success rate
        AgentConnectorInvocation::factory()->count(8)->create([
            'connection_id' => $connection->id,
            'connector_id' => $connector->id,
            'workflow_key' => $workflowKey,
            'outcome' => AgentConnectorInvocation::OUTCOME_SUCCESS,
            'created_at' => Carbon::now()->subMinutes(30),
        ]);
        AgentConnectorInvocation::factory()->count(2)->create([
            'connection_id' => $connection->id,
            'connector_id' => $connector->id,
            'workflow_key' => $workflowKey,
            'outcome' => AgentConnectorInvocation::OUTCOME_FAILED,
            'created_at' => Carbon::now()->subMinutes(30),
        ]);

        $contribution = $this->contributor->getReliabilityContribution($workflowKey, $from, $to);

        $this->assertEquals(0.8, $contribution);
    }

    public function test_no_invocations_returns_perfect_score(): void
    {
        $workflowKey = 'test-workflow-empty';
        $from = Carbon::now()->subHour();
        $to = Carbon::now();

        $contribution = $this->contributor->getReliabilityContribution($workflowKey, $from, $to);

        // No data means no degradation signal
        $this->assertEquals(1.0, $contribution);
    }

    public function test_only_counts_invocations_in_time_range(): void
    {
        $connector = AgentConnector::factory()->create();
        $connection = AgentConnectorConnection::factory()->create([
            'connector_id' => $connector->id,
        ]);

        $workflowKey = 'test-workflow-time-range';
        $from = Carbon::now()->subHour();
        $to = Carbon::now();

        // Failures outside the time range (2 hours ago)
        AgentConnectorInvocation::factory()->count(5)->create([
            'connection_id' => $connection->id,
            'connector_id' => $connector->id,
            'workflow_key' => $workflowKey,
            'outcome' => AgentConnectorInvocation::OUTCOME_FAILED,
            'created_at' => Carbon::now()->subHours(2),
        ]);

        // Successes within the time range
        AgentConnectorInvocation::factory()->count(5)->create([
            'connection_id' => $connection->id,
            'connector_id' => $connector->id,
            'workflow_key' => $workflowKey,
            'outcome' => AgentConnectorInvocation::OUTCOME_SUCCESS,
            'created_at' => Carbon::now()->subMinutes(30),
        ]);

        $contribution = $this->contributor->getReliabilityContribution($workflowKey, $from, $to);

        // Only in-range invocations count: all successes
        $this->assertEquals(1.0, $contribution);
    }
}
