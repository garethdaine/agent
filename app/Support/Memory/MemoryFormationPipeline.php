<?php

declare(strict_types=1);

namespace App\Support\Memory;

use App\Models\AgentJobRun;
use App\Models\MemoryConversationLog;
use App\Models\MemoryEmbedding;
use App\Models\MemoryFormationFailure;
use App\Support\Agent\FeatureFlagManager;
use App\Support\Memory\Contracts\EmbeddingProvider;
use App\Support\Memory\Contracts\ExtractionProvider;
use Illuminate\Support\Facades\Log;

/**
 * Memory Formation Pipeline for Long-term Memory (Layer 3).
 *
 * Orchestrates the formation of long-term memory from completed agent runs:
 * 1. Retrieve Working Memory buffer for completed run
 * 2. Persist conversation log entries to memory_conversation_logs
 * 3. Extract entities via ExtractionProvider (API mode)
 * 4. Score importance via ExtractionProvider (API mode)
 * 5. Generate embeddings via EmbeddingProvider (API mode)
 * 6. Persist embeddings to memory_embeddings with content_hash dedup
 * 7. Store entities/relationships in Neo4j via Neo4jGraphStore (API mode)
 * 8. Handle partial failures: persist what succeeded + record failure
 *
 * Entity types extracted:
 * - Standard NER: Person, Organization, Location, Date, Concept
 * - Technical: File, Function, Class, API, Error, Dependency
 */
class MemoryFormationPipeline
{
    public function __construct(
        private WorkingMemoryBuffer $workingMemoryBuffer,
        private ?ExtractionProvider $extractionProvider = null,
        private ?EmbeddingProvider $embeddingProvider = null,
        private ?Neo4jGraphStore $graphStore = null,
    ) {}

    /**
     * Process a completed run through the formation pipeline.
     *
     * @param  AgentJobRun  $run  The completed run to process
     */
    public function process(AgentJobRun $run): MemoryFormationResult
    {
        $conversationLogsCreated = 0;
        $embeddingsCreated = 0;
        $entitiesStored = 0;
        $relationshipsStored = 0;
        $graphSkipped = false;

        // Get user and job IDs for scoping
        $userId = $run->user_id ?? $run->job?->user_id;
        $jobId = $run->agent_job_id;

        if ($userId === null) {
            Log::warning('MemoryFormationPipeline: Run has no user_id', ['run_id' => $run->id]);

            return MemoryFormationResult::failure(
                MemoryFormationFailure::TYPE_PROVIDER_ERROR,
                'Run has no associated user'
            );
        }

        // Step 1: Retrieve Working Memory buffer
        $workingMemoryEntries = $this->workingMemoryBuffer->getRecent($run->id);

        if (empty($workingMemoryEntries)) {
            Log::debug('MemoryFormationPipeline: No working memory entries', ['run_id' => $run->id]);

            return MemoryFormationResult::success();
        }

        // Step 2: Persist conversation logs (always, even in No-API mode)
        try {
            $conversationLogsCreated = $this->persistConversationLogs(
                $run->id,
                $jobId,
                $userId,
                $workingMemoryEntries
            );
        } catch (\Throwable $e) {
            Log::error('MemoryFormationPipeline: Failed to persist conversation logs', [
                'run_id' => $run->id,
                'error' => $e->getMessage(),
            ]);
            // Continue - this is a critical failure but we should try to extract what we can
        }

        // Steps 3-7 only run in API mode
        if (! app(FeatureFlagManager::class)->enabled(FeatureFlagManager::MEMORY_API_ENABLED)) {
            return MemoryFormationResult::success(
                conversationLogsCreated: $conversationLogsCreated
            );
        }

        // Combine content for extraction
        $combinedContent = $this->combineContentForExtraction($workingMemoryEntries);

        // Step 3: Extract entities
        $entities = [];
        try {
            if ($this->extractionProvider !== null) {
                $entities = $this->extractionProvider->extractEntities($combinedContent);
            }
        } catch (\Throwable $e) {
            Log::error('MemoryFormationPipeline: Entity extraction failed', [
                'run_id' => $run->id,
                'error' => $e->getMessage(),
            ]);

            return MemoryFormationResult::failure(
                MemoryFormationFailure::TYPE_EXTRACTION,
                $e->getMessage(),
                ['conversation_logs_created' => $conversationLogsCreated],
                $conversationLogsCreated
            );
        }

        // Step 4: Score importance
        $importanceScore = 0.5;
        try {
            if ($this->extractionProvider !== null && ! empty($entities)) {
                $importanceScore = $this->extractionProvider->scoreImportance($combinedContent, $entities);
            }
        } catch (\Throwable $e) {
            // Non-fatal - use default score
            Log::warning('MemoryFormationPipeline: Importance scoring failed', [
                'run_id' => $run->id,
                'error' => $e->getMessage(),
            ]);
        }

        // Step 5 & 6: Generate embeddings with dedup
        try {
            $embeddingsCreated = $this->generateAndStoreEmbeddings(
                $userId,
                $run->id,
                $combinedContent,
                $importanceScore
            );
        } catch (\Throwable $e) {
            Log::error('MemoryFormationPipeline: Embedding generation failed', [
                'run_id' => $run->id,
                'error' => $e->getMessage(),
            ]);

            return MemoryFormationResult::failure(
                MemoryFormationFailure::TYPE_EMBEDDING,
                $e->getMessage(),
                [
                    'conversation_logs_created' => $conversationLogsCreated,
                    'entities_extracted' => $this->summarizeEntities($entities),
                ],
                $conversationLogsCreated
            );
        }

        // Step 7: Store in Neo4j graph
        if ($this->graphStore !== null && ! empty($entities)) {
            if (! $this->graphStore->healthCheck()) {
                $graphSkipped = true;
                Log::debug('MemoryFormationPipeline: Neo4j unhealthy, skipping graph storage', [
                    'run_id' => $run->id,
                ]);
            } else {
                try {
                    $this->graphStore->storeEntities($userId, $entities);
                    $entitiesStored = count($entities);

                    // Extract and store relationships between entities
                    $relationships = $this->extractRelationships($entities);
                    if (! empty($relationships)) {
                        $this->graphStore->storeRelationships($userId, $relationships);
                        $relationshipsStored = count($relationships);
                    }
                } catch (\Throwable $e) {
                    Log::error('MemoryFormationPipeline: Graph storage failed', [
                        'run_id' => $run->id,
                        'error' => $e->getMessage(),
                    ]);

                    return MemoryFormationResult::failure(
                        MemoryFormationFailure::TYPE_GRAPH,
                        $e->getMessage(),
                        [
                            'conversation_logs_created' => $conversationLogsCreated,
                            'embeddings_created' => $embeddingsCreated,
                            'entities_extracted' => $this->summarizeEntities($entities),
                        ],
                        $conversationLogsCreated,
                        $embeddingsCreated
                    );
                }
            }
        }

        return MemoryFormationResult::success(
            conversationLogsCreated: $conversationLogsCreated,
            embeddingsCreated: $embeddingsCreated,
            entitiesStored: $entitiesStored,
            relationshipsStored: $relationshipsStored,
            graphSkipped: $graphSkipped
        );
    }

    /**
     * Persist conversation logs from working memory.
     *
     * @param  array<array{role: string, content: string, metadata?: array, timestamp?: float}>  $entries
     */
    private function persistConversationLogs(int $runId, int $jobId, int $userId, array $entries): int
    {
        $count = 0;
        $sequence = MemoryConversationLog::getNextSequence($runId);

        foreach ($entries as $entry) {
            $role = $this->normalizeRole($entry['role'] ?? 'unknown');
            $eventType = $this->detectEventType($entry);

            MemoryConversationLog::create([
                'user_id' => $userId,
                'run_id' => $runId,
                'job_id' => $jobId,
                'role' => $role,
                'content' => $entry['content'] ?? '',
                'sequence' => $sequence++,
                'event_type' => $eventType,
                'classification' => config('memory.default_classification', 'internal'),
                'created_at' => isset($entry['timestamp'])
                    ? \DateTimeImmutable::createFromFormat('U.u', (string) $entry['timestamp'])
                    : now(),
            ]);
            $count++;
        }

        return $count;
    }

    /**
     * Combine working memory entries into a single string for extraction.
     *
     * @param  array<array{role: string, content: string}>  $entries
     */
    private function combineContentForExtraction(array $entries): string
    {
        $parts = [];
        foreach ($entries as $entry) {
            $role = $entry['role'] ?? 'unknown';
            $content = $entry['content'] ?? '';
            $parts[] = "[{$role}]: {$content}";
        }

        return implode("\n\n", $parts);
    }

    /**
     * Generate and store embeddings with content_hash deduplication.
     */
    private function generateAndStoreEmbeddings(
        int $userId,
        int $runId,
        string $content,
        float $importanceScore
    ): int {
        // Check for duplicate by content_hash
        if (MemoryEmbedding::existsByContentHash($userId, $content)) {
            Log::debug('MemoryFormationPipeline: Content already embedded', [
                'run_id' => $runId,
            ]);

            return 0;
        }

        // Generate embedding
        if ($this->embeddingProvider === null) {
            return 0;
        }

        $embedding = $this->embeddingProvider->embed($content);
        if ($embedding === null) {
            throw new \RuntimeException('Embedding provider returned null');
        }

        // Store with dedup
        [$model, $created] = MemoryEmbedding::createOrGetByContentHash($userId, $content, [
            'source_type' => MemoryEmbedding::SOURCE_CONVERSATION,
            'source_id' => (string) $runId,
            'importance_score' => $importanceScore,
            'classification' => config('memory.default_classification', 'internal'),
            'metadata_json' => [
                'run_id' => $runId,
                'provider' => $this->embeddingProvider->getProviderName(),
            ],
        ]);

        return $created ? 1 : 0;
    }

    /**
     * Extract relationships between entities.
     *
     * Creates relationships based on co-occurrence in the same conversation.
     *
     * @param  array<array{type: string, name: string}>  $entities
     * @return array<array{from: string, to: string, type: string}>
     */
    private function extractRelationships(array $entities): array
    {
        $relationships = [];

        // Create co-occurrence relationships between entities
        for ($i = 0; $i < count($entities); $i++) {
            for ($j = $i + 1; $j < count($entities); $j++) {
                $relationships[] = [
                    'from' => $entities[$i]['name'],
                    'to' => $entities[$j]['name'],
                    'type' => 'CO_OCCURRED_WITH',
                ];
            }
        }

        return $relationships;
    }

    /**
     * Normalize role to valid conversation log role.
     */
    private function normalizeRole(string $role): string
    {
        return match ($role) {
            'user', 'assistant', 'system', 'tool' => $role,
            'stderr', 'lifecycle' => 'system',
            default => 'assistant',
        };
    }

    /**
     * Detect event type from entry metadata.
     */
    private function detectEventType(array $entry): string
    {
        $metadata = $entry['metadata'] ?? [];

        if (isset($metadata['type'])) {
            return match ($metadata['type']) {
                'tool_call' => MemoryConversationLog::EVENT_TOOL_CALL,
                'tool_result' => MemoryConversationLog::EVENT_TOOL_RESULT,
                'thinking' => MemoryConversationLog::EVENT_THINKING,
                default => MemoryConversationLog::EVENT_MESSAGE,
            };
        }

        return MemoryConversationLog::EVENT_MESSAGE;
    }

    /**
     * Summarize entities by type for partial data recording.
     *
     * @param  array<array{type: string, name: string}>  $entities
     * @return array<string, int>
     */
    private function summarizeEntities(array $entities): array
    {
        $summary = [];
        foreach ($entities as $entity) {
            $type = $entity['type'] ?? 'Unknown';
            $summary[$type] = ($summary[$type] ?? 0) + 1;
        }

        return $summary;
    }
}
