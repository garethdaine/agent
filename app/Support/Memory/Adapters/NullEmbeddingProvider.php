<?php

declare(strict_types=1);

namespace App\Support\Memory\Adapters;

use App\Support\Memory\Contracts\EmbeddingProvider;

/**
 * Null embedding provider for No-API mode.
 *
 * Returns null embeddings and reports no embedding capability.
 * Used when no provider keys are configured or for testing.
 */
class NullEmbeddingProvider implements EmbeddingProvider
{
    /**
     * {@inheritdoc}
     */
    public function embed(string $text): ?array
    {
        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function embedBatch(array $texts): array
    {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function getDimensions(): int
    {
        return 1536;
    }

    /**
     * {@inheritdoc}
     */
    public function getProviderName(): string
    {
        return 'null';
    }

    /**
     * {@inheritdoc}
     */
    public function supportsEmbeddings(): bool
    {
        return false;
    }
}
