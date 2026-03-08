<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Support\Agent\FeatureFlagManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FeatureSettingsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_feature_settings_can_be_loaded_with_defaults(): void
    {
        config()->set('delegation.enabled', false);
        config()->set('delegation.ui_enabled', true);
        config()->set('agent.interrogation.adversarial_review_enabled', false);
        config()->set('repo_analysis.enabled', false);
        config()->set('repo_analysis.ai.enabled', true);

        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->getJson('/agent/api/v1/features/settings');
        $response->assertOk();
        $response->assertJsonFragment([
            'key' => FeatureFlagManager::DELEGATION_ENABLED,
            'is_enabled' => false,
            'default_enabled' => false,
            'is_overridden' => false,
        ]);
        $response->assertJsonFragment([
            'key' => FeatureFlagManager::DELEGATION_UI_ENABLED,
            'is_enabled' => true,
            'default_enabled' => true,
            'is_overridden' => false,
        ]);
        $response->assertJsonFragment([
            'key' => FeatureFlagManager::REPO_ANALYSIS_ENABLED,
            'is_enabled' => false,
            'default_enabled' => false,
            'is_overridden' => false,
        ]);
        $response->assertJsonFragment([
            'key' => FeatureFlagManager::REPO_ANALYSIS_AI_ENABLED,
            'is_enabled' => true,
            'default_enabled' => true,
            'is_overridden' => false,
        ]);
    }

    public function test_feature_settings_can_be_updated_and_persisted(): void
    {
        config()->set('delegation.enabled', false);
        config()->set('delegation.ui_enabled', false);
        config()->set('agent.interrogation.adversarial_review_enabled', false);
        config()->set('repo_analysis.enabled', false);
        config()->set('repo_analysis.ai.enabled', true);

        $user = User::factory()->create();
        $this->actingAs($user);

        $this->putJson('/agent/api/v1/features/settings', [
            'flags' => [
                FeatureFlagManager::DELEGATION_ENABLED => true,
                FeatureFlagManager::DELEGATION_UI_ENABLED => true,
                FeatureFlagManager::ADVERSARIAL_REVIEW_ENABLED => true,
                FeatureFlagManager::REPO_ANALYSIS_ENABLED => true,
                FeatureFlagManager::REPO_ANALYSIS_AI_ENABLED => false,
            ],
        ])->assertOk()
            ->assertJsonFragment([
                'key' => FeatureFlagManager::DELEGATION_ENABLED,
                'is_enabled' => true,
                'is_overridden' => true,
            ])
            ->assertJsonFragment([
                'key' => FeatureFlagManager::ADVERSARIAL_REVIEW_ENABLED,
                'is_enabled' => true,
                'is_overridden' => true,
            ])
            ->assertJsonFragment([
                'key' => FeatureFlagManager::REPO_ANALYSIS_ENABLED,
                'is_enabled' => true,
                'is_overridden' => true,
            ])
            ->assertJsonFragment([
                'key' => FeatureFlagManager::REPO_ANALYSIS_AI_ENABLED,
                'is_enabled' => false,
                'is_overridden' => true,
            ]);

        $this->assertDatabaseHas('agent_feature_settings', [
            'key' => FeatureFlagManager::DELEGATION_ENABLED,
            'is_enabled' => true,
            'updated_by_user_id' => $user->id,
        ]);
        $this->assertDatabaseHas('agent_feature_settings', [
            'key' => FeatureFlagManager::REPO_ANALYSIS_ENABLED,
            'is_enabled' => true,
            'updated_by_user_id' => $user->id,
        ]);
        $this->assertDatabaseHas('agent_feature_settings', [
            'key' => FeatureFlagManager::REPO_ANALYSIS_AI_ENABLED,
            'is_enabled' => false,
            'updated_by_user_id' => $user->id,
        ]);

        $this->getJson('/agent/api/v1/features/settings')
            ->assertOk()
            ->assertJsonFragment([
                'key' => FeatureFlagManager::DELEGATION_UI_ENABLED,
                'is_enabled' => true,
                'is_overridden' => true,
            ]);
    }

    public function test_feature_settings_reject_unsupported_keys(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->putJson('/agent/api/v1/features/settings', [
            'flags' => [
                'unknown.feature' => true,
            ],
        ])->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }

    public function test_feature_settings_returns_error_when_store_is_unavailable(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Schema::drop('agent_feature_settings');

        $this->putJson('/agent/api/v1/features/settings', [
            'flags' => [
                FeatureFlagManager::DELEGATION_ENABLED => true,
            ],
        ])->assertStatus(500)
            ->assertJsonPath('error.code', 'FEATURE_SETTINGS_STORE_UNAVAILABLE');
    }
}
