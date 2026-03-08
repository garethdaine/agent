<?php

declare(strict_types=1);

namespace Tests\Feature\Connectors;

use App\Models\User;
use App\Support\Agent\FeatureFlagManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConnectorDashboardWidgetsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
    }

    public function test_dashboard_includes_connectors_ui_enabled_prop_when_enabled(): void
    {
        FeatureFlagManager::enable(FeatureFlagManager::CONNECTORS_ENABLED);
        FeatureFlagManager::enable(FeatureFlagManager::CONNECTORS_UI_ENABLED);

        $response = $this->actingAs($this->user)
            ->get(route('dashboard'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->where('connectorsUiEnabled', true)
        );
    }

    public function test_dashboard_excludes_connector_data_when_disabled(): void
    {
        $response = $this->actingAs($this->user)
            ->get(route('dashboard'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->where('connectorsUiEnabled', false)
        );
    }
}
