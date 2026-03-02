<?php

declare(strict_types=1);

namespace App\Support\Memory;

/**
 * Working Memory Summarizer for API mode eviction.
 *
 * In API mode, when the working memory buffer exceeds max_messages,
 * older entries are summarized using an LLM before eviction.
 * This preserves context while reducing token usage.
 *
 * This is a stub implementation for v1. Full LLM summarization
 * will be implemented when API mode is enabled.
 *
 * In No-API mode, the WorkingMemoryBuffer uses oldest-first
 * truncation (ZREMRANGEBYRANK) which doesn't require summarization.
 */
class WorkingMemorySummarizer
{
    /**
     * Summarize a batch of working memory entries.
     *
     * @param  array<int, array{role: string, content: string, metadata: array<string, mixed>, timestamp: float}>  $entries
     * @param  int  $maxTokens  Maximum tokens for the summary
     * @return string|null The summary text, or null if summarization is not available
     */
    public function summarize(array $entries, int $maxTokens = 200): ?string
    {
        // Stub: API mode summarization not yet implemented
        // Will use ExtractionProvider::summarize() when available
        return null;
    }

    /**
     * Check if summarization is available (API mode with provider keys).
     */
    public function isAvailable(): bool
    {
        // Stub: Always returns false until API mode is implemented
        return false;
    }

    /**
     * Get the summarization model being used.
     */
    public function getModel(): ?string
    {
        // Stub: No model until API mode is implemented
        return null;
    }
}
