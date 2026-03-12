<?php

declare(strict_types=1);

namespace App\Jobs\Memory;

use App\Models\InterrogationSession;
use App\Models\MemoryFormationFailure;
use App\Support\Agent\FeatureFlagManager;
use App\Support\Memory\MemoryFormationPipeline;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Dispatches memory formation for completed interrogation sessions.
 *
 * Extracts Q&A pairs, discovery outputs, plan content, and build results
 * from InterrogationEvent records and persists them into the memory system
 * (conversation logs, entities, embeddings, Neo4j graph).
 *
 * Follows the same pattern as MemoryFormationJob:
 * - Queue: memory-formation
 * - Retries: 3 with backoff [10, 30, 60]s
 * - Non-blocking: failures logged but never block interrogation flow
 * - Feature flag gated: MEMORY_ENABLED
 */
class InterrogationMemoryFormationJob implements ShouldQueue
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
        $session = InterrogationSession::find($this->sessionId);

        if ($session === null) {
            Log::warning('InterrogationMemoryFormationJob: Session not found', [
                'session_id' => $this->sessionId,
            ]);

            return;
        }

        $result = $pipeline->processInterrogationSession($session);

        if (! $result->success) {
            Log::warning('InterrogationMemoryFormationJob: Formation failed', [
                'session_id' => $this->sessionId,
                'failure_type' => $result->failureType,
                'error' => $result->errorMessage,
                'conversation_logs_created' => $result->conversationLogsCreated,
            ]);

            $this->recordFailure($session, $result->failureType, $result->errorMessage, $result->partialData);
        }

        Log::info('InterrogationMemoryFormationJob: Formation completed', [
            'session_id' => $this->sessionId,
            'conversation_logs_created' => $result->conversationLogsCreated,
            'embeddings_created' => $result->embeddingsCreated,
            'entities_stored' => $result->entitiesStored,
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('InterrogationMemoryFormationJob: Job failed after retries', [
            'session_id' => $this->sessionId,
            'error' => $exception->getMessage(),
        ]);

        $session = InterrogationSession::find($this->sessionId);
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
        return ['memory', 'formation', 'interrogation', "session:{$this->sessionId}"];
    }

    private function recordFailure(
        InterrogationSession $session,
        ?string $failureType,
        ?string $errorMessage,
        array $partialData,
    ): void {
        try {
            $existing = MemoryFormationFailure::where('source_type', 'interrogation_session')
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
                'source_type' => 'interrogation_session',
                'source_id' => $session->id,
                'failure_type' => $failureType ?? MemoryFormationFailure::TYPE_TIMEOUT,
                'error_message' => $errorMessage,
                'partial_data' => $partialData,
                'attempts' => $this->attempts(),
            ]);
        } catch (\Throwable $e) {
            Log::error('InterrogationMemoryFormationJob: Failed to record failure', [
                'session_id' => $session->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
