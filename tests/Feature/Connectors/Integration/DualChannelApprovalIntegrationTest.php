<?php

declare(strict_types=1);

namespace Tests\Feature\Connectors\Integration;

use App\Jobs\Connectors\ExpireConnectorApprovalsJob;
use App\Models\AgentConnector;
use App\Models\AgentConnectorApproval;
use App\Models\AgentConnectorConnection;
use App\Models\Team;
use App\Models\User;
use App\Services\Connectors\DualChannelApprovalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class DualChannelApprovalIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Team $team;

    private AgentConnectorConnection $connection;

    private DualChannelApprovalService $approvalService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->team = Team::factory()->create(['user_id' => $this->user->id]);
        $this->user->switchTeam($this->team);

        $connector = AgentConnector::factory()->create([
            'name' => 'test-approval-connector',
        ]);

        $this->connection = AgentConnectorConnection::factory()->create([
            'team_id' => $this->team->id,
            'connector_id' => $connector->id,
            'connected_by' => $this->user->id,
        ]);

        $this->approvalService = app(DualChannelApprovalService::class);
    }

    public function test_approval_created_and_resolved_via_dashboard(): void
    {
        // Step 1: Request approval
        $approval = $this->approvalService->requestApproval(
            connection: $this->connection,
            actionName: 'create_invoice',
            type: AgentConnectorApproval::TYPE_APPROVAL,
            context: ['amount' => 1500, 'currency' => 'GBP'],
            runAttemptId: (string) Str::uuid(),
        );

        $this->assertDatabaseHas('agent_connector_approvals', [
            'id' => $approval->id,
            'status' => AgentConnectorApproval::STATUS_PENDING,
            'action_name' => 'create_invoice',
            'type' => AgentConnectorApproval::TYPE_APPROVAL,
        ]);

        $this->assertTrue($this->approvalService->isPending($approval->id));
        $this->assertFalse($this->approvalService->isApproved($approval->id));

        // Step 2: Resolve via dashboard
        $resolved = $this->approvalService->resolveApproval(
            approval: $approval,
            user: $this->user,
            channel: 'dashboard',
            status: AgentConnectorApproval::STATUS_APPROVED,
            payload: ['note' => 'Looks good'],
        );

        $this->assertTrue($resolved);

        // Step 3: Verify resolution
        $approval->refresh();
        $this->assertEquals(AgentConnectorApproval::STATUS_APPROVED, $approval->status);
        $this->assertEquals($this->user->id, $approval->resolved_by);
        $this->assertEquals('dashboard', $approval->resolved_via);
        $this->assertNotNull($approval->resolved_at);
        $this->assertEquals(['note' => 'Looks good'], $approval->resolution_payload);

        $this->assertTrue($this->approvalService->isApproved($approval->id));
        $this->assertFalse($this->approvalService->isPending($approval->id));
    }

    public function test_approval_expires_after_timeout(): void
    {
        // Create approval that will expire
        $approval = AgentConnectorApproval::factory()->create([
            'connection_id' => $this->connection->id,
            'connector_id' => $this->connection->connector_id,
            'status' => AgentConnectorApproval::STATUS_PENDING,
            'expires_at' => now()->subMinute(), // Already expired
        ]);

        // Run the expire job
        $job = new ExpireConnectorApprovalsJob;
        $job->handle();

        // Verify expired
        $approval->refresh();
        $this->assertEquals(AgentConnectorApproval::STATUS_EXPIRED, $approval->status);
    }

    public function test_concurrent_approval_resolution_first_wins(): void
    {
        $secondUser = User::factory()->create();
        $this->team->users()->attach($secondUser, ['role' => 'admin']);

        // Create approval
        $approval = $this->approvalService->requestApproval(
            connection: $this->connection,
            actionName: 'delete_record',
            type: AgentConnectorApproval::TYPE_APPROVAL,
            context: ['record_id' => 'rec_abc'],
        );

        // First resolution succeeds
        $result1 = $this->approvalService->resolveApproval(
            approval: $approval,
            user: $this->user,
            channel: 'dashboard',
            status: AgentConnectorApproval::STATUS_APPROVED,
        );
        $this->assertTrue($result1);

        // Second resolution fails (already resolved)
        $result2 = $this->approvalService->resolveApproval(
            approval: $approval,
            user: $secondUser,
            channel: 'messenger',
            status: AgentConnectorApproval::STATUS_DENIED,
        );
        $this->assertFalse($result2);

        // Verify first resolution won
        $approval->refresh();
        $this->assertEquals(AgentConnectorApproval::STATUS_APPROVED, $approval->status);
        $this->assertEquals($this->user->id, $approval->resolved_by);
        $this->assertEquals('dashboard', $approval->resolved_via);
    }
}
