<?php

namespace Tests\Unit\Connectors;

use App\Support\Agent\FeatureFlagManager;
use Tests\TestCase;

class FeatureFlagConnectorsTest extends TestCase
{
    public function test_connector_flags_constants_exist(): void
    {
        $this->assertEquals('connectors.enabled', FeatureFlagManager::CONNECTORS_ENABLED);
        $this->assertEquals('connectors.ui_enabled', FeatureFlagManager::CONNECTORS_UI_ENABLED);
        $this->assertEquals('connectors.webhooks_enabled', FeatureFlagManager::CONNECTORS_WEBHOOKS_ENABLED);
        $this->assertEquals('connectors.auto_resolve', FeatureFlagManager::CONNECTORS_AUTO_RESOLVE);
        $this->assertEquals('connectors.write_actions', FeatureFlagManager::CONNECTORS_WRITE_ACTIONS);
        $this->assertEquals('connectors.credential_refresh', FeatureFlagManager::CONNECTORS_CREDENTIAL_REFRESH);
    }

    public function test_get_connectors_flags_returns_all_six(): void
    {
        $flags = FeatureFlagManager::getConnectorsFlags();

        $this->assertCount(6, $flags);
        $this->assertContains(FeatureFlagManager::CONNECTORS_ENABLED, $flags);
        $this->assertContains(FeatureFlagManager::CONNECTORS_UI_ENABLED, $flags);
        $this->assertContains(FeatureFlagManager::CONNECTORS_WEBHOOKS_ENABLED, $flags);
        $this->assertContains(FeatureFlagManager::CONNECTORS_AUTO_RESOLVE, $flags);
        $this->assertContains(FeatureFlagManager::CONNECTORS_WRITE_ACTIONS, $flags);
        $this->assertContains(FeatureFlagManager::CONNECTORS_CREDENTIAL_REFRESH, $flags);
    }

    public function test_connector_flags_default_disabled(): void
    {
        $manager = new FeatureFlagManager;

        foreach (FeatureFlagManager::getConnectorsFlags() as $flag) {
            $this->assertFalse($manager->enabled($flag), "Flag {$flag} should default to disabled");
        }
    }

    public function test_definitions_include_all_connector_flags(): void
    {
        $managedKeys = FeatureFlagManager::managedKeys();

        foreach (FeatureFlagManager::getConnectorsFlags() as $flag) {
            $this->assertContains($flag, $managedKeys, "Flag {$flag} should be in managedKeys");
        }
    }
}
