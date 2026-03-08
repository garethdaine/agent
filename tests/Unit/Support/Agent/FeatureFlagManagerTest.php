<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Agent;

use App\Models\AgentFeatureSetting;
use App\Support\Agent\FeatureFlagManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FeatureFlagManagerTest extends TestCase
{
    use RefreshDatabase;

    public function test_enabled_uses_config_default_when_override_missing(): void
    {
        config()->set('delegation.enabled', true);

        $manager = app(FeatureFlagManager::class);

        $this->assertTrue($manager->enabled(FeatureFlagManager::DELEGATION_ENABLED));
    }

    public function test_enabled_prefers_database_override(): void
    {
        config()->set('delegation.enabled', true);

        AgentFeatureSetting::query()->create([
            'key' => FeatureFlagManager::DELEGATION_ENABLED,
            'is_enabled' => false,
        ]);

        $manager = app(FeatureFlagManager::class);

        $this->assertFalse($manager->enabled(FeatureFlagManager::DELEGATION_ENABLED));
    }

    public function test_all_includes_override_metadata(): void
    {
        AgentFeatureSetting::query()->create([
            'key' => FeatureFlagManager::ADVERSARIAL_REVIEW_ENABLED,
            'is_enabled' => true,
        ]);

        $manager = app(FeatureFlagManager::class);
        $rows = $manager->all();

        $adversarial = collect($rows)->firstWhere('key', FeatureFlagManager::ADVERSARIAL_REVIEW_ENABLED);

        $this->assertIsArray($adversarial);
        $this->assertTrue((bool) ($adversarial['is_enabled'] ?? false));
        $this->assertTrue((bool) ($adversarial['is_overridden'] ?? false));
    }
}
