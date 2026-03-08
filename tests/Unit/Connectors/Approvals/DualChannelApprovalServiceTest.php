<?php

declare(strict_types=1);

namespace Tests\Unit\Connectors\Approvals;

use App\Models\AgentConnector;
use App\Models\AgentConnectorApproval;
use App\Models\AgentConnectorConnection;
use App\Models\Team;
use App\Models\User;
use App\Services\Connectors\DualChannelApprovalService;
use App\Services\Messenger\SystemNotificationDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class DualChannelApprovalServiceTest extends TestCase
{
    use RefreshDatabase;

    private DualChannelApprovalService $service;

    private SystemNotificationDispatcher $notificationDispatcher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->notificationDispatcher = Mockery::mock(SystemNotificationDispatcher::class);
        $this->notificationDispatcher->shouldReceive('dispatch')->byDefault();
        $this->service = new DualChannelApprovalService($this->notificationDispatcher);
    }

    public function test_creates_approval_request_with_expiry(): void
    {
        $team = Team::factory()->create(['connector_vault_key' => str_repeat('a', 64)]);
        $connector = AgentConnector::factory()->create();
        $connection = AgentConnectorConnection::factory()->create([
            'team_id' => $team->id,
            'connector_id' => $connector->id,
            'status' => AgentConnectorConnection::STATUS_CONNECTED,
        ]);

        $approval = $this->service->requestApproval(
            connection: $connection,
            actionName: 'create_invoice',
            type: AgentConnectorApproval::TYPE_APPROVAL,
            context: ['amount' => 100],
        );

        $this->assertInstanceOf(AgentConnectorApproval::class, $approval);
        $this->assertEquals(AgentConnectorApproval::STATUS_PENDING, $approval->status);
        $this->assertEquals('create_invoice', $approval->action_name);
        $this->assertNotNull($approval->expires_at);
        $this->assertTrue($approval->expires_at->isFuture());

        $expectedExpiry = now()->addMinutes(config('connectors.approval_timeout_minutes', 15));
        $this->assertTrue($approval->expires_at->diffInSeconds($expectedExpiry) < 5);
    }

    public function test_sends_messenger_notification(): void
    {
        $team = Team::factory()->create(['connector_vault_key' => str_repeat('b', 64)]);
        $connector = AgentConnector::factory()->create(['display_name' => 'Xero']);
        $connection = AgentConnectorConnection::factory()->create([
            'team_id' => $team->id,
            'connector_id' => $connector->id,
            'status' => AgentConnectorConnection::STATUS_CONNECTED,
            'connected_by' => User::factory()->create()->id,
        ]);

        $this->notificationDispatcher
            ->shouldReceive('dispatch')
            ->once()
            ->withArgs(function ($payload) {
                return $payload->type === 'connector.approval_requested'
                    && $payload->severity === 'warning';
            });

        $this->service->requestApproval(
            connection: $connection,
            actionName: 'create_invoice',
            type: AgentConnectorApproval::TYPE_APPROVAL,
            context: ['amount' => 100],
        );
    }

    public function test_sends_dashboard_notification(): void
    {
        $team = Team::factory()->create(['connector_vault_key' => str_repeat('c', 64)]);
        $connector = AgentConnector::factory()->create(['display_name' => 'Xero']);
        $connection = AgentConnectorConnection::factory()->create([
            'team_id' => $team->id,
            'connector_id' => $connector->id,
            'status' => AgentConnectorConnection::STATUS_CONNECTED,
            'connected_by' => User::factory()->create()->id,
        ]);

        $approval = $this->service->requestApproval(
            connection: $connection,
            actionName: 'create_invoice',
            type: AgentConnectorApproval::TYPE_APPROVAL,
            context: [],
        );

        // Approval record itself serves as the dashboard notification
        // (dashboard polls/subscribes to pending approvals)
        $this->assertDatabaseHas('agent_connector_approvals', [
            'id' => $approval->id,
            'status' => AgentConnectorApproval::STATUS_PENDING,
        ]);
    }

    public function test_resolve_via_messenger_marks_approved(): void
    {
        $approval = $this->createPendingApproval();
        $user = User::factory()->create();

        $won = $this->service->resolveApproval(
            approval: $approval,
            user: $user,
            channel: 'messenger',
            status: AgentConnectorApproval::STATUS_APPROVED,
        );

        $this->assertTrue($won);
        $approval->refresh();
        $this->assertEquals(AgentConnectorApproval::STATUS_APPROVED, $approval->status);
        $this->assertEquals('messenger', $approval->resolved_via);
        $this->assertEquals($user->id, $approval->resolved_by);
        $this->assertNotNull($approval->resolved_at);
    }

    public function test_resolve_via_dashboard_marks_approved(): void
    {
        $approval = $this->createPendingApproval();
        $user = User::factory()->create();

        $won = $this->service->resolveApproval(
            approval: $approval,
            user: $user,
            channel: 'dashboard',
            status: AgentConnectorApproval::STATUS_APPROVED,
        );

        $this->assertTrue($won);
        $approval->refresh();
        $this->assertEquals(AgentConnectorApproval::STATUS_APPROVED, $approval->status);
        $this->assertEquals('dashboard', $approval->resolved_via);
    }

    public function test_first_response_wins(): void
    {
        $approval = $this->createPendingApproval();
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $firstWon = $this->service->resolveApproval(
            approval: $approval,
            user: $user1,
            channel: 'messenger',
            status: AgentConnectorApproval::STATUS_APPROVED,
        );

        $secondWon = $this->service->resolveApproval(
            approval: $approval->fresh(),
            user: $user2,
            channel: 'dashboard',
            status: AgentConnectorApproval::STATUS_APPROVED,
        );

        $this->assertTrue($firstWon);
        $this->assertFalse($secondWon);

        $approval->refresh();
        $this->assertEquals('messenger', $approval->resolved_via);
        $this->assertEquals($user1->id, $approval->resolved_by);
    }

    public function test_expired_approval_cannot_be_resolved(): void
    {
        $approval = $this->createPendingApproval();
        $approval->update(['expires_at' => now()->subMinute()]);
        $user = User::factory()->create();

        $won = $this->service->resolveApproval(
            approval: $approval->fresh(),
            user: $user,
            channel: 'messenger',
            status: AgentConnectorApproval::STATUS_APPROVED,
        );

        $this->assertFalse($won);
    }

    public function test_clarification_type_stores_response_payload(): void
    {
        $team = Team::factory()->create(['connector_vault_key' => str_repeat('d', 64)]);
        $connector = AgentConnector::factory()->create();
        $connection = AgentConnectorConnection::factory()->create([
            'team_id' => $team->id,
            'connector_id' => $connector->id,
            'status' => AgentConnectorConnection::STATUS_CONNECTED,
        ]);

        $approval = $this->service->requestApproval(
            connection: $connection,
            actionName: 'create_invoice',
            type: AgentConnectorApproval::TYPE_CLARIFICATION,
            context: ['options' => ['connection-a', 'connection-b']],
        );

        $user = User::factory()->create();
        $payload = ['selected_alias' => 'connection-a'];

        $won = $this->service->resolveApproval(
            approval: $approval,
            user: $user,
            channel: 'messenger',
            status: AgentConnectorApproval::STATUS_CLARIFIED,
            payload: $payload,
        );

        $this->assertTrue($won);
        $approval->refresh();
        $this->assertEquals(AgentConnectorApproval::TYPE_CLARIFICATION, $approval->type);
        $this->assertEquals($payload, $approval->resolution_payload);
    }

    private function createPendingApproval(): AgentConnectorApproval
    {
        $team = Team::factory()->create(['connector_vault_key' => str_repeat('e', 64)]);
        $connector = AgentConnector::factory()->create();
        $connection = AgentConnectorConnection::factory()->create([
            'team_id' => $team->id,
            'connector_id' => $connector->id,
            'status' => AgentConnectorConnection::STATUS_CONNECTED,
        ]);

        return AgentConnectorApproval::factory()->create([
            'connection_id' => $connection->id,
            'connector_id' => $connector->id,
            'status' => AgentConnectorApproval::STATUS_PENDING,
            'expires_at' => now()->addMinutes(15),
        ]);
    }
}
