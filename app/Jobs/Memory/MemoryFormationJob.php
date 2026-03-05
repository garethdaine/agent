<?php

declare(strict_types=1);

namespace App\Jobs\Memory;

use App\Models\AgentJobRun;
use App\Models\MemoryFormationFailure;
use App\Support\Agent\FeatureFlagManager;
use App\Support\Memory\MemoryFormationPipeline;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Memory Formation Job for Long-term Memory (Layer 3).
 *
 * Dispatched after an agent run completes to form long-term memories.
 * Processes working memory through the formation pipeline, extracting
 * entities, generating embeddings, and storing in Neo4j.
 *
 * Retry behavior:
 * - 5 attempts with exponential backoff [10s, 30s, 60s, 120s, 300s]
 * - On exhaustion, creates MemoryFormationFailure record with serialized payload
 * - Partial successes are recorded for backfill retry
 *
 * Queue: memory-formation
 * Timeout: 300 seconds (5 minutes)
 */
class MemoryFormationJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 5;

    /**
     * The maximum number of seconds the job can run.
     */
    public int $timeout = 300;

    /**
     * Create a new job instance.
     *
     * @param  int  $runId  The agent job run ID to process
     */
    public function __construct(
        public int $runId
    ) {
        $this->onQueue('memory-formation');
    }

    /**
     * Get the backoff durations in seconds.
     *
     * @return array<int>
     */
    public function backoff(): array
    {
        return config('memory.formation.backoff_seconds', [10, 30, 60, 120, 300]);
    }

    /**
     * Determine if the job should be queued.
     */
    public function shouldQueue(): bool
    {
        return app(FeatureFlagManager::class)->enabled(FeatureFlagManager::MEMORY_ENABLED);
    }

    /**
     * Execute the job.
     */
    public function handle(MemoryFormationPipeline $pipeline): void
    {
        // Check if memory is enabled
        if (! app(FeatureFlagManager::class)->enabled(FeatureFlagManager::MEMORY_ENABLED)) {
            Log::debug('MemoryFormationJob: Memory disabled, skipping', [
                'run_id' => $this->runId,
            ]);

            return;
        }

        // Find the run
        $run = AgentJobRun::with('job')->find($this->runId);

        if ($run === null) {
            Log::warning('MemoryFormationJob: Run not found', [
                'run_id' => $this->runId,
            ]);

            return;
        }

        // Process through pipeline
        $result = $pipeline->process($run);

        if ($result->success) {
            Log::info('MemoryFormationJob: Formation completed', [
                'run_id' => $this->runId,
                'conversation_logs' => $result->conversationLogsCreated,
                'embeddings' => $result->embeddingsCreated,
                'entities' => $result->entitiesStored,
                'graph_skipped' => $result->graphSkipped,
            ]);

            return;
        }

        // Record failure for backfill
        $this->recordFailure($run, $result->failureType, $result->errorMessage, $result->partialData);

        Log::warning('MemoryFormationJob: Formation failed, recorded for backfill', [
            'run_id' => $this->runId,
            'failure_type' => $result->failureType,
            'error' => $result->errorMessage,
        ]);
    }

    /**
     * Handle a job failure.
     *
     * This is called when all retries are exhausted.
     */
    public function failed(Throwable $exception): void
    {
        Log::error('MemoryFormationJob: Job failed permanently', [
            'run_id' => $this->runId,
            'error' => $exception->getMessage(),
        ]);

        // Find the run to get user_id
        $run = AgentJobRun::find($this->runId);

        if ($run === null) {
            return;
        }

        // Record permanent failure
        $this->recordFailure(
            $run,
            MemoryFormationFailure::TYPE_TIMEOUT,
            $exception->getMessage(),
            [
                'exception_class' => get_class($exception),
                'attempts_exhausted' => true,
            ]
        );
    }

    /**
     * Get the tags that should be assigned to the job.
     *
     * @return array<int, string>
     */
    public function tags(): array
    {
        return [
            'memory',
            'formation',
            'run:'.$this->runId,
        ];
    }

    /**
     * Record a formation failure.
     */
    private function recordFailure(
        AgentJobRun $run,
        ?string $failureType,
        ?string $errorMessage,
        array $partialData = []
    ): void {
        $userId = $run->user_id ?? $run->job?->user_id;

        if ($userId === null) {
            Log::warning('MemoryFormationJob: Cannot record failure, no user_id', [
                'run_id' => $this->runId,
            ]);

            return;
        }

        // Check if a failure record already exists for this run
        $existingFailure = MemoryFormationFailure::where('run_id', $this->runId)
            ->whereNull('backfilled_at')
            ->first();

        if ($existingFailure !== null) {
            // Increment attempts on existing record
            $existingFailure->incrementAttempts();

            return;
        }

        // Create new failure record
        MemoryFormationFailure::record(
            $this->runId,
            $userId,
            $failureType ?? MemoryFormationFailure::TYPE_PROVIDER_ERROR,
            $errorMessage ?? 'Unknown error',
            [
                'run_id' => $this->runId,
                'partial_data' => $partialData,
                'job_attempt' => $this->attempts(),
            ]
        );
    }
}
