<?php

declare(strict_types=1);

namespace Tests\Feature\Memory;

use App\Support\Memory\CoreMemoryManager;
use App\Support\Memory\MemorySettingsService;
use Tests\TestCase;

/**
 * Tests for MemoryServiceProvider config-gated registration.
 *
 * Verifies:
 * - Provider registers when MEMORY_ENABLED=true
 * - Provider throws RuntimeException when MEMORY_ENABLED=false
 */
class MemoryServiceProviderTest extends TestCase
{
    /**
     * Test that CoreMemoryManager is resolvable when memory is enabled.
     */
    public function test_core_memory_manager_is_resolvable_when_enabled(): void
    {
        config(['memory.enabled' => true]);

        $manager = app(CoreMemoryManager::class);

        $this->assertInstanceOf(CoreMemoryManager::class, $manager);
    }

    /**
     * Test that CoreMemoryManager throws when memory is disabled.
     */
    public function test_core_memory_manager_throws_when_disabled(): void
    {
        config(['memory.enabled' => false]);

        // Unbind the singleton so we get a fresh resolution
        app()->forgetInstance(CoreMemoryManager::class);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Memory system is disabled');

        app(CoreMemoryManager::class);
    }

    /**
     * Test that MemorySettingsService is resolvable when memory is enabled.
     */
    public function test_memory_settings_service_is_resolvable_when_enabled(): void
    {
        config(['memory.enabled' => true]);

        $service = app(MemorySettingsService::class);

        $this->assertInstanceOf(MemorySettingsService::class, $service);
    }

    /**
     * Test that MemorySettingsService is always resolvable (even when memory disabled)
     * because users need to configure provider keys before enabling the system.
     */
    public function test_memory_settings_service_resolvable_when_disabled(): void
    {
        config(['memory.enabled' => false]);

        app()->forgetInstance(MemorySettingsService::class);

        $service = app(MemorySettingsService::class);

        $this->assertInstanceOf(MemorySettingsService::class, $service);
    }

    /**
     * Test that memory.enabled config defaults to false.
     */
    public function test_memory_enabled_defaults_to_false(): void
    {
        $this->assertFalse(config('memory.enabled'));
    }

    /**
     * Test that services are singletons when enabled.
     */
    public function test_services_are_singletons_when_enabled(): void
    {
        config(['memory.enabled' => true]);

        $manager1 = app(CoreMemoryManager::class);
        $manager2 = app(CoreMemoryManager::class);

        $this->assertSame($manager1, $manager2);

        $settings1 = app(MemorySettingsService::class);
        $settings2 = app(MemorySettingsService::class);

        $this->assertSame($settings1, $settings2);
    }
}
