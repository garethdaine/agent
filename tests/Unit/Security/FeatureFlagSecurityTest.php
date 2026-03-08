<?php

declare(strict_types=1);

namespace Tests\Unit\Security;

use App\Support\Agent\FeatureFlagManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FeatureFlagSecurityTest extends TestCase
{
    use RefreshDatabase;

    private FeatureFlagManager $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manager = new FeatureFlagManager;
    }

    public function test_security_content_trust_constant_exists(): void
    {
        $this->assertSame('security.content_trust', FeatureFlagManager::SECURITY_CONTENT_TRUST);
    }

    public function test_security_injection_detection_constant_exists(): void
    {
        $this->assertSame('security.injection_detection', FeatureFlagManager::SECURITY_INJECTION_DETECTION);
    }

    public function test_security_exfiltration_detection_constant_exists(): void
    {
        $this->assertSame('security.exfiltration_detection', FeatureFlagManager::SECURITY_EXFILTRATION_DETECTION);
    }

    public function test_enabled_returns_true_for_security_content_trust(): void
    {
        $this->assertTrue($this->manager->enabled(FeatureFlagManager::SECURITY_CONTENT_TRUST));
    }

    public function test_enabled_returns_true_for_security_injection_detection(): void
    {
        $this->assertTrue($this->manager->enabled(FeatureFlagManager::SECURITY_INJECTION_DETECTION));
    }

    public function test_enabled_returns_true_for_security_exfiltration_detection(): void
    {
        $this->assertTrue($this->manager->enabled(FeatureFlagManager::SECURITY_EXFILTRATION_DETECTION));
    }

    public function test_get_security_flags_returns_all_three_keys(): void
    {
        $flags = FeatureFlagManager::getSecurityFlags();

        $this->assertCount(3, $flags);
        $this->assertContains(FeatureFlagManager::SECURITY_CONTENT_TRUST, $flags);
        $this->assertContains(FeatureFlagManager::SECURITY_INJECTION_DETECTION, $flags);
        $this->assertContains(FeatureFlagManager::SECURITY_EXFILTRATION_DETECTION, $flags);
    }

    public function test_update_many_with_security_keys_set_to_false_still_returns_true(): void
    {
        $this->manager->updateMany([
            FeatureFlagManager::SECURITY_CONTENT_TRUST => false,
            FeatureFlagManager::SECURITY_INJECTION_DETECTION => false,
            FeatureFlagManager::SECURITY_EXFILTRATION_DETECTION => false,
        ], null);

        $this->assertTrue($this->manager->enabled(FeatureFlagManager::SECURITY_CONTENT_TRUST));
        $this->assertTrue($this->manager->enabled(FeatureFlagManager::SECURITY_INJECTION_DETECTION));
        $this->assertTrue($this->manager->enabled(FeatureFlagManager::SECURITY_EXFILTRATION_DETECTION));
    }

    public function test_all_includes_security_flags_as_enabled(): void
    {
        $allFlags = $this->manager->all();

        $securityKeys = [
            FeatureFlagManager::SECURITY_CONTENT_TRUST,
            FeatureFlagManager::SECURITY_INJECTION_DETECTION,
            FeatureFlagManager::SECURITY_EXFILTRATION_DETECTION,
        ];

        foreach ($securityKeys as $key) {
            $found = collect($allFlags)->firstWhere('key', $key);
            $this->assertNotNull($found, "Security flag {$key} should appear in all()");
            $this->assertTrue($found['is_enabled'], "Security flag {$key} should be enabled in all()");
        }
    }

    public function test_security_flags_are_in_managed_keys(): void
    {
        $keys = FeatureFlagManager::managedKeys();

        $this->assertContains(FeatureFlagManager::SECURITY_CONTENT_TRUST, $keys);
        $this->assertContains(FeatureFlagManager::SECURITY_INJECTION_DETECTION, $keys);
        $this->assertContains(FeatureFlagManager::SECURITY_EXFILTRATION_DETECTION, $keys);
    }
}
