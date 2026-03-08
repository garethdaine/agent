<?php

declare(strict_types=1);

namespace Tests\Unit\Connectors;

use App\Models\AgentConnector;
use App\Models\AgentConnectorConnection;
use App\Models\AgentConnectorCredential;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConnectorModelsTest extends TestCase
{
    use RefreshDatabase;

    public function test_agent_connector_has_expected_casts(): void
    {
        $connector = new AgentConnector;
        $casts = $connector->getCasts();

        $this->assertEquals('array', $casts['industries']);
        $this->assertEquals('array', $casts['auth_config']);
        $this->assertEquals('array', $casts['rate_limits']);
        $this->assertEquals('array', $casts['actions']);
        $this->assertEquals('array', $casts['webhooks']);
    }

    public function test_connector_connection_belongs_to_team(): void
    {
        $connection = new AgentConnectorConnection;
        $this->assertInstanceOf(BelongsTo::class, $connection->team());
    }

    public function test_connector_connection_belongs_to_connector(): void
    {
        $connection = new AgentConnectorConnection;
        $this->assertInstanceOf(BelongsTo::class, $connection->connector());
    }

    public function test_connector_connection_has_many_invocations(): void
    {
        $connection = new AgentConnectorConnection;
        $this->assertInstanceOf(HasMany::class, $connection->invocations());
    }

    public function test_connector_connection_has_one_credential(): void
    {
        $connection = new AgentConnectorConnection;
        $this->assertInstanceOf(HasOne::class, $connection->credential());
    }

    public function test_connector_credential_belongs_to_connection(): void
    {
        $credential = new AgentConnectorCredential;
        $this->assertInstanceOf(BelongsTo::class, $credential->team());
        $this->assertInstanceOf(BelongsTo::class, $credential->connector());
    }

    public function test_connector_connection_scope_for_team(): void
    {
        $connectionA = AgentConnectorConnection::factory()->create();
        $connectionB = AgentConnectorConnection::factory()->create();

        $results = AgentConnectorConnection::forTeam($connectionA->team_id)->get();

        $this->assertTrue($results->contains('id', $connectionA->id));
        $this->assertFalse($results->contains('id', $connectionB->id));
    }

    public function test_connector_connection_scope_connected(): void
    {
        $connected = AgentConnectorConnection::factory()->create([
            'status' => AgentConnectorConnection::STATUS_CONNECTED,
        ]);
        $pending = AgentConnectorConnection::factory()->create([
            'status' => AgentConnectorConnection::STATUS_PENDING,
        ]);
        $disconnected = AgentConnectorConnection::factory()->create([
            'status' => AgentConnectorConnection::STATUS_DISCONNECTED,
        ]);

        $results = AgentConnectorConnection::connected()->get();

        $this->assertTrue($results->contains('id', $connected->id));
        $this->assertFalse($results->contains('id', $pending->id));
        $this->assertFalse($results->contains('id', $disconnected->id));
    }
}
