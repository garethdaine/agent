<?php

namespace Tests\Feature;

use App\Models\AgentFeatureSetting;
use App\Models\User;
use App\Support\Agent\FeatureFlagManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class DelegationUiAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_delegation_nav_visible_when_ui_enabled(): void
    {
        config(['delegation.ui_enabled' => true]);
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->where('delegationEnabled', true)
        );
    }

    public function test_delegation_nav_hidden_when_ui_disabled(): void
    {
        config(['delegation.ui_enabled' => false]);
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->where('delegationEnabled', false)
        );
    }

    public function test_delegation_index_accessible_when_enabled(): void
    {
        config(['delegation.ui_enabled' => true]);
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/agent/delegation');

        $response->assertOk();
    }

    public function test_delegation_index_blocked_when_disabled(): void
    {
        config(['delegation.ui_enabled' => false]);
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/agent/delegation');

        $response->assertForbidden();
    }

    public function test_database_override_enables_delegation_ui_without_config_toggle(): void
    {
        config(['delegation.ui_enabled' => false]);

        AgentFeatureSetting::query()->create([
            'key' => FeatureFlagManager::DELEGATION_UI_ENABLED,
            'is_enabled' => true,
        ]);

        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/dashboard');
        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->where('delegationEnabled', true)
        );

        $this->actingAs($user)->get('/agent/delegation')->assertOk();
    }
}
