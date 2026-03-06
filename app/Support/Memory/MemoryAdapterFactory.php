<?php

declare(strict_types=1);

namespace App\Support\Memory;

use App\Support\Memory\Adapters\AnthropicAdapter;
use App\Support\Memory\Adapters\OpenAIAdapter;
use App\Support\Memory\Contracts\EmbeddingProvider;
use App\Support\Memory\Contracts\ExtractionProvider;

/**
 * Memory Adapter Factory.
 *
 * Resolves and caches provider adapters based on user settings.
 *
 * Features:
 * - Auto-detection of configured providers
 * - Failover chain for text extraction (OpenAI <-> Anthropic)
 * - Embeddings only via embedding-capable providers
 * - Request-scoped caching of resolved adapters
 *
 * Usage:
 *   $factory->makeEmbeddingProvider($userId);  // Returns OpenAIAdapter or null
 *   $factory->makeExtractionProvider($userId); // Returns configured provider
 *   $factory->getFailoverProvider($userId, 'openai'); // Returns Anthropic
 */
class MemoryAdapterFactory
{
    /**
     * Providers that support embedding generation.
     */
    private const EMBEDDING_PROVIDERS = ['openai'];

    /**
     * Providers that support text extraction/generation.
     */
    private const EXTRACTION_PROVIDERS = ['openai', 'anthropic'];

    /**
     * Cached adapter instances per user.
     *
     * @var array<string, EmbeddingProvider|ExtractionProvider|null>
     */
    private array $cache = [];

    public function __construct(
        private MemorySettingsService $settingsService,
        private MemoryCapabilityResolver $capabilityResolver
    ) {}

    /**
     * Create or retrieve an embedding provider for a user.
     *
     * Returns null if no embedding-capable provider is configured.
     *
     * @param  int  $userId  User ID
     * @return EmbeddingProvider|null Embedding provider or null
     */
    public function makeEmbeddingProvider(int $userId): ?EmbeddingProvider
    {
        $cacheKey = "embedding:{$userId}";

        if (array_key_exists($cacheKey, $this->cache)) {
            return $this->cache[$cacheKey];
        }

        $providers = $this->capabilityResolver->getEmbeddingProviders($userId);

        if (empty($providers)) {
            $this->cache[$cacheKey] = null;

            return null;
        }

        // Use first available embedding provider (OpenAI is currently the only one)
        $provider = $providers[0];
        $adapter = $this->createAdapter($userId, $provider);

        if ($adapter instanceof EmbeddingProvider) {
            $this->cache[$cacheKey] = $adapter;

            return $adapter;
        }

        $this->cache[$cacheKey] = null;

        return null;
    }

    /**
     * Create or retrieve an extraction provider for a user.
     *
     * Uses the user's configured extraction_provider setting,
     * falling back to the first available provider.
     *
     * @param  int  $userId  User ID
     * @return ExtractionProvider|null Extraction provider or null
     */
    public function makeExtractionProvider(int $userId): ?ExtractionProvider
    {
        $cacheKey = "extraction:{$userId}";

        if (array_key_exists($cacheKey, $this->cache)) {
            return $this->cache[$cacheKey];
        }

        $providers = $this->capabilityResolver->getExtractionProviders($userId);

        if (empty($providers)) {
            $this->cache[$cacheKey] = null;

            return null;
        }

        // Check user's preferred provider
        $preferred = $this->settingsService->get($userId, 'extraction_provider');

        if ($preferred !== null && in_array($preferred, $providers, true)) {
            $adapter = $this->createAdapter($userId, $preferred);

            if ($adapter instanceof ExtractionProvider) {
                $this->cache[$cacheKey] = $adapter;

                return $adapter;
            }
        }

        // Fall back to first available
        $adapter = $this->createAdapter($userId, $providers[0]);

        if ($adapter instanceof ExtractionProvider) {
            $this->cache[$cacheKey] = $adapter;

            return $adapter;
        }

        $this->cache[$cacheKey] = null;

        return null;
    }

    /**
     * Create or retrieve a summarization provider for a user.
     *
     * Uses the user's configured summarization_provider setting,
     * falling back to extraction provider.
     *
     * @param  int  $userId  User ID
     * @return ExtractionProvider|null Summarization provider or null
     */
    public function makeSummarizationProvider(int $userId): ?ExtractionProvider
    {
        $cacheKey = "summarization:{$userId}";

        if (array_key_exists($cacheKey, $this->cache)) {
            return $this->cache[$cacheKey];
        }

        $providers = $this->capabilityResolver->getExtractionProviders($userId);

        if (empty($providers)) {
            $this->cache[$cacheKey] = null;

            return null;
        }

        // Check user's preferred summarization provider
        $preferred = $this->settingsService->get($userId, 'summarization_provider');

        if ($preferred !== null && in_array($preferred, $providers, true)) {
            $adapter = $this->createAdapter($userId, $preferred);

            if ($adapter instanceof ExtractionProvider) {
                $this->cache[$cacheKey] = $adapter;

                return $adapter;
            }
        }

        // Fall back to extraction provider
        return $this->makeExtractionProvider($userId);
    }

    /**
     * Get a failover provider for text extraction.
     *
     * Returns an alternative provider when the primary fails.
     * Only works between extraction-capable providers.
     *
     * @param  int  $userId  User ID
     * @param  string  $excludeProvider  Provider that failed (to exclude)
     * @return ExtractionProvider|null Failover provider or null
     */
    public function getFailoverProvider(int $userId, string $excludeProvider): ?ExtractionProvider
    {
        $providers = $this->capabilityResolver->getExtractionProviders($userId);

        // Filter out the failed provider
        $alternatives = array_filter(
            $providers,
            fn ($p) => $p !== $excludeProvider
        );

        if (empty($alternatives)) {
            return null;
        }

        $failoverProvider = array_values($alternatives)[0];
        $adapter = $this->createAdapter($userId, $failoverProvider);

        if ($adapter instanceof ExtractionProvider) {
            return $adapter;
        }

        return null;
    }

    /**
     * Clear the adapter cache.
     *
     * Call this when user settings change.
     */
    public function clearCache(): void
    {
        $this->cache = [];
    }

    /**
     * Check if a provider supports embeddings.
     *
     * @param  string  $provider  Provider name
     * @return bool True if provider supports embeddings
     */
    public function providerSupportsEmbeddings(string $provider): bool
    {
        return in_array($provider, self::EMBEDDING_PROVIDERS, true);
    }

    /**
     * Check if a provider supports text extraction.
     *
     * @param  string  $provider  Provider name
     * @return bool True if provider supports extraction
     */
    public function providerSupportsExtraction(string $provider): bool
    {
        return in_array($provider, self::EXTRACTION_PROVIDERS, true);
    }

    /**
     * Create an adapter instance for a provider.
     *
     * API keys are resolved from the credential manager via MemorySettingsService.
     *
     * @param  int  $userId  User ID for settings lookup
     * @param  string  $provider  Provider name
     * @return OpenAIAdapter|AnthropicAdapter|null Adapter instance or null
     */
    private function createAdapter(int $userId, string $provider): OpenAIAdapter|AnthropicAdapter|null
    {
        $apiKey = $this->settingsService->getProviderKey($userId, $provider);

        if ($apiKey === null || $apiKey === '') {
            return null;
        }

        return match ($provider) {
            'openai' => $this->createOpenAIAdapter($userId, $apiKey),
            'anthropic' => $this->createAnthropicAdapter($userId, $apiKey),
            default => null,
        };
    }

    /**
     * Create an OpenAI adapter with user's model settings.
     *
     * @param  int  $userId  User ID for settings lookup
     * @param  string  $apiKey  OpenAI API key
     * @return OpenAIAdapter Configured adapter
     */
    private function createOpenAIAdapter(int $userId, string $apiKey): OpenAIAdapter
    {
        $extractionModel = $this->settingsService->get(
            $userId,
            'extraction_model',
            config('memory.models.extraction.default', 'gpt-4o-mini')
        );

        $summarizationModel = $this->settingsService->get(
            $userId,
            'summarization_model',
            config('memory.models.summarization.default', 'gpt-4.1-nano')
        );

        $embeddingsModel = $this->settingsService->get(
            $userId,
            'embeddings_model',
            config('memory.models.embeddings.default', 'text-embedding-3-small')
        );

        return new OpenAIAdapter(
            $apiKey,
            $extractionModel,
            $summarizationModel,
            $embeddingsModel
        );
    }

    /**
     * Create an Anthropic adapter with user's model settings.
     *
     * @param  int  $userId  User ID for settings lookup
     * @param  string  $apiKey  Anthropic API key
     * @return AnthropicAdapter Configured adapter
     */
    private function createAnthropicAdapter(int $userId, string $apiKey): AnthropicAdapter
    {
        $extractionModel = $this->settingsService->get(
            $userId,
            'extraction_model',
            config('memory.models.extraction.fallback', 'claude-3-haiku-20240307')
        );

        $summarizationModel = $this->settingsService->get(
            $userId,
            'summarization_model',
            config('memory.models.summarization.fallback', 'claude-3-haiku-20240307')
        );

        return new AnthropicAdapter(
            $apiKey,
            $extractionModel,
            $summarizationModel
        );
    }
}
