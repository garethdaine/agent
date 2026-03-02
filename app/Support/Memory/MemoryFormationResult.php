<?php

declare(strict_types=1);

namespace App\Support\Memory;

/**
 * Result object for memory formation pipeline operations.
 *
 * Tracks success/failure state, partial progress, and failure details
 * for retry and backfill operations.
 */
readonly class MemoryFormationResult
{
    public function __construct(
        public bool $success = true,
        public int $conversationLogsCreated = 0,
        public int $embeddingsCreated = 0,
        public int $entitiesStored = 0,
        public int $relationshipsStored = 0,
        public ?string $failureType = null,
        public ?string $errorMessage = null,
        public array $partialData = [],
        public bool $graphSkipped = false,
    ) {}

    /**
     * Create a success result.
     */
    public static function success(
        int $conversationLogsCreated = 0,
        int $embeddingsCreated = 0,
        int $entitiesStored = 0,
        int $relationshipsStored = 0,
        bool $graphSkipped = false
    ): self {
        return new self(
            success: true,
            conversationLogsCreated: $conversationLogsCreated,
            embeddingsCreated: $embeddingsCreated,
            entitiesStored: $entitiesStored,
            relationshipsStored: $relationshipsStored,
            graphSkipped: $graphSkipped,
        );
    }

    /**
     * Create a failure result.
     */
    public static function failure(
        string $failureType,
        string $errorMessage,
        array $partialData = [],
        int $conversationLogsCreated = 0,
        int $embeddingsCreated = 0,
        int $entitiesStored = 0
    ): self {
        return new self(
            success: false,
            conversationLogsCreated: $conversationLogsCreated,
            embeddingsCreated: $embeddingsCreated,
            entitiesStored: $entitiesStored,
            failureType: $failureType,
            errorMessage: $errorMessage,
            partialData: $partialData,
        );
    }

    /**
     * Get all data as array for serialization.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'conversation_logs_created' => $this->conversationLogsCreated,
            'embeddings_created' => $this->embeddingsCreated,
            'entities_stored' => $this->entitiesStored,
            'relationships_stored' => $this->relationshipsStored,
            'failure_type' => $this->failureType,
            'error_message' => $this->errorMessage,
            'partial_data' => $this->partialData,
            'graph_skipped' => $this->graphSkipped,
        ];
    }
}
