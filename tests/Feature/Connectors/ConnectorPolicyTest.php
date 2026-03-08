<?php

declare(strict_types=1);

namespace Tests\Feature\Connectors;

use App\Models\AgentConnector;
use App\Models\AgentConnectorConnection;
use App\Models\Team;
use App\Models\User;
use App\Policies\ConnectorPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConnectorPolicyTest extends TestCase
{
    use RefreshDatabase;

    private ConnectorPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new ConnectorPolicy;
    }

    public function test_any_authenticated_user_can_view_connectors(): void
    {
        $user = User::factory()->create();

        $this->assertTrue($this->policy->viewAny($user));
    }

    public function test_team_member_can_view_own_connection(): void
    {
        $team = Team::factory()->create();
        $user = User::factory()->create();
        $team->users()->attach($user, ['role' => 'editor']);

        $connector = AgentConnector::factory()->create();
        $connection = AgentConnectorConnection::factory()->create([
            'team_id' => $team->id,
            'connector_id' => $connector->id,
        ]);

        $this->assertTrue($this->policy->view($user, $connection));
    }

    public function test_non_team_member_cannot_view_connection(): void
    {
        $team = Team::factory()->create();
        $otherUser = User::factory()->create();

        $connector = AgentConnector::factory()->create();
        $connection = AgentConnectorConnection::factory()->create([
            'team_id' => $team->id,
            'connector_id' => $connector->id,
        ]);

        $this->assertFalse($this->policy->view($otherUser, $connection));
    }

    public function test_team_admin_can_manage_connection(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $team->users()->attach($user, ['role' => 'admin']);

        $connector = AgentConnector::factory()->create();
        $connection = AgentConnectorConnection::factory()->create([
            'team_id' => $team->id,
            'connector_id' => $connector->id,
        ]);

        $this->assertTrue($this->policy->manage($user, $connection));
    }

    public function test_non_admin_cannot_manage_connection(): void
    {
        $team = Team::factory()->create();
        $user = User::factory()->create();
        $team->users()->attach($user, ['role' => 'editor']);

        $connector = AgentConnector::factory()->create();
        $connection = AgentConnectorConnection::factory()->create([
            'team_id' => $team->id,
            'connector_id' => $connector->id,
        ]);

        $this->assertFalse($this->policy->manage($user, $connection));
    }
}
