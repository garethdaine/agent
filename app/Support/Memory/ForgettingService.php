<?php

declare(strict_types=1);

namespace App\Support\Memory;

use App\Models\MemoryConsolidationLog;
use App\Models\MemoryEmbedding;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

/**
 * Forgetting Service for Memory System.
 *
 * Implements tiered pruning with configurable thresholds:
 * - Working Memory: Redis TTL (2 hours post terminal status)
 * - Vector embeddings: Composite decay scoring, prune below 0.1 threshold
 * - Graph entities: Soft-delete below 0.2 importance after 90-day retention
 * - Conversation logs: Unlimited retention by default (user-managed)
 *
 * Default mode is dry-run (preview only). Use --force to actually delete.
 */
class ForgettingService
{
    public function __construct(
        private readonly Neo4jGraphStore $graphStore,
    ) {}

    /**
     * Run pruning operation.
     *
     * @param  int|null  $userId  Scope to specific user, null for all users
     * @param  bool  $dryRun  If true, only report what would be pruned (default: true)
     * @return array<string, mixed> Pruning results
     */
    public function prune(?int $userId = null, bool $dryRun = true): array
    {
        if (! config('memory.enabled', false)) {
            return ['skipped' => true, 'reason' => 'Memory system disabled'];
        }

        $results = [
            'dry_run' => $dryRun,
            'embeddings_pruned' => 0,
            'embeddings_would_prune' => 0,
            'graph_entities_pruned' => 0,
            'graph_entities_would_prune' => 0,
            'working_memory_expired' => 0,
            'candidates' => [],
        ];

        // Prune embeddings below decay threshold
        $embeddingResults = $this->pruneEmbeddings($userId, $dryRun);
        $results = array_merge($results, $embeddingResults);

        // Prune graph entities below importance threshold after retention period
        $graphResults = $this->pruneGraphEntities($userId, $dryRun);
        $results = array_merge($results, $graphResults);

        // Report on working memory (TTL is automatic via Redis)
        $results['working_memory_expired'] = $this->countExpiredWorkingMemory();

        // Log the prune operation
        if (! $dryRun) {
            $this->logPruneOperation($userId, $results);
        }

        return $results;
    }

    /**
     * Prune embeddings below decay threshold.
     *
     * Decay score formula: importance × recency_decay × access_frequency_bonus
     *
     * @param  int|null  $userId  Scope to specific user
     * @param  bool  $dryRun  Only report what would be pruned
     * @return array<string, mixed> Embedding pruning results
     */
    private function pruneEmbeddings(?int $userId, bool $dryRun): array
    {
        $threshold = (float) config('memory.forgetting.embedding_decay_threshold', 0.1);

        $query = MemoryEmbedding::query();

        if ($userId !== null) {
            $query->forUser($userId);
        }

        $embeddings = $query->get();

        $toPrune = [];
        foreach ($embeddings as $embedding) {
            $decayScore = $embedding->calculateDecayScore();

            if ($decayScore < $threshold) {
                $toPrune[] = [
                    'id' => $embedding->id,
                    'content_preview' => mb_substr($embedding->content, 0, 50).'...',
                    'decay_score' => round($decayScore, 4),
                    'importance_score' => $embedding->importance_score,
                    'last_accessed' => $embedding->last_accessed_at?->toDateTimeString(),
                    'created_at' => $embedding->created_at?->toDateTimeString(),
                ];
            }
        }

        if ($dryRun) {
            return [
                'embeddings_would_prune' => count($toPrune),
                'candidates' => $toPrune,
            ];
        }

        // Actually delete
        $deleted = 0;
        foreach ($toPrune as $candidate) {
            $embedding = MemoryEmbedding::find($candidate['id']);
            if ($embedding) {
                $embedding->delete();
                $deleted++;
            }
        }

        Log::info('ForgettingService: Pruned embeddings', [
            'user_id' => $userId,
            'deleted' => $deleted,
            'threshold' => $threshold,
        ]);

        return [
            'embeddings_pruned' => $deleted,
        ];
    }

    /**
     * Prune graph entities below importance threshold after retention period.
     *
     * @param  int|null  $userId  Scope to specific user
     * @param  bool  $dryRun  Only report what would be pruned
     * @return array<string, mixed> Graph pruning results
     */
    private function pruneGraphEntities(?int $userId, bool $dryRun): array
    {
        $threshold = (float) config('memory.forgetting.graph_importance_threshold', 0.2);
        $retentionDays = (int) config('memory.forgetting.graph_retention_days', 90);

        // Check if Neo4j is available
        if (! $this->graphStore->healthCheck()) {
            return [
                'graph_entities_pruned' => 0,
                'graph_entities_would_prune' => 0,
                'graph_skipped' => true,
                'graph_reason' => 'Neo4j not available',
            ];
        }

        try {
            $result = $this->graphStore->pruneEntities(
                $userId,
                $threshold,
                $retentionDays,
                $dryRun
            );

            if ($dryRun) {
                return [
                    'graph_entities_would_prune' => $result['would_prune'] ?? 0,
                ];
            }

            return [
                'graph_entities_pruned' => $result['pruned'] ?? 0,
            ];
        } catch (\Throwable $e) {
            Log::warning('ForgettingService: Graph prune failed', [
                'error' => $e->getMessage(),
            ]);

            return [
                'graph_entities_pruned' => 0,
                'graph_entities_would_prune' => 0,
                'graph_skipped' => true,
                'graph_reason' => $e->getMessage(),
            ];
        }
    }

    /**
     * Count expired working memory keys (informational only, Redis handles TTL).
     */
    private function countExpiredWorkingMemory(): int
    {
        // Working memory uses Redis TTL which is automatic
        // This just provides visibility into Redis key patterns
        try {
            $prefix = config('memory.working_memory.key_prefix', 'memory:run:');
            $keys = Redis::connection('memory')->keys($prefix.'*');

            // These are active keys - expired ones are automatically removed
            return 0; // Redis handles TTL automatically
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * Log the prune operation to consolidation log.
     *
     * @param  array<string, mixed>  $results
     */
    private function logPruneOperation(?int $userId, array $results): void
    {
        $totalPruned = ($results['embeddings_pruned'] ?? 0) + ($results['graph_entities_pruned'] ?? 0);

        if ($totalPruned > 0) {
            MemoryConsolidationLog::record(
                $userId,
                MemoryConsolidationLog::TYPE_PRUNE,
                $totalPruned,
                sprintf(
                    'Pruned %d embeddings, %d graph entities',
                    $results['embeddings_pruned'] ?? 0,
                    $results['graph_entities_pruned'] ?? 0
                ),
                [
                    'embeddings_pruned' => $results['embeddings_pruned'] ?? 0,
                    'graph_entities_pruned' => $results['graph_entities_pruned'] ?? 0,
                ]
            );
        }
    }
}
