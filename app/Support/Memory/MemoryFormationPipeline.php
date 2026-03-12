<?php

declare(strict_types=1);

namespace App\Support\Memory;

use App\Models\AgentJobRun;
use App\Models\ChatMessage;
use App\Models\InterrogationEvent;
use App\Models\InterrogationSession;
use App\Models\MemoryConversationLog;
use App\Models\MemoryEmbedding;
use App\Models\MemoryFormationFailure;
use App\Models\RepoAnalysisArtifact;
use App\Models\RepoAnalysisSession;
use App\Models\RepoAnalysisTask;
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
        $this->entitySanitizer = $entitySanitizer ?? new EntitySanitizer;
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
     * Extract entities with automatic failover to an alternative provider.
     *
     * When the primary extraction provider returns empty results (which may
     * indicate an API failure such as 429 insufficient_quota rather than
     * genuinely no entities), this method tries the failover provider.
     *
     * @param  int  $userId  User ID for failover provider resolution
     * @param  string  $content  Text content to extract entities from
     * @param  string  $contextId  Run ID or session ID for logging
     * @param  string  $contextLabel  'run_id' or 'session_id' for log keys
     * @return array<array{type: string, name: string, confidence?: float}> Extracted entities
     *
     * @throws \Throwable Re-thrown if both primary and failover fail with exceptions
     */
    private function extractEntitiesWithFailover(int $userId, string $content, string $contextId, string $contextLabel): array
    {
        // Try primary provider
        if ($this->extractionProvider !== null) {
            $primaryName = $this->extractionProvider->getProviderName();
            $primaryException = null;

            try {
                $entities = $this->extractionProvider->extractEntities($content);

                if (! empty($entities)) {
                    return $entities;
                }

                // Primary returned empty — may be API failure or genuinely no entities.
                // Try failover if available to be sure.
                Log::debug('MemoryFormationPipeline: Primary extraction returned empty, trying failover', [
                    $contextLabel => $contextId,
                    'primary_provider' => $primaryName,
                ]);
            } catch (\Throwable $e) {
                $primaryException = $e;

                // Primary threw — log and try failover before re-throwing
                Log::warning('MemoryFormationPipeline: Primary extraction threw, trying failover', [
                    $contextLabel => $contextId,
                    'primary_provider' => $primaryName,
                    'error' => $e->getMessage(),
                ]);
            }

            // Attempt failover
            if ($this->adapterFactory !== null) {
                $failover = $this->adapterFactory->getFailoverProvider($userId, $primaryName);

                if ($failover !== null) {
                    Log::info('MemoryFormationPipeline: Using failover extraction provider', [
                        $contextLabel => $contextId,
                        'failover_provider' => $failover->getProviderName(),
                    ]);

                    $entities = $failover->extractEntities($content);

                    if (! empty($entities)) {
                        // Also switch the extraction provider for subsequent steps
                        // (importance scoring) so they use the working provider.
                        $this->extractionProvider = $failover;

                        return $entities;
                    }
                }
            }

            // If the primary threw and failover didn't help, re-throw the original exception
            // so the pipeline records a proper extraction failure.
            if ($primaryException !== null) {
                throw $primaryException;
            }

            // Both returned empty or no failover available
            return [];
        }

        return [];
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

        // Step 3: Extract entities (with automatic failover)
        $entities = [];
        try {
            $entities = $this->extractEntitiesWithFailover(
                $userId,
                $combinedContent,
                (string) $run->id,
                'run_id'
            );
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
        if ($userId === null) { // @phpstan-ignore identical.alwaysFalse
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
            $entities = $this->extractEntitiesWithFailover(
                $userId,
                $combinedContent,
                (string) $session->id,
                'session_id'
            );
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
     * Process a completed interrogation session into long-term memory.
     *
     * Builds entries from InterrogationEvent records (Q&A pairs, discovery
     * findings, summaries, plans), persists conversation logs with source_type
     * identification, then runs extraction, embedding, and graph steps.
     */
    public function processInterrogationSession(InterrogationSession $session): MemoryFormationResult
    {
        $conversationLogsCreated = 0;
        $embeddingsCreated = 0;
        $entitiesStored = 0;
        $relationshipsStored = 0;
        $graphSkipped = false;

        $userId = $session->user_id;
        if ($userId === null) { // @phpstan-ignore identical.alwaysFalse
            Log::warning('MemoryFormationPipeline: Interrogation session has no user_id', ['session_id' => $session->id]);

            return MemoryFormationResult::failure(
                MemoryFormationFailure::TYPE_PROVIDER_ERROR,
                'Interrogation session has no associated user'
            );
        }

        $entries = $this->buildInterrogationEntries($session);
        if (empty($entries)) {
            Log::debug('MemoryFormationPipeline: No entries for interrogation session', ['session_id' => $session->id]);

            return MemoryFormationResult::success();
        }

        try {
            $conversationLogsCreated = $this->persistConversationLogsForSource(
                MemoryConversationLog::SOURCE_INTERROGATION,
                (string) $session->id,
                $userId,
                $entries
            );
        } catch (\Throwable $e) {
            Log::error('MemoryFormationPipeline: Failed to persist interrogation conversation logs', [
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

        $this->resolveProvidersForUser($userId);

        $combinedContent = $this->combineContentForExtraction($entries);

        $entities = [];
        try {
            $entities = $this->extractEntitiesWithFailover(
                $userId,
                $combinedContent,
                (string) $session->id,
                'session_id'
            );
        } catch (\Throwable $e) {
            Log::error('MemoryFormationPipeline: Interrogation entity extraction failed', [
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
            Log::warning('MemoryFormationPipeline: Interrogation importance scoring failed', [
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
                'interrogation-'.$session->id
            );
        } catch (\Throwable $e) {
            $embeddingFailed = true;
            Log::warning('MemoryFormationPipeline: Interrogation embedding failed, continuing in degraded mode', [
                'session_id' => $session->id,
                'error' => $e->getMessage(),
            ]);
        }

        $originalCount = count($entities);
        $entities = $this->entitySanitizer->sanitize($entities);
        if ($originalCount > 0 && count($entities) < $originalCount) {
            Log::info('MemoryFormationPipeline: Sanitizer filtered entities for interrogation session', [
                'session_id' => $session->id,
                'original' => $originalCount,
                'remaining' => count($entities),
            ]);
        }

        if ($this->graphStore !== null && ! empty($entities)) {
            if (! $this->graphStore->healthCheck()) {
                $graphSkipped = true;
                Log::debug('MemoryFormationPipeline: Neo4j unhealthy, skipping interrogation graph storage', [
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
                    Log::error('MemoryFormationPipeline: Interrogation graph storage failed', [
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
     * Process a completed repo analysis session into long-term memory.
     *
     * Builds entries from RepoAnalysisTask outputs and RepoAnalysisArtifact
     * records, persists conversation logs with source_type identification,
     * then runs extraction, embedding, and graph steps with a focus on
     * technical entities (files, classes, dependencies, patterns).
     */
    public function processRepoAnalysisSession(RepoAnalysisSession $session): MemoryFormationResult
    {
        $conversationLogsCreated = 0;
        $embeddingsCreated = 0;
        $entitiesStored = 0;
        $relationshipsStored = 0;
        $graphSkipped = false;

        $userId = $session->user_id;
        if ($userId === null) { // @phpstan-ignore identical.alwaysFalse
            Log::warning('MemoryFormationPipeline: Repo analysis session has no user_id', ['session_id' => $session->id]);

            return MemoryFormationResult::failure(
                MemoryFormationFailure::TYPE_PROVIDER_ERROR,
                'Repo analysis session has no associated user'
            );
        }

        $entries = $this->buildRepoAnalysisEntries($session);
        if (empty($entries)) {
            Log::debug('MemoryFormationPipeline: No entries for repo analysis session', ['session_id' => $session->id]);

            return MemoryFormationResult::success();
        }

        try {
            $conversationLogsCreated = $this->persistConversationLogsForSource(
                MemoryConversationLog::SOURCE_REPO_ANALYSIS,
                (string) $session->id,
                $userId,
                $entries
            );
        } catch (\Throwable $e) {
            Log::error('MemoryFormationPipeline: Failed to persist repo analysis conversation logs', [
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

        $this->resolveProvidersForUser($userId);

        $combinedContent = $this->combineContentForExtraction($entries);

        $entities = [];
        try {
            $entities = $this->extractEntitiesWithFailover(
                $userId,
                $combinedContent,
                (string) $session->id,
                'session_id'
            );
        } catch (\Throwable $e) {
            Log::error('MemoryFormationPipeline: Repo analysis entity extraction failed', [
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

        $importanceScore = 0.6; // Technical analysis typically has higher baseline importance
        try {
            if ($this->extractionProvider !== null && ! empty($entities)) {
                $importanceScore = $this->extractionProvider->scoreImportance($combinedContent, $entities);
            }
        } catch (\Throwable $e) {
            Log::warning('MemoryFormationPipeline: Repo analysis importance scoring failed', [
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
                'repo-analysis-'.$session->id
            );
        } catch (\Throwable $e) {
            $embeddingFailed = true;
            Log::warning('MemoryFormationPipeline: Repo analysis embedding failed, continuing in degraded mode', [
                'session_id' => $session->id,
                'error' => $e->getMessage(),
            ]);
        }

        $originalCount = count($entities);
        $entities = $this->entitySanitizer->sanitize($entities);
        if ($originalCount > 0 && count($entities) < $originalCount) {
            Log::info('MemoryFormationPipeline: Sanitizer filtered entities for repo analysis session', [
                'session_id' => $session->id,
                'original' => $originalCount,
                'remaining' => count($entities),
            ]);
        }

        if ($this->graphStore !== null && ! empty($entities)) {
            if (! $this->graphStore->healthCheck()) {
                $graphSkipped = true;
                Log::debug('MemoryFormationPipeline: Neo4j unhealthy, skipping repo analysis graph storage', [
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
                    Log::error('MemoryFormationPipeline: Repo analysis graph storage failed', [
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
        $baseTimestamp = $session->started_at?->getTimestamp() ?? time(); // @phpstan-ignore method.nonObject
        foreach ($turns as $index => $turn) {
            $userContent = $this->getTurnUserContent($turn); // @phpstan-ignore argument.type
            $assistantContent = $this->getTurnAssistantContent($turn); // @phpstan-ignore argument.type

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

        return $msg->content ?? '';
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
            $role = $this->normalizeRole($entry['role'] ?? 'unknown'); // @phpstan-ignore nullCoalesce.offset
            $eventType = $this->detectEventType($entry);

            MemoryConversationLog::create([
                'user_id' => $userId,
                'run_id' => null,
                'job_id' => null,
                'runtime_session_id' => $runtimeSessionId,
                'role' => $role,
                'content' => $entry['content'] ?? '', // @phpstan-ignore nullCoalesce.offset
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
     * Build working-memory-style entries from an interrogation session's events.
     *
     * Maps InterrogationEvent types to conversation log roles:
     * - QUESTION → assistant (the agent asked the question)
     * - ANSWER → user (the user answered)
     * - DISCOVERY_ACTIVITY → system (automated findings)
     * - SUMMARY → assistant (generated summary)
     * - PLAN → assistant (generated plan)
     * - PHASE_TRANSITION → system (lifecycle event)
     * - ANNOTATION → system (metadata)
     *
     * @return array<int, array{role: string, content: string, metadata?: array, timestamp?: float}>
     */
    private function buildInterrogationEntries(InterrogationSession $session): array
    {
        $events = $session->events()
            ->orderBy('sequence')
            ->get();

        $entries = [];

        foreach ($events as $event) {
            $content = $this->extractInterrogationEventContent($event);
            if ($content === '') {
                continue;
            }

            $role = match ($event->event_type) {
                InterrogationEvent::TYPE_QUESTION => 'assistant',
                InterrogationEvent::TYPE_ANSWER => 'user',
                InterrogationEvent::TYPE_SUMMARY,
                InterrogationEvent::TYPE_PLAN,
                InterrogationEvent::TYPE_SUMMARY_REVIEW,
                InterrogationEvent::TYPE_PLAN_REVIEW => 'assistant',
                InterrogationEvent::TYPE_DISCOVERY_ACTIVITY,
                InterrogationEvent::TYPE_PHASE_TRANSITION,
                InterrogationEvent::TYPE_ANNOTATION,
                InterrogationEvent::TYPE_SYSTEM,
                InterrogationEvent::TYPE_ERROR => 'system',
                default => 'system',
            };

            $timestamp = $event->event_ts?->getTimestamp()
                ?? $event->created_at?->getTimestamp()
                ?? time();

            $entries[] = [
                'role' => $role,
                'content' => $content,
                'metadata' => [
                    'event_type' => $event->event_type,
                    'sequence' => $event->sequence,
                ],
                'timestamp' => (float) $timestamp,
            ];
        }

        return $entries;
    }

    /**
     * Extract meaningful text content from an InterrogationEvent payload.
     */
    private function extractInterrogationEventContent(InterrogationEvent $event): string
    {
        $payload = is_array($event->payload) ? $event->payload : []; // @phpstan-ignore function.alreadyNarrowedType

        // Try common payload keys in priority order
        foreach (['question_text', 'answer_text', 'message', 'content', 'text', 'summary'] as $key) {
            $value = $payload[$key] ?? null;
            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        // For plan/summary events, try nested data
        if (isset($payload['plan']) && is_string($payload['plan'])) {
            return trim($payload['plan']);
        }

        // Fallback: stringify the payload if it has meaningful content
        if (! empty($payload)) {
            $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);
            if (is_string($json) && strlen($json) > 2 && strlen($json) < 5000) {
                return $json;
            }
        }

        return '';
    }

    /**
     * Build working-memory-style entries from a repo analysis session's tasks and artifacts.
     *
     * Focuses on completed task outputs and artifact payloads to capture
     * technical knowledge about the analyzed repository.
     *
     * @return array<int, array{role: string, content: string, metadata?: array, timestamp?: float}>
     */
    private function buildRepoAnalysisEntries(RepoAnalysisSession $session): array
    {
        $entries = [];

        // Include session brief/name as context
        $sessionName = trim((string) ($session->name ?? ''));
        if ($sessionName !== '') {
            $entries[] = [
                'role' => 'system',
                'content' => "Repository analysis: {$sessionName}",
                'metadata' => ['type' => 'session_context'],
                'timestamp' => (float) ($session->started_at?->getTimestamp() ?? $session->created_at?->getTimestamp() ?? time()),
            ];
        }

        // Build entries from completed tasks
        $tasks = $session->tasks()
            ->where('status', 'completed')
            ->orderBy('id')
            ->get();

        foreach ($tasks as $task) {
            $this->addRepoAnalysisTaskEntries($task, $entries);
        }

        // Build entries from artifacts with meaningful payloads
        $artifacts = $session->artifacts()
            ->orderBy('id')
            ->get();

        foreach ($artifacts as $artifact) {
            $this->addRepoAnalysisArtifactEntry($artifact, $entries);
        }

        return $entries;
    }

    /**
     * Add entries from a completed RepoAnalysisTask.
     *
     * @param  array<int, array{role: string, content: string, metadata?: array, timestamp?: float}>  $entries
     */
    private function addRepoAnalysisTaskEntries(RepoAnalysisTask $task, array &$entries): void
    {
        $analyzerName = trim((string) $task->analyzer_name);
        $taskKey = trim((string) $task->task_key);

        // Task metadata as system context
        $contextParts = array_filter([
            $analyzerName !== '' ? "Analyzer: {$analyzerName}" : null,
            $taskKey !== '' ? "Task: {$taskKey}" : null,
        ]);

        if (! empty($contextParts)) {
            $entries[] = [
                'role' => 'system',
                'content' => 'Analysis task completed — '.implode(', ', $contextParts),
                'metadata' => [
                    'type' => 'task_completion',
                    'task_id' => $task->id,
                    'analyzer_name' => $analyzerName,
                ],
                'timestamp' => (float) ($task->finished_at?->getTimestamp() ?? time()),
            ];
        }

        // Extract task metadata content if it has useful output
        $metadata = is_array($task->metadata_json) ? $task->metadata_json : [];
        $sectionTitle = trim((string) ($metadata['section_title'] ?? ''));
        $output = trim((string) ($metadata['output'] ?? ''));

        if ($output !== '' && strlen($output) < 10000) {
            $prefix = $sectionTitle !== '' ? "[{$sectionTitle}] " : '';
            $entries[] = [
                'role' => 'assistant',
                'content' => $prefix.$output,
                'metadata' => [
                    'type' => 'analysis_output',
                    'task_id' => $task->id,
                ],
                'timestamp' => (float) ($task->finished_at?->getTimestamp() ?? time()),
            ];
        }
    }

    /**
     * Add an entry from a RepoAnalysisArtifact if it has meaningful content.
     *
     * @param  array<int, array{role: string, content: string, metadata?: array, timestamp?: float}>  $entries
     */
    private function addRepoAnalysisArtifactEntry(RepoAnalysisArtifact $artifact, array &$entries): void
    {
        $payload = is_array($artifact->payload_json) ? $artifact->payload_json : [];
        if (empty($payload)) {
            return;
        }

        // Extract text content from common payload structures
        $content = '';
        foreach (['content', 'summary', 'findings', 'description', 'text'] as $key) {
            $value = $payload[$key] ?? null;
            if (is_string($value) && trim($value) !== '') {
                $content = trim($value);
                break;
            }
        }

        // If no text field found, try JSON encoding (for structured findings)
        if ($content === '' && ! empty($payload)) {
            $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);
            if (is_string($json) && strlen($json) > 2 && strlen($json) < 5000) {
                $content = $json;
            }
        }

        if ($content === '') {
            return;
        }

        $artifactType = trim((string) $artifact->artifact_type);
        $artifactKey = trim((string) $artifact->artifact_key);
        $prefix = array_filter([$artifactType, $artifactKey]);

        $entries[] = [
            'role' => 'assistant',
            'content' => (! empty($prefix) ? '['.implode('/', $prefix).'] ' : '').$content,
            'metadata' => [
                'type' => 'artifact',
                'artifact_id' => $artifact->id,
                'artifact_type' => $artifactType,
            ],
            'timestamp' => (float) ($artifact->created_at?->getTimestamp() ?? time()),
        ];
    }

    /**
     * Persist conversation logs for a generic source (interrogation, repo analysis, etc).
     *
     * Uses source_type + source_id columns instead of run_id or runtime_session_id.
     *
     * @param  array<array{role: string, content: string, metadata?: array, timestamp?: float}>  $entries
     */
    private function persistConversationLogsForSource(string $sourceType, string $sourceId, int $userId, array $entries): int
    {
        $count = 0;
        $sequence = MemoryConversationLog::getNextSequenceForSource($sourceType, $sourceId);

        foreach ($entries as $entry) {
            $role = $this->normalizeRole($entry['role'] ?? 'unknown'); // @phpstan-ignore nullCoalesce.offset
            $eventType = $this->detectEventType($entry);

            MemoryConversationLog::create([
                'user_id' => $userId,
                'run_id' => null,
                'job_id' => null,
                'runtime_session_id' => null,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'role' => $role,
                'content' => $entry['content'] ?? '', // @phpstan-ignore nullCoalesce.offset
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
            $role = $this->normalizeRole($entry['role'] ?? 'unknown'); // @phpstan-ignore nullCoalesce.offset
            $eventType = $this->detectEventType($entry);

            MemoryConversationLog::create([
                'user_id' => $userId,
                'run_id' => $runId,
                'job_id' => $jobId,
                'role' => $role,
                'content' => $entry['content'] ?? '', // @phpstan-ignore nullCoalesce.offset
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
            $role = $entry['role'] ?? 'unknown'; // @phpstan-ignore nullCoalesce.offset
            $content = $entry['content'] ?? ''; // @phpstan-ignore nullCoalesce.offset
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
            $type = $entity['type'] ?? 'Unknown'; // @phpstan-ignore nullCoalesce.offset
            $summary[$type] = ($summary[$type] ?? 0) + 1;
        }

        return $summary;
    }
}
