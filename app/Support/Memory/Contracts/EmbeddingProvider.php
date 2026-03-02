<?php

declare(strict_types=1);

namespace App\Support\Memory\Contracts;

/**
 * Contract for embedding vector generation providers.
 *
 * Embeddings convert text into high-dimensional vectors for semantic search.
 * Currently only OpenAI supports this capability (text-embedding-3-small, 1536d).
 *
 * Implementations should:
 * - Handle rate limiting gracefully with retries
 * - Return null on API errors rather than throwing
 * - Track token usage for cost estimation
 */
interface EmbeddingProvider
{
    /**
     * Generate an embedding vector for a single text.
     *
     * @param  string  $text  The text to embed
     * @return array<float>|null Array of floats (1536d for v1) or null on error
     */
    public function embed(string $text): ?array;

    /**
     * Generate embeddings for multiple texts in a single API call.
     *
     * More efficient than calling embed() multiple times.
     * Returns empty array for any text that fails.
     *
     * @param  array<string>  $texts  Array of texts to embed
     * @return array<array<float>> Array of embedding vectors (same order as input)
     */
    public function embedBatch(array $texts): array;

    /**
     * Get the dimension count of embeddings produced by this provider.
     *
     * v1: Fixed 1536 dimensions (text-embedding-3-small)
     *
     * @return int Number of dimensions
     */
    public function getDimensions(): int;

    /**
     * Get the provider name for logging and tracking.
     *
     * @return string Provider identifier (e.g., 'openai')
     */
    public function getProviderName(): string;

    /**
     * Check if this provider supports embedding generation.
     *
     * Always returns true for EmbeddingProvider implementations,
     * but useful for duck-typing checks.
     *
     * @return bool True if embeddings are supported
     */
    public function supportsEmbeddings(): bool;
}
