<?php

declare(strict_types=1);

namespace Tests\Unit\Connectors\Jobs;

use App\Jobs\Connectors\ExpireConnectorApprovalsJob;
use App\Models\AgentConnector;
use App\Models\AgentConnectorApproval;
use App\Models\AgentConnectorConnection;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpireConnectorApprovalsJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_expires_pending_approvals_past_expiry(): void
    {
        $approval = $this->createApproval(
            status: AgentConnectorApproval::STATUS_PENDING,
            expiresAt: now()->subMinutes(5),
        );

        (new ExpireConnectorApprovalsJob)->handle();

        $approval->refresh();
        $this->assertEquals(AgentConnectorApproval::STATUS_EXPIRED, $approval->status);
    }

    public function test_does_not_expire_future_approvals(): void
    {
        $approval = $this->createApproval(
            status: AgentConnectorApproval::STATUS_PENDING,
            expiresAt: now()->addMinutes(10),
        );

        (new ExpireConnectorApprovalsJob)->handle();

        $approval->refresh();
        $this->assertEquals(AgentConnectorApproval::STATUS_PENDING, $approval->status);
    }

    public function test_dispatched_on_connector_approvals_queue(): void
    {
        $job = new ExpireConnectorApprovalsJob;

        $this->assertEquals('connector-approvals', $job->queue);
    }

    private function createApproval(string $status, $expiresAt): AgentConnectorApproval
    {
        $team = Team::factory()->create(['connector_vault_key' => str_repeat('z', 64)]);
        $connector = AgentConnector::factory()->create();
        $connection = AgentConnectorConnection::factory()->create([
            'team_id' => $team->id,
            'connector_id' => $connector->id,
            'status' => AgentConnectorConnection::STATUS_CONNECTED,
        ]);

        return AgentConnectorApproval::factory()->create([
            'connection_id' => $connection->id,
            'connector_id' => $connector->id,
            'status' => $status,
            'expires_at' => $expiresAt,
        ]);
    }
}
