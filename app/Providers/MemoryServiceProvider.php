<?php

declare(strict_types=1);

namespace App\Providers;

use App\Support\Agent\AuditLogger;
use App\Support\Agent\FeatureFlagManager;
use App\Support\Memory\Adapters\NullEmbeddingProvider;
use App\Support\Memory\ConsolidationService;
use App\Support\Memory\Contracts\EmbeddingProvider;
use App\Support\Memory\CoreMemoryManager;
use App\Support\Memory\ForgettingService;
use App\Support\Memory\HybridRetriever;
use App\Support\Memory\MemoryAdapterFactory;
use App\Support\Memory\MemoryCapabilityResolver;
use App\Support\Memory\MemoryFormationPipeline;
use App\Support\Memory\MemorySettingsService;
use App\Support\Memory\Neo4jGraphStore;
use App\Support\Memory\WorkingMemoryBuffer;
use App\Support\Memory\WorkingMemorySummarizer;
use Illuminate\Support\ServiceProvider;

/**
 * Memory Service Provider.
 *
 * Registers all memory system services with FeatureFlagManager-gated bindings.
 * When memory is not enabled (via DB override or config), services throw clear errors.
 *
 * The flag check is deferred to resolution time (not registration time)
 * to allow tests to configure flags before resolving services.
 */
class MemoryServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../../config/memory.php',
            'memory'
        );

        // Register services with deferred flag check at resolution time.
        // This allows tests to set flags before resolving.
        $this->app->singleton(CoreMemoryManager::class, function ($app) {
            $this->ensureMemoryEnabled($app);

            return new CoreMemoryManager(
                $app->make(AuditLogger::class)
            );
        });

        // MemorySettingsService is always available — users need to configure
        // provider keys and view capabilities even before enabling memory.
        $this->app->singleton(MemorySettingsService::class, function ($app) {
            return new MemorySettingsService(
                $app->make(\App\Services\Credentials\CredentialsManager::class)
            );
        });

        // WorkingMemoryBuffer: Redis-backed sorted set for short-term conversational state.
        // Fire-and-forget design - no config check needed since operations are silent.
        $this->app->singleton(WorkingMemoryBuffer::class, function ($app) {
            $this->ensureMemoryEnabled($app);

            return new WorkingMemoryBuffer;
        });

        // WorkingMemorySummarizer: Stub for API mode eviction summarization.
        $this->app->singleton(WorkingMemorySummarizer::class, function ($app) {
            $this->ensureMemoryEnabled($app);

            return new WorkingMemorySummarizer;
        });

        // Neo4jGraphStore: Knowledge graph storage with MERGE-based idempotent operations.
        $this->app->singleton(Neo4jGraphStore::class, function () {
            return new Neo4jGraphStore;
        });

        // ConsolidationService: Backfill retries and vector deduplication.
        $this->app->singleton(ConsolidationService::class, function ($app) {
            return new ConsolidationService(
                $app->make(MemoryFormationPipeline::class)
            );
        });

        // ForgettingService: Tiered pruning with configurable thresholds.
        $this->app->singleton(ForgettingService::class, function ($app) {
            return new ForgettingService(
                $app->make(Neo4jGraphStore::class)
            );
        });

        // MemoryCapabilityResolver: Determines operating mode and capabilities.
        $this->app->singleton(MemoryCapabilityResolver::class, function ($app) {
            return new MemoryCapabilityResolver(
                $app->make(MemorySettingsService::class),
                $app->make(FeatureFlagManager::class)
            );
        });

        // MemoryAdapterFactory: Resolves user-specific provider adapters based on
        // configured API keys and preferences stored via MemorySettingsService.
        $this->app->singleton(MemoryAdapterFactory::class, function ($app) {
            return new MemoryAdapterFactory(
                $app->make(MemorySettingsService::class),
                $app->make(MemoryCapabilityResolver::class)
            );
        });

        // MemoryFormationPipeline: Orchestrates long-term memory formation.
        // Receives the MemoryAdapterFactory to resolve user-specific providers at runtime.
        $this->app->singleton(MemoryFormationPipeline::class, function ($app) {
            return new MemoryFormationPipeline(
                $app->make(WorkingMemoryBuffer::class),
                null, // ExtractionProvider resolved per-user via factory
                null, // EmbeddingProvider resolved per-user via factory
                $app->make(Neo4jGraphStore::class),
                $app->make(MemoryAdapterFactory::class)
            );
        });

        // EmbeddingProvider: Default to NullEmbeddingProvider for non-pipeline consumers.
        // The MemoryFormationPipeline resolves real adapters per-user via MemoryAdapterFactory.
        $this->app->singleton(EmbeddingProvider::class, function () {
            return new NullEmbeddingProvider;
        });

        // HybridRetriever: Three-source retrieval with RRF fusion.
        $this->app->singleton(HybridRetriever::class, function ($app) {
            return new HybridRetriever(
                $app->make(EmbeddingProvider::class),
                $app->make(Neo4jGraphStore::class)
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../../config/memory.php' => config_path('memory.php'),
            ], 'memory-config');
        }
    }

    /**
     * Ensure memory is enabled via FeatureFlagManager before resolving a service.
     *
     * @throws \RuntimeException when memory is disabled
     */
    private function ensureMemoryEnabled($app): void
    {
        $featureFlags = $app->make(FeatureFlagManager::class);

        if (! $featureFlags->enabled(FeatureFlagManager::MEMORY_ENABLED)) {
            throw new \RuntimeException(
                'Memory system is disabled. Enable the "Agent Memory" feature flag or set MEMORY_ENABLED=true.'
            );
        }
    }
}
