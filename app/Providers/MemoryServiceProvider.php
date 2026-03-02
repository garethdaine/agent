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
        $this->app->singleton(MemorySettingsService::class, function () {
            return new MemorySettingsService;
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

        // EmbeddingProvider: Default to NullEmbeddingProvider when no keys configured.
        // Real adapters can be substituted by MemoryAdapterFactory based on user settings.
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
