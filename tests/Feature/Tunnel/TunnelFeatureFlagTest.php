<?php

declare(strict_types=1);

namespace Tests\Feature\Tunnel;

use App\Models\AgentFeatureSetting;
use App\Support\Agent\FeatureFlagManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TunnelFeatureFlagTest extends TestCase
{
    use RefreshDatabase;

    public function test_tunnel_feature_flag_defaults_to_disabled(): void
    {
        config()->set('tunnel.enabled', false);

        $manager = app(FeatureFlagManager::class);

        $this->assertFalse($manager->isEnabled(FeatureFlagManager::TUNNEL_ENABLED));
    }

    public function test_tunnel_feature_flag_can_be_enabled(): void
    {
        AgentFeatureSetting::create([
            'key' => FeatureFlagManager::TUNNEL_ENABLED,
            'is_enabled' => true,
        ]);

        $manager = app(FeatureFlagManager::class);

        $this->assertTrue($manager->isEnabled(FeatureFlagManager::TUNNEL_ENABLED));
    }

    public function test_tunnel_feature_flag_constant_exists(): void
    {
        $this->assertSame('tunnel.enabled', FeatureFlagManager::TUNNEL_ENABLED);
        $this->assertContains(
            FeatureFlagManager::TUNNEL_ENABLED,
            FeatureFlagManager::managedKeys()
        );
    }
}
