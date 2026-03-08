<?php

declare(strict_types=1);

namespace Tests\Unit\Connectors\Mcp;

use App\Models\AgentConnector;
use App\Models\AgentConnectorConnection;
use App\Models\Team;
use App\Support\Connectors\Mcp\ConnectorMcpRegistrar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConnectorMcpRegistrarTest extends TestCase
{
    use RefreshDatabase;

    private ConnectorMcpRegistrar $registrar;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registrar = new ConnectorMcpRegistrar;
    }

    public function test_registers_connector_actions_as_mcp_tools(): void
    {
        $team = Team::factory()->create();
        $connector = AgentConnector::factory()->create([
            'mcp_tool_prefix' => 'xero',
            'actions' => [
                ['name' => 'list-invoices', 'method' => 'GET', 'path' => '/Invoices'],
                ['name' => 'create-invoice', 'method' => 'POST', 'path' => '/Invoices'],
            ],
            'status' => AgentConnector::STATUS_AVAILABLE,
        ]);
        AgentConnectorConnection::factory()->create([
            'team_id' => $team->id,
            'connector_id' => $connector->id,
            'status' => AgentConnectorConnection::STATUS_CONNECTED,
        ]);

        $tools = $this->registrar->getRegisteredTools($team);

        $this->assertCount(2, $tools);
    }

    public function test_mcp_tool_name_follows_prefix_convention(): void
    {
        $team = Team::factory()->create();
        $connector = AgentConnector::factory()->create([
            'mcp_tool_prefix' => 'slack',
            'actions' => [
                ['name' => 'send-message', 'method' => 'POST', 'path' => '/chat.postMessage'],
            ],
            'status' => AgentConnector::STATUS_AVAILABLE,
        ]);
        AgentConnectorConnection::factory()->create([
            'team_id' => $team->id,
            'connector_id' => $connector->id,
            'status' => AgentConnectorConnection::STATUS_CONNECTED,
        ]);

        $tools = $this->registrar->getRegisteredTools($team);

        $this->assertCount(1, $tools);
        $this->assertEquals('slack.send-message', $tools[0]['name']);
    }

    public function test_mcp_tool_includes_schema(): void
    {
        $team = Team::factory()->create();
        $connector = AgentConnector::factory()->create([
            'mcp_tool_prefix' => 'hubspot',
            'description' => 'HubSpot CRM',
            'actions' => [
                ['name' => 'list-contacts', 'method' => 'GET', 'path' => '/contacts'],
            ],
            'status' => AgentConnector::STATUS_AVAILABLE,
        ]);
        AgentConnectorConnection::factory()->create([
            'team_id' => $team->id,
            'connector_id' => $connector->id,
            'status' => AgentConnectorConnection::STATUS_CONNECTED,
        ]);

        $tools = $this->registrar->getRegisteredTools($team);

        $tool = $tools[0];
        $this->assertArrayHasKey('name', $tool);
        $this->assertArrayHasKey('description', $tool);
        $this->assertArrayHasKey('input_schema', $tool);
        $this->assertArrayHasKey('stability', $tool);
        $this->assertArrayHasKey('scope', $tool);
    }

    public function test_only_connected_connectors_register_tools(): void
    {
        $team = Team::factory()->create();
        $connector = AgentConnector::factory()->create([
            'mcp_tool_prefix' => 'jira',
            'actions' => [
                ['name' => 'list-issues', 'method' => 'GET', 'path' => '/issues'],
            ],
            'status' => AgentConnector::STATUS_AVAILABLE,
        ]);
        // Create a disconnected connection
        AgentConnectorConnection::factory()->disconnected()->create([
            'team_id' => $team->id,
            'connector_id' => $connector->id,
        ]);

        $tools = $this->registrar->getRegisteredTools($team);

        $this->assertCount(0, $tools);
    }

    public function test_mcp_tools_respect_stability_tier(): void
    {
        $team = Team::factory()->create();
        $connector = AgentConnector::factory()->create([
            'mcp_tool_prefix' => 'stripe',
            'risk_level' => 'elevated',
            'actions' => [
                ['name' => 'create-charge', 'method' => 'POST', 'path' => '/charges', 'stability' => 'beta'],
            ],
            'status' => AgentConnector::STATUS_AVAILABLE,
        ]);
        AgentConnectorConnection::factory()->create([
            'team_id' => $team->id,
            'connector_id' => $connector->id,
            'status' => AgentConnectorConnection::STATUS_CONNECTED,
        ]);

        $tools = $this->registrar->getRegisteredTools($team);

        $this->assertCount(1, $tools);
        $this->assertEquals('beta', $tools[0]['stability']);
    }

    public function test_scope_includes_tenant_environment_role(): void
    {
        $team = Team::factory()->create();
        $connector = AgentConnector::factory()->create([
            'mcp_tool_prefix' => 'salesforce',
            'actions' => [
                ['name' => 'list-leads', 'method' => 'GET', 'path' => '/leads'],
            ],
            'status' => AgentConnector::STATUS_AVAILABLE,
        ]);
        AgentConnectorConnection::factory()->create([
            'team_id' => $team->id,
            'connector_id' => $connector->id,
            'status' => AgentConnectorConnection::STATUS_CONNECTED,
        ]);

        $tools = $this->registrar->getRegisteredTools($team);

        $scope = $tools[0]['scope'];
        $this->assertEquals($team->id, $scope['tenant']);
        $this->assertEquals('production', $scope['environment']);
        $this->assertEquals('connector', $scope['role']);
    }

    public function test_multiple_connectors_register_all_tools(): void
    {
        $team = Team::factory()->create();

        $xero = AgentConnector::factory()->create([
            'mcp_tool_prefix' => 'xero',
            'actions' => [
                ['name' => 'list-invoices', 'method' => 'GET', 'path' => '/Invoices'],
            ],
            'status' => AgentConnector::STATUS_AVAILABLE,
        ]);
        $slack = AgentConnector::factory()->create([
            'mcp_tool_prefix' => 'slack',
            'actions' => [
                ['name' => 'send-message', 'method' => 'POST', 'path' => '/chat.postMessage'],
                ['name' => 'list-channels', 'method' => 'GET', 'path' => '/conversations.list'],
            ],
            'status' => AgentConnector::STATUS_AVAILABLE,
        ]);

        AgentConnectorConnection::factory()->create([
            'team_id' => $team->id,
            'connector_id' => $xero->id,
            'status' => AgentConnectorConnection::STATUS_CONNECTED,
        ]);
        AgentConnectorConnection::factory()->create([
            'team_id' => $team->id,
            'connector_id' => $slack->id,
            'status' => AgentConnectorConnection::STATUS_CONNECTED,
        ]);

        $tools = $this->registrar->getRegisteredTools($team);

        $this->assertCount(3, $tools);

        $toolNames = array_column($tools, 'name');
        $this->assertContains('xero.list-invoices', $toolNames);
        $this->assertContains('slack.send-message', $toolNames);
        $this->assertContains('slack.list-channels', $toolNames);
    }
}
