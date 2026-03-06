<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Compliance;

use App\Support\Agent\FeatureFlagManager;
use App\Support\Compliance\ComplianceFlagResolver;
use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ComplianceFlagResolverTest extends TestCase
{
    private ComplianceFlagResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();

        // Set up default compliance config values
        Config::set('agent.compliance.enabled', true);
        Config::set('agent.compliance.enforcement_mode', 'advisory');
        Config::set('agent.compliance.plan_gate_enabled', true);
        Config::set('agent.compliance.verification_gate_enabled', true);
        Config::set('agent.compliance.elegance_gate_enabled', false);
        Config::set('agent.compliance.lessons_enabled', true);

        $this->resolver = new ComplianceFlagResolver(
            app(FeatureFlagManager::class)
        );
    }

    #[Test]
    public function resolve_returns_global_default_when_no_tenant_override(): void
    {
        Config::set('agent.compliance.plan_gate_enabled', true);

        $result = $this->resolver->resolve(FeatureFlagManager::COMPLIANCE_PLAN_GATE, tenantId: null);

        $this->assertTrue($result);
    }

    #[Test]
    public function resolve_returns_global_default_when_tenant_has_no_override(): void
    {
        Config::set('agent.compliance.verification_gate_enabled', false);

        // Tenant ID 999 has no override stored
        $result = $this->resolver->resolve(FeatureFlagManager::COMPLIANCE_VERIFICATION_GATE, tenantId: 999);

        $this->assertFalse($result);
    }

    #[Test]
    public function resolve_returns_tenant_value_when_stricter_than_global_for_boolean(): void
    {
        Config::set('agent.compliance.elegance_gate_enabled', false);

        // Create resolver with mock that returns tenant override enabling the gate
        $resolver = $this->createResolverWithTenantOverride(
            tenantId: 1,
            key: FeatureFlagManager::COMPLIANCE_ELEGANCE_GATE,
            value: true
        );

        $result = $resolver->resolve(FeatureFlagManager::COMPLIANCE_ELEGANCE_GATE, tenantId: 1);

        // Tenant enabling a gate that's disabled globally is stricter, so it should be enabled
        $this->assertTrue($result);
    }

    #[Test]
    public function resolve_ignores_tenant_value_when_trying_to_weaken_global_for_boolean(): void
    {
        Config::set('agent.compliance.plan_gate_enabled', true);

        // Create resolver with mock that returns tenant override trying to disable the gate
        $resolver = $this->createResolverWithTenantOverride(
            tenantId: 1,
            key: FeatureFlagManager::COMPLIANCE_PLAN_GATE,
            value: false
        );

        $result = $resolver->resolve(FeatureFlagManager::COMPLIANCE_PLAN_GATE, tenantId: 1);

        // Tenant trying to disable should be ignored - global stays enabled
        $this->assertTrue($result);
    }

    #[Test]
    public function resolve_all_returns_all_compliance_flags_as_array(): void
    {
        Config::set('agent.compliance.enabled', true);
        Config::set('agent.compliance.enforcement_mode', 'warning');
        Config::set('agent.compliance.plan_gate_enabled', true);
        Config::set('agent.compliance.verification_gate_enabled', false);
        Config::set('agent.compliance.elegance_gate_enabled', true);
        Config::set('agent.compliance.lessons_enabled', false);

        $result = $this->resolver->resolveAll(tenantId: null);

        $this->assertIsArray($result);
        $this->assertArrayHasKey(FeatureFlagManager::COMPLIANCE_ENABLED, $result);
        $this->assertArrayHasKey(FeatureFlagManager::COMPLIANCE_PLAN_GATE, $result);
        $this->assertArrayHasKey(FeatureFlagManager::COMPLIANCE_VERIFICATION_GATE, $result);
        $this->assertArrayHasKey(FeatureFlagManager::COMPLIANCE_ELEGANCE_GATE, $result);
        $this->assertArrayHasKey(FeatureFlagManager::COMPLIANCE_LESSONS, $result);
        // enforcement_mode is a string, not a boolean toggle — resolved via getEffectiveMode()
        $this->assertArrayNotHasKey(FeatureFlagManager::COMPLIANCE_ENFORCEMENT_MODE, $result);

        $this->assertTrue($result[FeatureFlagManager::COMPLIANCE_ENABLED]);
        $this->assertTrue($result[FeatureFlagManager::COMPLIANCE_PLAN_GATE]);
        $this->assertFalse($result[FeatureFlagManager::COMPLIANCE_VERIFICATION_GATE]);
        $this->assertTrue($result[FeatureFlagManager::COMPLIANCE_ELEGANCE_GATE]);
        $this->assertFalse($result[FeatureFlagManager::COMPLIANCE_LESSONS]);
    }

    #[Test]
    public function get_effective_mode_returns_advisory_as_default(): void
    {
        Config::set('agent.compliance.enforcement_mode', 'advisory');

        $result = $this->resolver->getEffectiveMode(tenantId: null);

        $this->assertSame('advisory', $result);
    }

    #[Test]
    public function get_effective_mode_returns_warning_when_configured(): void
    {
        Config::set('agent.compliance.enforcement_mode', 'warning');

        $result = $this->resolver->getEffectiveMode(tenantId: null);

        $this->assertSame('warning', $result);
    }

    #[Test]
    public function get_effective_mode_returns_strict_when_configured(): void
    {
        Config::set('agent.compliance.enforcement_mode', 'strict');

        $result = $this->resolver->getEffectiveMode(tenantId: null);

        $this->assertSame('strict', $result);
    }

    #[Test]
    public function enforcement_mode_tenant_can_upgrade_from_advisory_to_warning(): void
    {
        Config::set('agent.compliance.enforcement_mode', 'advisory');

        $resolver = $this->createResolverWithTenantOverride(
            tenantId: 1,
            key: FeatureFlagManager::COMPLIANCE_ENFORCEMENT_MODE,
            value: 'warning'
        );

        $result = $resolver->getEffectiveMode(tenantId: 1);

        $this->assertSame('warning', $result);
    }

    #[Test]
    public function enforcement_mode_tenant_can_upgrade_from_advisory_to_strict(): void
    {
        Config::set('agent.compliance.enforcement_mode', 'advisory');

        $resolver = $this->createResolverWithTenantOverride(
            tenantId: 1,
            key: FeatureFlagManager::COMPLIANCE_ENFORCEMENT_MODE,
            value: 'strict'
        );

        $result = $resolver->getEffectiveMode(tenantId: 1);

        $this->assertSame('strict', $result);
    }

    #[Test]
    public function enforcement_mode_tenant_can_upgrade_from_warning_to_strict(): void
    {
        Config::set('agent.compliance.enforcement_mode', 'warning');

        $resolver = $this->createResolverWithTenantOverride(
            tenantId: 1,
            key: FeatureFlagManager::COMPLIANCE_ENFORCEMENT_MODE,
            value: 'strict'
        );

        $result = $resolver->getEffectiveMode(tenantId: 1);

        $this->assertSame('strict', $result);
    }

    #[Test]
    public function enforcement_mode_tenant_cannot_downgrade_from_strict_to_warning(): void
    {
        Config::set('agent.compliance.enforcement_mode', 'strict');

        $resolver = $this->createResolverWithTenantOverride(
            tenantId: 1,
            key: FeatureFlagManager::COMPLIANCE_ENFORCEMENT_MODE,
            value: 'warning'
        );

        $result = $resolver->getEffectiveMode(tenantId: 1);

        // Should remain strict, tenant cannot weaken
        $this->assertSame('strict', $result);
    }

    #[Test]
    public function enforcement_mode_tenant_cannot_downgrade_from_warning_to_advisory(): void
    {
        Config::set('agent.compliance.enforcement_mode', 'warning');

        $resolver = $this->createResolverWithTenantOverride(
            tenantId: 1,
            key: FeatureFlagManager::COMPLIANCE_ENFORCEMENT_MODE,
            value: 'advisory'
        );

        $result = $resolver->getEffectiveMode(tenantId: 1);

        // Should remain warning, tenant cannot weaken
        $this->assertSame('warning', $result);
    }

    #[Test]
    public function enforcement_mode_tenant_cannot_downgrade_from_strict_to_advisory(): void
    {
        Config::set('agent.compliance.enforcement_mode', 'strict');

        $resolver = $this->createResolverWithTenantOverride(
            tenantId: 1,
            key: FeatureFlagManager::COMPLIANCE_ENFORCEMENT_MODE,
            value: 'advisory'
        );

        $result = $resolver->getEffectiveMode(tenantId: 1);

        // Should remain strict, tenant cannot weaken
        $this->assertSame('strict', $result);
    }

    #[Test]
    public function boolean_flag_tenant_can_enable_but_not_disable(): void
    {
        // Global is disabled
        Config::set('agent.compliance.lessons_enabled', false);

        $resolver = $this->createResolverWithTenantOverride(
            tenantId: 1,
            key: FeatureFlagManager::COMPLIANCE_LESSONS,
            value: true
        );

        // Tenant can enable
        $result = $resolver->resolve(FeatureFlagManager::COMPLIANCE_LESSONS, tenantId: 1);
        $this->assertTrue($result);

        // Now test opposite: global is enabled
        Config::set('agent.compliance.lessons_enabled', true);

        $resolver2 = $this->createResolverWithTenantOverride(
            tenantId: 2,
            key: FeatureFlagManager::COMPLIANCE_LESSONS,
            value: false
        );

        // Tenant cannot disable
        $result2 = $resolver2->resolve(FeatureFlagManager::COMPLIANCE_LESSONS, tenantId: 2);
        $this->assertTrue($result2);
    }

    #[Test]
    public function is_enabled_convenience_method_returns_compliance_enabled_flag(): void
    {
        Config::set('agent.compliance.enabled', true);

        $this->assertTrue($this->resolver->isEnabled(tenantId: null));

        Config::set('agent.compliance.enabled', false);
        $resolver = new ComplianceFlagResolver(app(FeatureFlagManager::class));

        $this->assertFalse($resolver->isEnabled(tenantId: null));
    }

    #[Test]
    public function get_tenant_override_always_returns_null(): void
    {
        $resolver = app(ComplianceFlagResolver::class);

        // Use reflection to test protected method
        $method = new \ReflectionMethod($resolver, 'getTenantOverride');
        $method->setAccessible(true);

        $this->assertNull($method->invoke($resolver, 123, 'any_key'));
        $this->assertNull($method->invoke($resolver, 456, 'another_key'));
        $this->assertNull($method->invoke($resolver, null, 'key'));
    }

    #[Test]
    public function resolve_uses_global_value_ignoring_tenant(): void
    {
        Config::set('agent.compliance.test_flag', 'global_value');

        $resolver = app(ComplianceFlagResolver::class);

        // Should return global value regardless of tenant ID
        $this->assertEquals('global_value', $resolver->resolve('compliance.test_flag', 123));
        $this->assertEquals('global_value', $resolver->resolve('compliance.test_flag', 456));
        $this->assertEquals('global_value', $resolver->resolve('compliance.test_flag'));
    }

    /**
     * Creates a resolver with a mock that returns a specific tenant override.
     *
     * This simulates what would happen when a tenant has an override stored in the database.
     */
    private function createResolverWithTenantOverride(int $tenantId, string $key, mixed $value): ComplianceFlagResolver
    {
        return new class($tenantId, $key, $value, app(FeatureFlagManager::class)) extends ComplianceFlagResolver
        {
            private int $mockTenantId;

            private string $mockKey;

            private mixed $mockValue;

            public function __construct(int $mockTenantId, string $mockKey, mixed $mockValue, FeatureFlagManager $flagManager)
            {
                parent::__construct($flagManager);
                $this->mockTenantId = $mockTenantId;
                $this->mockKey = $mockKey;
                $this->mockValue = $mockValue;
            }

            protected function getTenantOverride(?int $tenantId, string $key): mixed
            {
                if ($tenantId === $this->mockTenantId && $key === $this->mockKey) {
                    return $this->mockValue;
                }

                return null;
            }
        };
    }
}
