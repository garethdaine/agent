<?php

declare(strict_types=1);

namespace App\Support\Memory\Contracts;

/**
 * Contract for LLM text extraction and summarization providers.
 *
 * Provides entity extraction, importance scoring, and text summarization
 * for memory formation. Both OpenAI and Anthropic support this capability.
 *
 * Entity types extracted:
 * - Standard NER: Person, Organization, Location, Date, Concept
 * - Technical: File, Function, Class, API, Error, Dependency
 *
 * Implementations should:
 * - Handle rate limiting gracefully with retries
 * - Return empty results on API errors rather than throwing
 * - Track token usage for cost estimation
 * - Use structured output for reliable entity extraction
 */
interface ExtractionProvider
{
    /**
     * Extract named entities from text.
     *
     * Returns array of entities with type and value:
     * [
     *     ['type' => 'Person', 'name' => 'John Doe', 'confidence' => 0.95],
     *     ['type' => 'API', 'name' => '/api/users', 'confidence' => 0.88],
     * ]
     *
     * @param  string  $text  The text to extract entities from
     * @return array<array{type: string, name: string, confidence?: float}> Extracted entities
     */
    public function extractEntities(string $text): array;

    /**
     * Score the importance of text with extracted entities.
     *
     * Returns a float between 0.0 and 1.0 indicating how important
     * this content is for long-term memory formation.
     *
     * Factors considered:
     * - Entity density and novelty
     * - Actionable information
     * - Reference potential
     *
     * @param  string  $text  The text to score
     * @param  array<array{type: string, name: string}>  $entities  Previously extracted entities
     * @return float Importance score between 0.0 and 1.0
     */
    public function scoreImportance(string $text, array $entities): float;

    /**
     * Summarize text within token budget.
     *
     * Used for Working Memory eviction - summarizes older turns
     * to preserve information while reducing token count.
     *
     * @param  string  $text  The text to summarize
     * @param  int  $maxTokens  Maximum tokens for summary output
     * @return string Summarized text
     */
    public function summarize(string $text, int $maxTokens): string;

    /**
     * Get the provider name for logging and tracking.
     *
     * @return string Provider identifier (e.g., 'openai', 'anthropic')
     */
    public function getProviderName(): string;

    /**
     * Check if this provider supports text generation.
     *
     * Always returns true for ExtractionProvider implementations,
     * but useful for duck-typing checks.
     *
     * @return bool True if text generation is supported
     */
    public function supportsTextGeneration(): bool;
}
