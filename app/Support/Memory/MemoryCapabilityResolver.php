<?php

declare(strict_types=1);

namespace App\Support\Memory;

use App\Support\Agent\FeatureFlagManager;

/**
 * Memory Capability Resolver.
 *
 * Determines the operating mode and available capabilities based on:
 * - Memory system enabled state (via FeatureFlagManager)
 * - API features enabled state (via FeatureFlagManager)
 * - Configured provider API keys
 * - Infrastructure availability (pgvector, Neo4j)
 *
 * Operating Modes:
 * - 'no-api': No provider keys or API disabled. Core Memory + Working Memory + BM25 only.
 * - 'api': OpenAI key configured. Full pipeline with embeddings, extraction, graph.
 * - 'degraded': Anthropic only. Text extraction enabled but no embeddings (BM25 + graph).
 */
class MemoryCapabilityResolver
{
    /**
     * Providers that support embedding generation.
     *
     * @var array<string>
     */
    private const EMBEDDING_PROVIDERS = ['openai'];

    /**
     * Providers that support text extraction/generation.
     *
     * @var array<string>
     */
    private const EXTRACTION_PROVIDERS = ['openai', 'anthropic'];

    public function __construct(
        private MemorySettingsService $settingsService,
        private FeatureFlagManager $featureFlags
    ) {}

    /**
     * Get the operating mode for a user.
     *
     * @param  int  $userId  User ID to check
     * @return string Operating mode: 'no-api', 'api', or 'degraded'
     */
    public function getOperatingMode(int $userId): string
    {
        // Check master toggles first (DB override → config fallback)
        if (! $this->featureFlags->enabled(FeatureFlagManager::MEMORY_ENABLED)) {
            return 'no-api';
        }

        if (! $this->featureFlags->enabled(FeatureFlagManager::MEMORY_API_ENABLED)) {
            return 'no-api';
        }

        // Check configured providers
        $providers = $this->settingsService->getConfiguredProviders($userId);

        if (empty($providers)) {
            return 'no-api';
        }

        // Check if any embedding-capable provider is configured
        $hasEmbeddingProvider = ! empty(array_intersect($providers, self::EMBEDDING_PROVIDERS));

        if ($hasEmbeddingProvider) {
            return 'api';
        }

        // Has providers but no embedding capability (e.g., Anthropic only)
        return 'degraded';
    }

    /**
     * Get full capability set for a user.
     *
     * @param  int  $userId  User ID to check
     * @return array<string, mixed> Capabilities map
     */
    public function getCapabilities(int $userId): array
    {
        $mode = $this->getOperatingMode($userId);

        // Core features always available when memory is enabled
        $coreEnabled = $this->featureFlags->enabled(FeatureFlagManager::MEMORY_ENABLED);

        return [
            'mode' => $mode,

            // Always available when memory enabled
            'core_memory' => $coreEnabled,
            'working_memory' => $coreEnabled,
            'keyword_search' => $coreEnabled, // BM25 via PostgreSQL

            // API-dependent features
            'embeddings' => $mode === 'api',
            'entity_extraction' => in_array($mode, ['api', 'degraded']),
            'summarization' => in_array($mode, ['api', 'degraded']),

            // Infrastructure-dependent features
            'semantic_search' => $mode === 'api' && $this->isPgVectorAvailable(),
            'graph_search' => in_array($mode, ['api', 'degraded']) && $this->isNeo4jAvailable(),

            // Provider info
            'configured_providers' => $coreEnabled ? $this->settingsService->getConfiguredProviders($userId) : [],
        ];
    }

    /**
     * Check if user has embedding capability.
     *
     * @param  int  $userId  User ID to check
     * @return bool True if embeddings are available
     */
    public function hasEmbeddingCapability(int $userId): bool
    {
        if (! $this->featureFlags->enabled(FeatureFlagManager::MEMORY_ENABLED)
            || ! $this->featureFlags->enabled(FeatureFlagManager::MEMORY_API_ENABLED)) {
            return false;
        }

        return ! empty($this->getEmbeddingProviders($userId));
    }

    /**
     * Check if user has extraction capability.
     *
     * @param  int  $userId  User ID to check
     * @return bool True if extraction is available
     */
    public function hasExtractionCapability(int $userId): bool
    {
        if (! $this->featureFlags->enabled(FeatureFlagManager::MEMORY_ENABLED)
            || ! $this->featureFlags->enabled(FeatureFlagManager::MEMORY_API_ENABLED)) {
            return false;
        }

        return ! empty($this->getExtractionProviders($userId));
    }

    /**
     * Get all configured providers for a user.
     *
     * @param  int  $userId  User ID to check
     * @return array<string> List of configured provider names
     */
    public function getAvailableProviders(int $userId): array
    {
        return $this->settingsService->getConfiguredProviders($userId);
    }

    /**
     * Get providers that support embeddings.
     *
     * @param  int  $userId  User ID to check
     * @return array<string> List of embedding-capable providers
     */
    public function getEmbeddingProviders(int $userId): array
    {
        $configured = $this->settingsService->getConfiguredProviders($userId);

        return array_values(array_intersect($configured, self::EMBEDDING_PROVIDERS));
    }

    /**
     * Get providers that support text extraction.
     *
     * @param  int  $userId  User ID to check
     * @return array<string> List of extraction-capable providers
     */
    public function getExtractionProviders(int $userId): array
    {
        $configured = $this->settingsService->getConfiguredProviders($userId);

        return array_values(array_intersect($configured, self::EXTRACTION_PROVIDERS));
    }

    /**
     * Check if pgvector extension is available.
     *
     * Queries PostgreSQL to check for the vector extension.
     * Result is cached for the request lifecycle.
     *
     * @return bool True if pgvector is available
     */
    public function isPgVectorAvailable(): bool
    {
        static $available = null;

        if ($available !== null) {
            return $available;
        }

        try {
            $result = \DB::select("SELECT 1 FROM pg_extension WHERE extname = 'vector'");
            $available = ! empty($result);
        } catch (\Throwable) {
            $available = false;
        }

        return $available;
    }

    /**
     * Check if Neo4j is available.
     *
     * Attempts a lightweight connection test.
     * Result is cached for the request lifecycle.
     *
     * @return bool True if Neo4j is reachable
     */
    public function isNeo4jAvailable(): bool
    {
        static $available = null;

        if ($available !== null) {
            return $available;
        }

        try {
            $host = config('memory.neo4j.host', 'localhost');
            $port = config('memory.neo4j.port', 7687);

            // Simple socket connection test (faster than full Bolt handshake)
            $socket = @fsockopen($host, $port, $errno, $errstr, 2);

            if ($socket !== false) {
                fclose($socket);
                $available = true;
            } else {
                $available = false;
            }
        } catch (\Throwable) {
            $available = false;
        }

        return $available;
    }

    /**
     * Reset cached infrastructure checks.
     *
     * Useful for testing or after configuration changes.
     */
    public function resetCache(): void
    {
        // Static variables will be reset on next check
        // This is a placeholder for future implementation if needed
    }
}
