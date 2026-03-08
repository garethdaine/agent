<?php

namespace Tests\Feature\Connectors;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ConnectorMigrationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_migrations_create_all_connector_tables(): void
    {
        $tables = [
            'agent_connectors',
            'agent_connector_connections',
            'agent_connector_credentials',
            'agent_connector_invocations',
            'agent_connector_webhook_events',
            'agent_connector_credential_events',
            'agent_connector_approvals',
        ];

        foreach ($tables as $table) {
            $this->assertTrue(Schema::hasTable($table), "Table {$table} should exist");
        }
    }

    public function test_agent_connectors_table_has_expected_columns(): void
    {
        $expectedColumns = [
            'id', 'name', 'display_name', 'description', 'category',
            'industries', 'version', 'auth_type', 'auth_config', 'base_url',
            'rate_limits', 'cost_model', 'risk_level', 'actions', 'webhooks',
            'icon_path', 'mcp_tool_prefix', 'status', 'created_at', 'updated_at',
        ];

        foreach ($expectedColumns as $column) {
            $this->assertTrue(
                Schema::hasColumn('agent_connectors', $column),
                "Column {$column} should exist on agent_connectors"
            );
        }
    }

    public function test_agent_connector_connections_has_unique_constraint(): void
    {
        $team = \App\Models\Team::factory()->create();
        $connector = \App\Models\AgentConnector::factory()->create();

        \App\Models\AgentConnectorConnection::factory()->create([
            'team_id' => $team->id,
            'connector_id' => $connector->id,
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        \App\Models\AgentConnectorConnection::factory()->create([
            'team_id' => $team->id,
            'connector_id' => $connector->id,
        ]);
    }

    public function test_agent_connector_credentials_has_unique_constraint(): void
    {
        $team = \App\Models\Team::factory()->create();
        $connector = \App\Models\AgentConnector::factory()->create();

        \App\Models\AgentConnectorCredential::factory()->create([
            'team_id' => $team->id,
            'connector_id' => $connector->id,
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        \App\Models\AgentConnectorCredential::factory()->create([
            'team_id' => $team->id,
            'connector_id' => $connector->id,
        ]);
    }

    public function test_teams_table_has_connector_vault_key(): void
    {
        $this->assertTrue(
            Schema::hasColumn('teams', 'connector_vault_key'),
            'teams table should have connector_vault_key column'
        );
    }
}
