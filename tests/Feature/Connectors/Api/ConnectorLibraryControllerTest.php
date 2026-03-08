<?php

namespace Tests\Feature\Connectors\Api;

use App\Models\AgentConnector;
use App\Models\Team;
use App\Models\User;
use App\Support\Agent\FeatureFlagManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConnectorLibraryControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Team $team;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->team = Team::factory()->create(['user_id' => $this->user->id]);
        $this->user->switchTeam($this->team);

        FeatureFlagManager::enable(FeatureFlagManager::CONNECTORS_ENABLED);
    }

    public function test_index_returns_available_connectors(): void
    {
        AgentConnector::factory()->count(3)->create();
        AgentConnector::factory()->deprecated()->create();

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/agent/api/v1/connectors');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'display_name', 'description', 'category', 'auth_type', 'status'],
                ],
            ]);

        $this->assertCount(3, $response->json('data'));
    }

    public function test_index_filters_by_category(): void
    {
        AgentConnector::factory()->create(['category' => 'accounting']);
        AgentConnector::factory()->create(['category' => 'crm']);
        AgentConnector::factory()->create(['category' => 'accounting']);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/agent/api/v1/connectors?category=accounting');

        $response->assertOk();
        $this->assertCount(2, $response->json('data'));
    }

    public function test_index_filters_by_industry(): void
    {
        AgentConnector::factory()->create(['industries' => ['insurance', 'finance']]);
        AgentConnector::factory()->create(['industries' => ['healthcare']]);
        AgentConnector::factory()->create(['industries' => ['insurance']]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/agent/api/v1/connectors?industry=insurance');

        $response->assertOk();
        $this->assertCount(2, $response->json('data'));
    }

    public function test_show_returns_connector_detail(): void
    {
        $connector = AgentConnector::factory()->create([
            'actions' => [
                ['name' => 'list-invoices', 'method' => 'GET', 'path' => '/invoices'],
                ['name' => 'create-invoice', 'method' => 'POST', 'path' => '/invoices'],
            ],
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/agent/api/v1/connectors/{$connector->id}");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => ['id', 'name', 'display_name', 'description', 'category', 'auth_type', 'actions', 'status'],
            ])
            ->assertJsonPath('data.id', $connector->id);
    }

    public function test_actions_returns_available_actions(): void
    {
        $connector = AgentConnector::factory()->create([
            'actions' => [
                ['name' => 'list-invoices', 'method' => 'GET', 'path' => '/invoices'],
                ['name' => 'create-invoice', 'method' => 'POST', 'path' => '/invoices'],
            ],
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/agent/api/v1/connectors/{$connector->id}/actions");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['name', 'method', 'path'],
                ],
            ]);

        $this->assertCount(2, $response->json('data'));
    }

    public function test_unauthenticated_returns_401(): void
    {
        $response = $this->getJson('/agent/api/v1/connectors');

        $response->assertUnauthorized();
    }
}
