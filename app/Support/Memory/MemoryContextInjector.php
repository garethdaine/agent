<?php

declare(strict_types=1);

namespace App\Support\Memory;

use App\Support\Agent\FeatureFlagManager;
use Illuminate\Support\Facades\Log;

/**
 * Lightweight, non-blocking service for injecting memory context into system prompts.
 *
 * Used by execution paths that don't use the ToolGateway (interrogation, repo analysis).
 * These paths cannot use the MemoryToolAdapter, so we inject relevant memory context
 * directly into their system prompts.
 *
 * Injection includes:
 * - Agent identity (agent_persona core block)
 * - User context (user_profile core block)
 * - Relevant memories from HybridRetriever (if taskContext is provided)
 *
 * Never throws — returns original prompt on any failure to ensure non-blocking behavior.
 */
class MemoryContextInjector
{
    public function __construct(
        private readonly CoreMemoryManager $coreMemoryManager,
        private readonly HybridRetriever $hybridRetriever,
    ) {}

    /**
     * Append relevant memory context to a system prompt.
     *
     * Never throws — returns original prompt on any failure.
     *
     * @param  string  $systemPrompt  The base system prompt to inject into
     * @param  int  $userId  The user ID to retrieve memory for
     * @param  string|null  $taskContext  Optional task context to use as a retrieval query
     * @param  int  $maxChars  Maximum characters for the injected memory section
     * @return string The system prompt with memory context appended
     */
    public function inject(string $systemPrompt, int $userId, ?string $taskContext = null, int $maxChars = 2000): string
    {
        if (! app(FeatureFlagManager::class)->enabled(FeatureFlagManager::MEMORY_ENABLED)) {
            return $systemPrompt;
        }

        try {
            return $this->doInject($systemPrompt, $userId, $taskContext, $maxChars);
        } catch (\Throwable $e) {
            Log::warning('MemoryContextInjector: Failed to inject memory context', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);

            return $systemPrompt;
        }
    }

    /**
     * Internal injection method.
     */
    private function doInject(string $systemPrompt, int $userId, ?string $taskContext, int $maxChars): string
    {
        $sections = [];

        // Fetch core memory blocks
        $agentPersona = $this->coreMemoryManager->get($userId, 'agent_persona');
        $userProfile = $this->coreMemoryManager->get($userId, 'user_profile');

        if ($agentPersona !== null && ! empty($agentPersona->content_text)) {
            $sections[] = '**Agent Identity:** '.$agentPersona->content_text;
        }

        if ($userProfile !== null && ! empty($userProfile->content_text)) {
            $sections[] = '**User Context:** '.$userProfile->content_text;
        }

        // Retrieve relevant memories if task context is provided
        if ($taskContext !== null && trim($taskContext) !== '') {
            $memories = $this->retrieveMemories($userId, $taskContext);
            if ($memories !== []) {
                $memoryLines = array_map(
                    fn (array $m): string => '- '.($m['content'] ?? ''),
                    $memories
                );
                $sections[] = "**Relevant Context:**\n".implode("\n", $memoryLines);
            }
        }

        if ($sections === []) {
            return $systemPrompt;
        }

        // Combine sections and truncate to maxChars
        $combined = implode("\n\n", $sections);
        if (mb_strlen($combined) > $maxChars) {
            $combined = mb_substr($combined, 0, $maxChars - 3).'...';
        }

        return $systemPrompt."\n\n## Relevant Memory Context\n\n".$combined;
    }

    /**
     * Retrieve relevant memories using hybrid search.
     *
     * @return array<array{id: int|string, content: string, source: string, classification: string, rrf_score: float}>
     */
    private function retrieveMemories(int $userId, string $query): array
    {
        try {
            return $this->hybridRetriever->retrieve($userId, $query, [
                'limit' => 5,
                'classification' => ['public', 'internal'],
            ]);
        } catch (\Throwable $e) {
            Log::debug('MemoryContextInjector: Failed to retrieve memories', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }
}
