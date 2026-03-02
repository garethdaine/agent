<?php

declare(strict_types=1);

namespace Tests\Unit\Memory;

use App\Support\Agent\FeatureFlagManager;
use App\Support\Memory\MemoryCapabilityResolver;
use App\Support\Memory\MemorySettingsService;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * Tests for MemoryCapabilityResolver operating mode detection.
 *
 * Verifies:
 * - No provider keys → returns 'no-api' mode
 * - OpenAI key only → returns 'api' mode
 * - Anthropic key only → returns 'degraded' mode (no embeddings)
 * - Both keys → returns 'api' mode
 * - pgvector availability detection
 * - Neo4j connectivity detection
 */
class MemoryCapabilityResolverTest extends TestCase
{
    private MemorySettingsService&MockInterface $settingsService;

    private MemoryCapabilityResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();

        // Enable memory for these tests
        config(['memory.enabled' => true]);
        config(['memory.api_enabled' => true]);

        $this->settingsService = Mockery::mock(MemorySettingsService::class);
        $featureFlags = app(FeatureFlagManager::class);
        $this->resolver = new MemoryCapabilityResolver($this->settingsService, $featureFlags);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Test that no provider keys returns 'no-api' mode.
     */
    public function test_no_provider_keys_returns_no_api_mode(): void
    {
        $userId = 1;

        $this->settingsService
            ->shouldReceive('getConfiguredProviders')
            ->with($userId)
            ->once()
            ->andReturn([]);

        $mode = $this->resolver->getOperatingMode($userId);

        $this->assertEquals('no-api', $mode);
    }

    /**
     * Test that OpenAI key only returns 'api' mode.
     */
    public function test_openai_key_only_returns_api_mode(): void
    {
        $userId = 1;

        $this->settingsService
            ->shouldReceive('getConfiguredProviders')
            ->with($userId)
            ->once()
            ->andReturn(['openai']);

        $mode = $this->resolver->getOperatingMode($userId);

        $this->assertEquals('api', $mode);
    }

    /**
     * Test that Anthropic key only returns 'degraded' mode (no embeddings).
     */
    public function test_anthropic_key_only_returns_degraded_mode(): void
    {
        $userId = 1;

        $this->settingsService
            ->shouldReceive('getConfiguredProviders')
            ->with($userId)
            ->once()
            ->andReturn(['anthropic']);

        $mode = $this->resolver->getOperatingMode($userId);

        $this->assertEquals('degraded', $mode);
    }

    /**
     * Test that both keys configured returns 'api' mode.
     */
    public function test_both_keys_configured_returns_api_mode(): void
    {
        $userId = 1;

        $this->settingsService
            ->shouldReceive('getConfiguredProviders')
            ->with($userId)
            ->once()
            ->andReturn(['openai', 'anthropic']);

        $mode = $this->resolver->getOperatingMode($userId);

        $this->assertEquals('api', $mode);
    }

    /**
     * Test that 'no-api' mode is returned when memory is disabled.
     */
    public function test_memory_disabled_returns_no_api_mode(): void
    {
        config(['memory.enabled' => false]);

        $userId = 1;

        // No provider check needed when memory is disabled
        $this->settingsService
            ->shouldReceive('getConfiguredProviders')
            ->never();

        $mode = $this->resolver->getOperatingMode($userId);

        $this->assertEquals('no-api', $mode);
    }

    /**
     * Test that 'no-api' mode is returned when api_enabled is false.
     */
    public function test_api_disabled_returns_no_api_mode(): void
    {
        config(['memory.api_enabled' => false]);

        $userId = 1;

        // No provider check needed when API is disabled
        $this->settingsService
            ->shouldReceive('getConfiguredProviders')
            ->never();

        $mode = $this->resolver->getOperatingMode($userId);

        $this->assertEquals('no-api', $mode);
    }

    /**
     * Test that getCapabilities() returns correct capabilities for 'no-api' mode.
     */
    public function test_get_capabilities_returns_correct_for_no_api_mode(): void
    {
        $userId = 1;

        $this->settingsService
            ->shouldReceive('getConfiguredProviders')
            ->with($userId)
            ->andReturn([]);

        $capabilities = $this->resolver->getCapabilities($userId);

        $this->assertEquals('no-api', $capabilities['mode']);
        $this->assertFalse($capabilities['embeddings']);
        $this->assertFalse($capabilities['entity_extraction']);
        $this->assertFalse($capabilities['summarization']);
        $this->assertTrue($capabilities['core_memory']);
        $this->assertTrue($capabilities['working_memory']);
        $this->assertTrue($capabilities['keyword_search']);
        $this->assertFalse($capabilities['semantic_search']);
        $this->assertFalse($capabilities['graph_search']);
    }

    /**
     * Test that getCapabilities() returns correct capabilities for 'api' mode.
     */
    public function test_get_capabilities_returns_correct_for_api_mode(): void
    {
        $userId = 1;

        $this->settingsService
            ->shouldReceive('getConfiguredProviders')
            ->with($userId)
            ->andReturn(['openai']);

        $capabilities = $this->resolver->getCapabilities($userId);

        $this->assertEquals('api', $capabilities['mode']);
        $this->assertTrue($capabilities['embeddings']);
        $this->assertTrue($capabilities['entity_extraction']);
        $this->assertTrue($capabilities['summarization']);
        $this->assertTrue($capabilities['core_memory']);
        $this->assertTrue($capabilities['working_memory']);
        $this->assertTrue($capabilities['keyword_search']);
        // Semantic search depends on pgvector (we'll mock/check separately)
        $this->assertArrayHasKey('semantic_search', $capabilities);
        // Graph search depends on Neo4j connectivity
        $this->assertArrayHasKey('graph_search', $capabilities);
    }

    /**
     * Test that getCapabilities() returns correct capabilities for 'degraded' mode.
     */
    public function test_get_capabilities_returns_correct_for_degraded_mode(): void
    {
        $userId = 1;

        $this->settingsService
            ->shouldReceive('getConfiguredProviders')
            ->with($userId)
            ->andReturn(['anthropic']);

        $capabilities = $this->resolver->getCapabilities($userId);

        $this->assertEquals('degraded', $capabilities['mode']);
        $this->assertFalse($capabilities['embeddings']); // Anthropic has no embeddings
        $this->assertTrue($capabilities['entity_extraction']);
        $this->assertTrue($capabilities['summarization']);
        $this->assertTrue($capabilities['core_memory']);
        $this->assertTrue($capabilities['working_memory']);
        $this->assertTrue($capabilities['keyword_search']);
        $this->assertFalse($capabilities['semantic_search']); // No embeddings means no semantic
        // Graph search may still work
        $this->assertArrayHasKey('graph_search', $capabilities);
    }

    /**
     * Test that hasEmbeddingCapability() returns true for OpenAI.
     */
    public function test_has_embedding_capability_returns_true_for_openai(): void
    {
        $userId = 1;

        $this->settingsService
            ->shouldReceive('getConfiguredProviders')
            ->with($userId)
            ->andReturn(['openai']);

        $this->assertTrue($this->resolver->hasEmbeddingCapability($userId));
    }

    /**
     * Test that hasEmbeddingCapability() returns false for Anthropic only.
     */
    public function test_has_embedding_capability_returns_false_for_anthropic_only(): void
    {
        $userId = 1;

        $this->settingsService
            ->shouldReceive('getConfiguredProviders')
            ->with($userId)
            ->andReturn(['anthropic']);

        $this->assertFalse($this->resolver->hasEmbeddingCapability($userId));
    }

    /**
     * Test that hasExtractionCapability() returns true when any provider configured.
     */
    public function test_has_extraction_capability_returns_true_when_any_provider_configured(): void
    {
        $userId = 1;

        $this->settingsService
            ->shouldReceive('getConfiguredProviders')
            ->with($userId)
            ->andReturn(['anthropic']);

        $this->assertTrue($this->resolver->hasExtractionCapability($userId));
    }

    /**
     * Test that hasExtractionCapability() returns false when no providers configured.
     */
    public function test_has_extraction_capability_returns_false_when_no_providers_configured(): void
    {
        $userId = 1;

        $this->settingsService
            ->shouldReceive('getConfiguredProviders')
            ->with($userId)
            ->andReturn([]);

        $this->assertFalse($this->resolver->hasExtractionCapability($userId));
    }

    /**
     * Test that getAvailableProviders() returns correct list.
     */
    public function test_get_available_providers_returns_correct_list(): void
    {
        $userId = 1;

        $this->settingsService
            ->shouldReceive('getConfiguredProviders')
            ->with($userId)
            ->andReturn(['openai', 'anthropic']);

        $providers = $this->resolver->getAvailableProviders($userId);

        $this->assertEquals(['openai', 'anthropic'], $providers);
    }

    /**
     * Test that getEmbeddingProviders() returns only embedding-capable providers.
     */
    public function test_get_embedding_providers_returns_only_embedding_capable(): void
    {
        $userId = 1;

        $this->settingsService
            ->shouldReceive('getConfiguredProviders')
            ->with($userId)
            ->andReturn(['openai', 'anthropic']);

        $providers = $this->resolver->getEmbeddingProviders($userId);

        $this->assertEquals(['openai'], $providers);
        $this->assertNotContains('anthropic', $providers);
    }

    /**
     * Test that getExtractionProviders() returns all configured providers.
     */
    public function test_get_extraction_providers_returns_all_configured(): void
    {
        $userId = 1;

        $this->settingsService
            ->shouldReceive('getConfiguredProviders')
            ->with($userId)
            ->andReturn(['openai', 'anthropic']);

        $providers = $this->resolver->getExtractionProviders($userId);

        $this->assertContains('openai', $providers);
        $this->assertContains('anthropic', $providers);
    }
}
