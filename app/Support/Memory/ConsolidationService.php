<?php

declare(strict_types=1);

namespace App\Support\Memory;

use App\Models\AgentJobRun;
use App\Models\MemoryConsolidationLog;
use App\Models\MemoryEmbedding;
use App\Models\MemoryFormationFailure;
use App\Support\Agent\FeatureFlagManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Consolidation Service for Memory System.
 *
 * Handles scheduled consolidation operations:
 * - Backfill retry: Retries failed memory formations up to 5 cycles
 * - Vector deduplication: Identifies and merges near-duplicate embeddings
 * - Checkpoint support: Resumable processing for large batches
 *
 * Consolidation runs every 2 hours when memory.enabled is true.
 */
class ConsolidationService
{
    /**
     * Maximum failures to process per consolidation run.
     */
    private const BATCH_SIZE = 100;

    public function __construct(
        private readonly MemoryFormationPipeline $pipeline,
    ) {}

    /**
     * Run full consolidation cycle (all types).
     *
     * @param  int|null  $userId  Scope to specific user, null for all users
     * @param  string|null  $type  Run only specific type: 'backfill', 'dedup', or null for all
     * @return array<string, mixed> Consolidation results
     */
    public function run(?int $userId = null, ?string $type = null): array
    {
        if (! app(FeatureFlagManager::class)->enabled(FeatureFlagManager::MEMORY_ENABLED)) {
            return ['skipped' => true, 'reason' => 'Memory system disabled'];
        }

        $results = [];

        if ($type === null || $type === 'backfill') {
            $results['backfill'] = $this->runBackfill($userId);
        }

        if ($type === null || $type === 'dedup') {
            $results['deduplication'] = $this->runDeduplication($userId);
        }

        return $results;
    }

    /**
     * Run backfill retry for failed memory formations.
     *
     * Processes retriable failures (pending and under max attempts).
     * Creates MemoryConsolidationLog entry with checkpoint for resumability.
     *
     * @param  int|null  $userId  Scope to specific user, null for all users
     * @return array<string, mixed> Backfill results
     */
    public function runBackfill(?int $userId = null): array
    {
        if (! app(FeatureFlagManager::class)->enabled(FeatureFlagManager::MEMORY_ENABLED)) {
            return ['skipped' => true, 'reason' => 'Memory system disabled', 'processed' => 0];
        }

        // Get last checkpoint for resumability
        $checkpoint = MemoryConsolidationLog::getLastCheckpoint(
            $userId,
            MemoryConsolidationLog::TYPE_FAILURE_BACKFILL
        );

        $lastProcessedId = $checkpoint['last_processed_id'] ?? 0;

        // Query retriable failures
        $query = MemoryFormationFailure::query()
            ->retriable()
            ->where('id', '>', $lastProcessedId)
            ->orderBy('id');

        if ($userId !== null) {
            $query->forUser($userId);
        }

        $failures = $query->take(self::BATCH_SIZE)->get();

        $processed = 0;
        $succeeded = 0;
        $failed = 0;
        $currentId = $lastProcessedId;

        foreach ($failures as $failure) {
            $currentId = $failure->id;

            try {
                $run = AgentJobRun::find($failure->run_id);
                if ($run === null) {
                    // Run no longer exists, mark as backfilled (nothing to do)
                    $failure->markBackfilled();
                    $processed++;
                    $succeeded++;

                    continue;
                }

                // Attempt to process via the formation pipeline
                $result = $this->pipeline->process($run);

                if ($result->success) {
                    $failure->markBackfilled();
                    $succeeded++;
                } else {
                    // Increment attempts for next cycle
                    $failure->incrementAttempts();
                    $failed++;

                    Log::debug('ConsolidationService: Backfill retry failed', [
                        'failure_id' => $failure->id,
                        'run_id' => $failure->run_id,
                        'attempts' => $failure->attempts,
                        'error' => $result->errorMessage,
                    ]);
                }

                $processed++;
            } catch (\Throwable $e) {
                $failure->incrementAttempts();
                $failed++;
                $processed++;

                Log::warning('ConsolidationService: Backfill exception', [
                    'failure_id' => $failure->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Save checkpoint for resumability
        $this->saveCheckpoint(
            $userId,
            MemoryConsolidationLog::TYPE_FAILURE_BACKFILL,
            $processed,
            $currentId,
            $succeeded,
            $failed
        );

        return [
            'processed' => $processed,
            'succeeded' => $succeeded,
            'failed' => $failed,
            'checkpoint_id' => $currentId,
        ];
    }

    /**
     * Run deduplication for near-duplicate embeddings.
     *
     * Identifies embeddings with identical content_hash and merges metadata.
     * Keeps the embedding with highest importance, sums access counts.
     *
     * @param  int|null  $userId  Scope to specific user, null for all users
     * @return array<string, mixed> Deduplication results
     */
    public function runDeduplication(?int $userId = null): array
    {
        if (! app(FeatureFlagManager::class)->enabled(FeatureFlagManager::MEMORY_ENABLED)) {
            return ['skipped' => true, 'reason' => 'Memory system disabled', 'duplicates_found' => 0];
        }

        // Find duplicates by content_hash
        // SAFETY: no user input in raw query — aggregation only
        $query = MemoryEmbedding::query()
            ->select('content_hash', 'user_id', DB::raw('COUNT(*) as duplicate_count'))
            ->groupBy('content_hash', 'user_id')
            ->having(DB::raw('COUNT(*)'), '>', 1);

        if ($userId !== null) {
            $query->where('user_id', $userId);
        }

        $duplicates = $query->get();

        $duplicatesFound = 0;
        $duplicatesMerged = 0;

        foreach ($duplicates as $duplicate) {
            $duplicatesFound += $duplicate->duplicate_count - 1;

            // Get all embeddings with this hash for this user
            $embeddings = MemoryEmbedding::where('content_hash', $duplicate->content_hash)
                ->where('user_id', $duplicate->user_id)
                ->orderByDesc('importance_score')
                ->orderByDesc('access_count')
                ->get();

            if ($embeddings->count() <= 1) {
                continue;
            }

            // Keep the first one (highest importance), merge others into it
            $keeper = $embeddings->first();
            $toDelete = $embeddings->slice(1);

            $totalAccessCount = $embeddings->sum('access_count');
            $maxImportance = $embeddings->max('importance_score');

            // Update keeper with merged values
            $keeper->update([
                'importance_score' => $maxImportance,
                'access_count' => $totalAccessCount,
            ]);

            // Delete duplicates
            foreach ($toDelete as $dupe) {
                $dupe->delete();
                $duplicatesMerged++;
            }
        }

        // Log deduplication run
        if ($duplicatesMerged > 0) {
            MemoryConsolidationLog::record(
                $userId,
                MemoryConsolidationLog::TYPE_VECTOR_DEDUP,
                $duplicatesFound,
                "Merged {$duplicatesMerged} duplicate embeddings",
                ['duplicates_found' => $duplicatesFound, 'duplicates_merged' => $duplicatesMerged]
            );
        }

        return [
            'duplicates_found' => $duplicatesFound,
            'duplicates_merged' => $duplicatesMerged,
        ];
    }

    /**
     * Save consolidation checkpoint for resumability.
     */
    private function saveCheckpoint(
        ?int $userId,
        string $type,
        int $processed,
        int $lastProcessedId,
        int $succeeded,
        int $failed
    ): void {
        if ($processed === 0) {
            return;
        }

        MemoryConsolidationLog::record(
            $userId,
            $type,
            $processed,
            "Processed: {$processed}, Succeeded: {$succeeded}, Failed: {$failed}",
            [
                'last_processed_id' => $lastProcessedId,
                'phase' => 'backfill',
                'succeeded' => $succeeded,
                'failed' => $failed,
            ]
        );
    }
}
