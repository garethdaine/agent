<?php

declare(strict_types=1);

namespace App\Jobs\Memory;

use App\Models\MemoryFormationFailure;
use App\Models\RepoAnalysisSession;
use App\Support\Agent\FeatureFlagManager;
use App\Support\Memory\MemoryFormationPipeline;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Dispatches memory formation for completed repo analysis sessions.
 *
 * Extracts analysis results, findings, architecture info from RepoAnalysisTask
 * and RepoAnalysisArtifact records and persists them into the memory system
 * (conversation logs, entities, embeddings, Neo4j graph).
 *
 * Entity extraction focuses on technical entities:
 * files, classes, dependencies, patterns, architectural decisions.
 *
 * Follows the same pattern as MemoryFormationJob:
 * - Queue: memory-formation
 * - Retries: 3 with backoff [10, 30, 60]s
 * - Non-blocking: failures logged but never block repo analysis flow
 * - Feature flag gated: MEMORY_ENABLED
 */
class RepoAnalysisMemoryFormationJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(
        public int $sessionId,
    ) {
        $this->onQueue('memory-formation');
    }

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [10, 30, 60];
    }

    public function shouldQueue(): bool
    {
        return app(FeatureFlagManager::class)->enabled(FeatureFlagManager::MEMORY_ENABLED);
    }

    public function handle(MemoryFormationPipeline $pipeline): void
    {
        $session = RepoAnalysisSession::find($this->sessionId);

        if ($session === null) {
            Log::warning('RepoAnalysisMemoryFormationJob: Session not found', [
                'session_id' => $this->sessionId,
            ]);

            return;
        }

        $result = $pipeline->processRepoAnalysisSession($session);

        if (! $result->success) {
            Log::warning('RepoAnalysisMemoryFormationJob: Formation failed', [
                'session_id' => $this->sessionId,
                'failure_type' => $result->failureType,
                'error' => $result->errorMessage,
                'conversation_logs_created' => $result->conversationLogsCreated,
            ]);

            $this->recordFailure($session, $result->failureType, $result->errorMessage, $result->partialData);
        }

        Log::info('RepoAnalysisMemoryFormationJob: Formation completed', [
            'session_id' => $this->sessionId,
            'conversation_logs_created' => $result->conversationLogsCreated,
            'embeddings_created' => $result->embeddingsCreated,
            'entities_stored' => $result->entitiesStored,
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('RepoAnalysisMemoryFormationJob: Job failed after retries', [
            'session_id' => $this->sessionId,
            'error' => $exception->getMessage(),
        ]);

        $session = RepoAnalysisSession::find($this->sessionId);
        if ($session !== null) {
            $this->recordFailure(
                $session,
                MemoryFormationFailure::TYPE_TIMEOUT,
                $exception->getMessage(),
                []
            );
        }
    }

    /**
     * @return array<int, string>
     */
    public function tags(): array
    {
        return ['memory', 'formation', 'repo-analysis', "session:{$this->sessionId}"];
    }

    private function recordFailure(
        RepoAnalysisSession $session,
        ?string $failureType,
        ?string $errorMessage,
        array $partialData,
    ): void {
        try {
            $existing = MemoryFormationFailure::where('source_type', 'repo_analysis_session')
                ->where('source_id', $session->id)
                ->whereNull('backfilled_at')
                ->first();

            if ($existing !== null) {
                $existing->increment('attempts');
                $existing->update([
                    'failure_type' => $failureType ?? MemoryFormationFailure::TYPE_TIMEOUT,
                    'error_message' => $errorMessage,
                    'partial_data' => $partialData,
                ]);

                return;
            }

            MemoryFormationFailure::create([
                'user_id' => $session->user_id,
                'source_type' => 'repo_analysis_session',
                'source_id' => $session->id,
                'failure_type' => $failureType ?? MemoryFormationFailure::TYPE_TIMEOUT,
                'error_message' => $errorMessage,
                'partial_data' => $partialData,
                'attempts' => $this->attempts(),
            ]);
        } catch (\Throwable $e) {
            Log::error('RepoAnalysisMemoryFormationJob: Failed to record failure', [
                'session_id' => $session->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
