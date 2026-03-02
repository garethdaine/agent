<?php

declare(strict_types=1);

namespace Tests\Unit\Memory;

use App\Support\Memory\Adapters\AnthropicAdapter;
use App\Support\Memory\Adapters\OpenAIAdapter;
use App\Support\Memory\Contracts\EmbeddingProvider;
use App\Support\Memory\Contracts\ExtractionProvider;
use App\Support\Memory\MemoryAdapterFactory;
use App\Support\Memory\MemoryCapabilityResolver;
use App\Support\Memory\MemorySettingsService;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * Tests for MemoryAdapterFactory adapter resolution.
 *
 * Verifies:
 * - makeEmbeddingProvider() returns OpenAIAdapter when configured
 * - makeEmbeddingProvider() returns null when only Anthropic configured
 * - makeExtractionProvider() returns configured provider
 * - Failover works when primary fails
 * - Adapters are cached per request
 */
class MemoryAdapterFactoryTest extends TestCase
{
    private MemorySettingsService&MockInterface $settingsService;

    private MemoryCapabilityResolver&MockInterface $capabilityResolver;

    private MemoryAdapterFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();

        // Enable memory for these tests
        config(['memory.enabled' => true]);
        config(['memory.api_enabled' => true]);

        $this->settingsService = Mockery::mock(MemorySettingsService::class);
        $this->capabilityResolver = Mockery::mock(MemoryCapabilityResolver::class);

        $this->factory = new MemoryAdapterFactory(
            $this->settingsService,
            $this->capabilityResolver
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Test that makeEmbeddingProvider() returns OpenAIAdapter when OpenAI is configured.
     */
    public function test_make_embedding_provider_returns_openai_adapter_when_configured(): void
    {
        $userId = 1;

        $this->capabilityResolver
            ->shouldReceive('getEmbeddingProviders')
            ->with($userId)
            ->andReturn(['openai']);

        // Mock all possible get() calls with flexible matching
        $this->settingsService
            ->shouldReceive('get')
            ->andReturnUsing(function ($uid, $key, $default = null) {
                return match ($key) {
                    'provider_key_openai' => 'sk-test-key',
                    'extraction_model' => 'gpt-4o-mini',
                    'summarization_model' => 'gpt-4.1-nano',
                    'embeddings_model' => 'text-embedding-3-small',
                    default => $default,
                };
            });

        $provider = $this->factory->makeEmbeddingProvider($userId);

        $this->assertNotNull($provider);
        $this->assertInstanceOf(EmbeddingProvider::class, $provider);
        $this->assertInstanceOf(OpenAIAdapter::class, $provider);
    }

    /**
     * Test that makeEmbeddingProvider() returns null when only Anthropic is configured.
     */
    public function test_make_embedding_provider_returns_null_when_only_anthropic_configured(): void
    {
        $userId = 1;

        $this->capabilityResolver
            ->shouldReceive('getEmbeddingProviders')
            ->with($userId)
            ->andReturn([]); // Anthropic is not in embedding providers

        $provider = $this->factory->makeEmbeddingProvider($userId);

        $this->assertNull($provider);
    }

    /**
     * Test that makeEmbeddingProvider() returns null when no providers configured.
     */
    public function test_make_embedding_provider_returns_null_when_no_providers_configured(): void
    {
        $userId = 1;

        $this->capabilityResolver
            ->shouldReceive('getEmbeddingProviders')
            ->with($userId)
            ->andReturn([]);

        $provider = $this->factory->makeEmbeddingProvider($userId);

        $this->assertNull($provider);
    }

    /**
     * Test that makeExtractionProvider() returns OpenAIAdapter when OpenAI is primary.
     */
    public function test_make_extraction_provider_returns_openai_adapter_when_primary(): void
    {
        $userId = 1;

        $this->capabilityResolver
            ->shouldReceive('getExtractionProviders')
            ->with($userId)
            ->andReturn(['openai']);

        $this->settingsService
            ->shouldReceive('get')
            ->andReturnUsing(function ($uid, $key, $default = null) {
                return match ($key) {
                    'extraction_provider' => 'openai',
                    'provider_key_openai' => 'sk-test-key',
                    'extraction_model' => 'gpt-4o-mini',
                    'summarization_model' => 'gpt-4.1-nano',
                    'embeddings_model' => 'text-embedding-3-small',
                    default => $default,
                };
            });

        $provider = $this->factory->makeExtractionProvider($userId);

        $this->assertNotNull($provider);
        $this->assertInstanceOf(ExtractionProvider::class, $provider);
        $this->assertInstanceOf(OpenAIAdapter::class, $provider);
    }

    /**
     * Test that makeExtractionProvider() returns AnthropicAdapter when Anthropic is primary.
     */
    public function test_make_extraction_provider_returns_anthropic_adapter_when_primary(): void
    {
        $userId = 1;

        $this->capabilityResolver
            ->shouldReceive('getExtractionProviders')
            ->with($userId)
            ->andReturn(['anthropic']);

        $this->settingsService
            ->shouldReceive('get')
            ->andReturnUsing(function ($uid, $key, $default = null) {
                return match ($key) {
                    'extraction_provider' => 'anthropic',
                    'provider_key_anthropic' => 'sk-ant-test-key',
                    'extraction_model' => 'claude-3-haiku-20240307',
                    'summarization_model' => 'claude-3-haiku-20240307',
                    default => $default,
                };
            });

        $provider = $this->factory->makeExtractionProvider($userId);

        $this->assertNotNull($provider);
        $this->assertInstanceOf(ExtractionProvider::class, $provider);
        $this->assertInstanceOf(AnthropicAdapter::class, $provider);
    }

    /**
     * Test that makeExtractionProvider() returns null when no providers configured.
     */
    public function test_make_extraction_provider_returns_null_when_no_providers_configured(): void
    {
        $userId = 1;

        $this->capabilityResolver
            ->shouldReceive('getExtractionProviders')
            ->with($userId)
            ->andReturn([]);

        $provider = $this->factory->makeExtractionProvider($userId);

        $this->assertNull($provider);
    }

    /**
     * Test that makeSummarizationProvider() returns correct provider.
     */
    public function test_make_summarization_provider_returns_correct_provider(): void
    {
        $userId = 1;

        $this->capabilityResolver
            ->shouldReceive('getExtractionProviders')
            ->with($userId)
            ->andReturn(['openai']);

        $this->settingsService
            ->shouldReceive('get')
            ->andReturnUsing(function ($uid, $key, $default = null) {
                return match ($key) {
                    'summarization_provider' => 'openai',
                    'provider_key_openai' => 'sk-test-key',
                    'extraction_model' => 'gpt-4o-mini',
                    'summarization_model' => 'gpt-4.1-nano',
                    'embeddings_model' => 'text-embedding-3-small',
                    default => $default,
                };
            });

        $provider = $this->factory->makeSummarizationProvider($userId);

        $this->assertNotNull($provider);
        $this->assertInstanceOf(ExtractionProvider::class, $provider);
    }

    /**
     * Test that getFailoverProvider() returns alternative provider.
     */
    public function test_get_failover_provider_returns_alternative(): void
    {
        $userId = 1;

        $this->capabilityResolver
            ->shouldReceive('getExtractionProviders')
            ->with($userId)
            ->andReturn(['openai', 'anthropic']);

        $this->settingsService
            ->shouldReceive('get')
            ->andReturnUsing(function ($uid, $key, $default = null) {
                return match ($key) {
                    'provider_key_anthropic' => 'sk-ant-test-key',
                    'extraction_model' => 'claude-3-haiku-20240307',
                    'summarization_model' => 'claude-3-haiku-20240307',
                    default => $default,
                };
            });

        $failover = $this->factory->getFailoverProvider($userId, 'openai');

        $this->assertNotNull($failover);
        $this->assertInstanceOf(ExtractionProvider::class, $failover);
        $this->assertInstanceOf(AnthropicAdapter::class, $failover);
    }

    /**
     * Test that getFailoverProvider() returns null when no alternative exists.
     */
    public function test_get_failover_provider_returns_null_when_no_alternative(): void
    {
        $userId = 1;

        $this->capabilityResolver
            ->shouldReceive('getExtractionProviders')
            ->with($userId)
            ->andReturn(['openai']);

        $failover = $this->factory->getFailoverProvider($userId, 'openai');

        $this->assertNull($failover);
    }

    /**
     * Test that adapters are cached per request.
     */
    public function test_adapters_are_cached_per_request(): void
    {
        $userId = 1;

        $callCount = 0;

        $this->capabilityResolver
            ->shouldReceive('getEmbeddingProviders')
            ->with($userId)
            ->andReturnUsing(function () use (&$callCount) {
                $callCount++;

                return ['openai'];
            });

        $this->settingsService
            ->shouldReceive('get')
            ->andReturnUsing(function ($uid, $key, $default = null) {
                return match ($key) {
                    'provider_key_openai' => 'sk-test-key',
                    'extraction_model' => 'gpt-4o-mini',
                    'summarization_model' => 'gpt-4.1-nano',
                    'embeddings_model' => 'text-embedding-3-small',
                    default => $default,
                };
            });

        $provider1 = $this->factory->makeEmbeddingProvider($userId);
        $provider2 = $this->factory->makeEmbeddingProvider($userId);

        // Should only call getEmbeddingProviders once due to caching
        $this->assertEquals(1, $callCount);
        $this->assertSame($provider1, $provider2);
    }

    /**
     * Test that clearCache() clears adapter cache.
     */
    public function test_clear_cache_clears_adapter_cache(): void
    {
        $userId = 1;

        $callCount = 0;

        $this->capabilityResolver
            ->shouldReceive('getEmbeddingProviders')
            ->with($userId)
            ->andReturnUsing(function () use (&$callCount) {
                $callCount++;

                return ['openai'];
            });

        $this->settingsService
            ->shouldReceive('get')
            ->andReturnUsing(function ($uid, $key, $default = null) {
                return match ($key) {
                    'provider_key_openai' => 'sk-test-key',
                    'extraction_model' => 'gpt-4o-mini',
                    'summarization_model' => 'gpt-4.1-nano',
                    'embeddings_model' => 'text-embedding-3-small',
                    default => $default,
                };
            });

        $provider1 = $this->factory->makeEmbeddingProvider($userId);

        $this->factory->clearCache();

        $provider2 = $this->factory->makeEmbeddingProvider($userId);

        // Should call getEmbeddingProviders twice after cache clear
        $this->assertEquals(2, $callCount);
        // After cache clear, a new instance should be created
        $this->assertNotSame($provider1, $provider2);
    }

    /**
     * Test that supportsEmbeddings() returns correct value based on provider.
     */
    public function test_supports_embeddings_returns_correct_value(): void
    {
        $this->assertTrue($this->factory->providerSupportsEmbeddings('openai'));
        $this->assertFalse($this->factory->providerSupportsEmbeddings('anthropic'));
        $this->assertFalse($this->factory->providerSupportsEmbeddings('unknown'));
    }

    /**
     * Test that supportsExtraction() returns correct value based on provider.
     */
    public function test_supports_extraction_returns_correct_value(): void
    {
        $this->assertTrue($this->factory->providerSupportsExtraction('openai'));
        $this->assertTrue($this->factory->providerSupportsExtraction('anthropic'));
        $this->assertFalse($this->factory->providerSupportsExtraction('unknown'));
    }
}
