<?php

declare(strict_types=1);

namespace App\Support\Memory;

use App\Models\AgentJobRun;
use App\Models\ChatMessage;
use App\Models\MemoryConversationLog;
use App\Models\MemoryEmbedding;
use App\Models\MemoryFormationFailure;
use App\Models\Runtime\RuntimeSession;
use App\Models\Runtime\RuntimeTurn;
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
    private EntitySanitizer $entitySanitizer;

    public function __construct(
        private WorkingMemoryBuffer $workingMemoryBuffer,
        private ?ExtractionProvider $extractionProvider = null,
        private ?EmbeddingProvider $embeddingProvider = null,
        private ?Neo4jGraphStore $graphStore = null,
        private ?MemoryAdapterFactory $adapterFactory = null,
        ?EntitySanitizer $entitySanitizer = null,
    ) {
        $this->entitySanitizer = $entitySanitizer ?? new EntitySanitizer();
    }

    /**
     * Resolve user-specific providers via the adapter factory.
     *
     * When providers were not injected directly (e.g. via container resolution),
     * uses the MemoryAdapterFactory to create user-specific adapters based on
     * their configured API keys and provider preferences.
     */
    private function resolveProvidersForUser(int $userId): void
    {
        if ($this->adapterFactory === null) {
            return;
        }

        if ($this->extractionProvider === null) {
            $this->extractionProvider = $this->adapterFactory->makeExtractionProvider($userId);
        }

        if ($this->embeddingProvider === null || ! $this->embeddingProvider->supportsEmbeddings()) {
            $resolved = $this->adapterFactory->makeEmbeddingProvider($userId);
            if ($resolved !== null) {
                $this->embeddingProvider = $resolved;
            }
        }
    }

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

        // Resolve user-specific providers via factory when not directly injected
        $this->resolveProvidersForUser($userId);

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

        // Step 5 & 6: Generate embeddings with dedup (non-fatal — degrade gracefully)
        $embeddingFailed = false;
        try {
            $embeddingsCreated = $this->generateAndStoreEmbeddings(
                $userId,
                $run->id,
                $combinedContent,
                $importanceScore,
                null
            );
        } catch (\Throwable $e) {
            $embeddingFailed = true;
            Log::warning('MemoryFormationPipeline: Embedding generation failed, continuing in degraded mode', [
                'run_id' => $run->id,
                'error' => $e->getMessage(),
            ]);
        }

        // Sanitize entities before graph storage
        $originalCount = count($entities);
        $entities = $this->entitySanitizer->sanitize($entities);
        if ($originalCount > 0 && count($entities) < $originalCount) {
            Log::info('MemoryFormationPipeline: Sanitizer filtered entities', [
                'run_id' => $run->id,
                'original' => $originalCount,
                'remaining' => count($entities),
            ]);
        }

        // Step 7: Store in Neo4j graph (runs even if embeddings failed)
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

        if ($embeddingFailed) {
            Log::info('MemoryFormationPipeline: Completed in degraded mode (no embeddings)', [
                'run_id' => $run->id,
                'conversation_logs' => $conversationLogsCreated,
                'entities_stored' => $entitiesStored,
            ]);
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
     * Process a stopped runtime session into long-term memory.
     *
     * Builds entries from RuntimeTurns (and linked ChatMessages), persists to
     * memory_conversation_logs with runtime_session_id, then runs extraction,
     * embedding, and graph steps when API mode is enabled.
     */
    public function processRuntimeSession(RuntimeSession $session): MemoryFormationResult
    {
        $conversationLogsCreated = 0;
        $embeddingsCreated = 0;
        $entitiesStored = 0;
        $relationshipsStored = 0;
        $graphSkipped = false;

        $userId = $session->user_id;
        if ($userId === null) {
            Log::warning('MemoryFormationPipeline: Runtime session has no user_id', ['session_id' => $session->id]);

            return MemoryFormationResult::failure(
                MemoryFormationFailure::TYPE_PROVIDER_ERROR,
                'Runtime session has no associated user'
            );
        }

        $entries = $this->buildRuntimeSessionEntries($session);
        if (empty($entries)) {
            Log::debug('MemoryFormationPipeline: No turn entries for runtime session', ['session_id' => $session->id]);

            return MemoryFormationResult::success();
        }

        try {
            $conversationLogsCreated = $this->persistConversationLogsForRuntimeSession(
                $session->id,
                $userId,
                $entries
            );
        } catch (\Throwable $e) {
            Log::error('MemoryFormationPipeline: Failed to persist runtime conversation logs', [
                'session_id' => $session->id,
                'error' => $e->getMessage(),
            ]);

            return MemoryFormationResult::failure(
                MemoryFormationFailure::TYPE_PROVIDER_ERROR,
                $e->getMessage(),
                [],
                0
            );
        }

        if (! app(FeatureFlagManager::class)->enabled(FeatureFlagManager::MEMORY_API_ENABLED)) {
            return MemoryFormationResult::success(conversationLogsCreated: $conversationLogsCreated);
        }

        // Resolve user-specific providers via factory when not directly injected
        $this->resolveProvidersForUser($userId);

        $combinedContent = $this->combineContentForExtraction($entries);

        $entities = [];
        try {
            if ($this->extractionProvider !== null) {
                $entities = $this->extractionProvider->extractEntities($combinedContent);
            }
        } catch (\Throwable $e) {
            Log::error('MemoryFormationPipeline: Runtime entity extraction failed', [
                'session_id' => $session->id,
                'error' => $e->getMessage(),
            ]);

            return MemoryFormationResult::failure(
                MemoryFormationFailure::TYPE_EXTRACTION,
                $e->getMessage(),
                ['conversation_logs_created' => $conversationLogsCreated],
                $conversationLogsCreated
            );
        }

        $importanceScore = 0.5;
        try {
            if ($this->extractionProvider !== null && ! empty($entities)) {
                $importanceScore = $this->extractionProvider->scoreImportance($combinedContent, $entities);
            }
        } catch (\Throwable $e) {
            Log::warning('MemoryFormationPipeline: Runtime importance scoring failed', [
                'session_id' => $session->id,
                'error' => $e->getMessage(),
            ]);
        }

        $embeddingFailed = false;
        try {
            $embeddingsCreated = $this->generateAndStoreEmbeddings(
                $userId,
                0,
                $combinedContent,
                $importanceScore,
                (string) $session->id
            );
        } catch (\Throwable $e) {
            $embeddingFailed = true;
            Log::warning('MemoryFormationPipeline: Runtime embedding failed, continuing in degraded mode', [
                'session_id' => $session->id,
                'error' => $e->getMessage(),
            ]);
        }

        // Sanitize entities before graph storage
        $originalCount = count($entities);
        $entities = $this->entitySanitizer->sanitize($entities);
        if ($originalCount > 0 && count($entities) < $originalCount) {
            Log::info('MemoryFormationPipeline: Sanitizer filtered entities for runtime session', [
                'session_id' => $session->id,
                'original' => $originalCount,
                'remaining' => count($entities),
            ]);
        }

        if ($this->graphStore !== null && ! empty($entities)) {
            if (! $this->graphStore->healthCheck()) {
                $graphSkipped = true;
                Log::debug('MemoryFormationPipeline: Neo4j unhealthy, skipping runtime graph storage', [
                    'session_id' => $session->id,
                ]);
            } else {
                try {
                    $this->graphStore->storeEntities($userId, $entities);
                    $entitiesStored = count($entities);
                    $relationships = $this->extractRelationships($entities);
                    if (! empty($relationships)) {
                        $this->graphStore->storeRelationships($userId, $relationships);
                        $relationshipsStored = count($relationships);
                    }
                } catch (\Throwable $e) {
                    Log::error('MemoryFormationPipeline: Runtime graph storage failed', [
                        'session_id' => $session->id,
                        'error' => $e->getMessage(),
                    ]);
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
     * Build working-memory-style entries from a runtime session's turns.
     *
     * @return array<int, array{role: string, content: string, metadata?: array, timestamp?: float}>
     */
    private function buildRuntimeSessionEntries(RuntimeSession $session): array
    {
        $turns = $session->turns()->orderBy('sequence')->get();
        $entries = [];
        $baseTimestamp = $session->started_at?->getTimestamp() ?? time();

        foreach ($turns as $index => $turn) {
            $userContent = $this->getTurnUserContent($turn);
            $assistantContent = $this->getTurnAssistantContent($turn);

            if ($userContent !== '') {
                $entries[] = [
                    'role' => 'user',
                    'content' => $userContent,
                    'metadata' => [],
                    'timestamp' => $baseTimestamp + $index * 2,
                ];
            }
            if ($assistantContent !== '') {
                $entries[] = [
                    'role' => 'assistant',
                    'content' => $assistantContent,
                    'metadata' => [],
                    'timestamp' => $baseTimestamp + $index * 2 + 1,
                ];
            }
        }

        return $entries;
    }

    private function getTurnUserContent(RuntimeTurn $turn): string
    {
        if ($turn->input_message_id === null) {
            return '';
        }

        $msg = ChatMessage::find($turn->input_message_id);

        return $msg?->content ?? '';
    }

    private function getTurnAssistantContent(RuntimeTurn $turn): string
    {
        if ($turn->output_message_id !== null) {
            $msg = ChatMessage::find($turn->output_message_id);
            if ($msg !== null && trim($msg->content ?? '') !== '') {
                return trim($msg->content);
            }
        }

        return trim((string) ($turn->summary ?? ''));
    }

    /**
     * Persist conversation logs for a runtime session.
     *
     * @param  array<array{role: string, content: string, metadata?: array, timestamp?: float}>  $entries
     */
    private function persistConversationLogsForRuntimeSession(string $runtimeSessionId, int $userId, array $entries): int
    {
        $count = 0;
        $sequence = MemoryConversationLog::getNextSequenceForRuntimeSession($runtimeSessionId);

        foreach ($entries as $entry) {
            $role = $this->normalizeRole($entry['role'] ?? 'unknown');
            $eventType = $this->detectEventType($entry);

            MemoryConversationLog::create([
                'user_id' => $userId,
                'run_id' => null,
                'job_id' => null,
                'runtime_session_id' => $runtimeSessionId,
                'role' => $role,
                'content' => $entry['content'] ?? '',
                'sequence' => $sequence++,
                'event_type' => $eventType,
                'classification' => config('memory.default_classification', 'internal'),
                'created_at' => isset($entry['timestamp'])
                    ? \Carbon\Carbon::createFromTimestamp((float) $entry['timestamp'])
                    : now(),
            ]);
            $count++;
        }

        return $count;
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
     *
     * @param  string|null  $sourceIdOverride  When set (e.g. runtime session UUID), used as source_id instead of runId
     */
    private function generateAndStoreEmbeddings(
        int $userId,
        int $runId,
        string $content,
        float $importanceScore,
        ?string $sourceIdOverride = null
    ): int {
        // Check for duplicate by content_hash
        if (MemoryEmbedding::existsByContentHash($userId, $content)) {
            Log::debug('MemoryFormationPipeline: Content already embedded', [
                'run_id' => $runId,
                'runtime_session_id' => $sourceIdOverride,
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

        $sourceId = $sourceIdOverride ?? (string) $runId;
        $metadata = ['provider' => $this->embeddingProvider->getProviderName()];
        if ($sourceIdOverride !== null) {
            $metadata['runtime_session_id'] = $sourceIdOverride;
        } else {
            $metadata['run_id'] = $runId;
        }

        [$model, $created] = MemoryEmbedding::createOrGetByContentHash($userId, $content, [
            'source_type' => MemoryEmbedding::SOURCE_CONVERSATION,
            'source_id' => $sourceId,
            'importance_score' => $importanceScore,
            'classification' => config('memory.default_classification', 'internal'),
            'metadata_json' => $metadata,
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
