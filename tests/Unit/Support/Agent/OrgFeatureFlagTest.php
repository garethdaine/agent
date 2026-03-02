<?php

namespace Tests\Unit\Support\Agent;

use App\Support\Agent\FeatureFlagManager;
use Tests\TestCase;

class OrgFeatureFlagTest extends TestCase
{
    public function test_org_sub_flags_disabled_when_global_disabled(): void
    {
        config(['agent.org.enabled' => false]);
        config(['agent.org.features.profiles' => true]);

        $manager = app(FeatureFlagManager::class);

        $this->assertFalse($manager->isEnabled(FeatureFlagManager::ORG_ENABLED));
        $this->assertFalse($manager->isOrgFeatureEnabled('profiles'));
    }

    public function test_org_sub_flags_apply_when_global_enabled(): void
    {
        config(['agent.org.enabled' => true]);
        config(['agent.org.features.profiles' => true]);
        config(['agent.org.features.councils' => false]);

        $manager = app(FeatureFlagManager::class);

        $this->assertTrue($manager->isOrgFeatureEnabled('profiles'));
        $this->assertFalse($manager->isOrgFeatureEnabled('councils'));
    }
}
