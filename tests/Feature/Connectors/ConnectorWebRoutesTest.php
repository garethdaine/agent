<?php

declare(strict_types=1);

namespace Tests\Feature\Connectors;

use App\Models\AgentConnector;
use App\Models\User;
use App\Support\Agent\FeatureFlagManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class ConnectorWebRoutesTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
    }

    public function test_connectors_index_page_renders(): void
    {
        FeatureFlagManager::enable(FeatureFlagManager::CONNECTORS_ENABLED);
        FeatureFlagManager::enable(FeatureFlagManager::CONNECTORS_UI_ENABLED);

        $response = $this->actingAs($this->user)
            ->get(route('tools.connectors.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Tools/Connectors/Index')
        );
    }

    public function test_connectors_index_requires_auth(): void
    {
        FeatureFlagManager::enable(FeatureFlagManager::CONNECTORS_ENABLED);
        FeatureFlagManager::enable(FeatureFlagManager::CONNECTORS_UI_ENABLED);

        $response = $this->get(route('tools.connectors.index'));

        $response->assertRedirect(route('login'));
    }

    public function test_connectors_show_page_renders(): void
    {
        FeatureFlagManager::enable(FeatureFlagManager::CONNECTORS_ENABLED);
        FeatureFlagManager::enable(FeatureFlagManager::CONNECTORS_UI_ENABLED);

        $connector = AgentConnector::factory()->create();

        $response = $this->actingAs($this->user)
            ->get(route('tools.connectors.show', $connector->id));

        $response->assertStatus(200);
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Tools/Connectors/Show')
            ->has('connectorId')
        );
    }

    public function test_connectors_pages_return_404_when_ui_flag_disabled(): void
    {
        // connectors.ui_enabled is false by default
        $response = $this->actingAs($this->user)
            ->get(route('tools.connectors.index'));

        $response->assertStatus(404);
    }

    public function test_connectors_pages_return_404_when_master_flag_disabled(): void
    {
        // Only enable UI flag, not the master flag
        FeatureFlagManager::enable(FeatureFlagManager::CONNECTORS_UI_ENABLED);

        $response = $this->actingAs($this->user)
            ->get(route('tools.connectors.index'));

        $response->assertStatus(404);
    }

    public function test_connectors_library_page_renders(): void
    {
        FeatureFlagManager::enable(FeatureFlagManager::CONNECTORS_ENABLED);
        FeatureFlagManager::enable(FeatureFlagManager::CONNECTORS_UI_ENABLED);

        $response = $this->actingAs($this->user)
            ->get(route('tools.connectors.library'));

        $response->assertStatus(200);
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Tools/Connectors/Library')
        );
    }
}
