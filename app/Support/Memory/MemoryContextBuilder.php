<?php

declare(strict_types=1);

namespace App\Support\Memory;

use App\Models\AgentJobRun;
use App\Support\Agent\FeatureFlagManager;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Builds wrapper markdown context for agent runs with memory injection.
 *
 * Generates a wrapper file that prepends memory context to the original task:
 * - Agent identity (resolved via SoulResolver with memory core block fallback)
 * - User context (resolved via SoulResolver with memory core block fallback)
 * - Memory API instructions (so CLI agents can query memory on-the-fly)
 * - Original task content
 *
 * This builder does NOT dump retrieved memories into context. Instead, it tells
 * CLI agents about the memory API endpoint (MEMORY_API_BASE_URL env var) so they
 * can query memory on-the-fly when needed.
 *
 * Token budget calculation:
 * 1. Base: 5% of context window (default 200k)
 * 2. Apply 10% safety margin
 * 3. Clamp to [floor_tokens, ceiling_tokens] range
 *
 * Files are stored in: storage/app/memory/context/{YYYY-MM-DD}/{uuid}.md
 */
class MemoryContextBuilder
{
    public function __construct(
        private readonly SoulResolver $soulResolver,
    ) {}

    /**
     * Build context wrapper markdown and return path to the generated file.
     *
     * Returns null if memory is disabled or if building fails.
     * Failures are logged but never thrown to avoid blocking agent execution.
     *
     * @param  AgentJobRun  $run  The agent run to build context for
     * @return string|null Path to the generated context file, or null if skipped/failed
     */
    public function buildContext(AgentJobRun $run): ?string
    {
        if (! app(FeatureFlagManager::class)->enabled(FeatureFlagManager::MEMORY_ENABLED)) {
            return null;
        }

        try {
            return $this->doBuildContext($run);
        } catch (\Throwable $e) {
            Log::warning('MemoryContextBuilder: Failed to build context', [
                'run_id' => $run->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Calculate the token budget for memory context.
     *
     * Formula:
     * 1. Base = context_window * budget_percent / 100
     * 2. With margin = Base * (100 - margin_percent) / 100
     * 3. Clamped to [floor_tokens, ceiling_tokens]
     *
     * @return int Token budget
     */
    public function calculateTokenBudget(): int
    {
        $config = config('memory.context_injection', []);

        $contextWindow = $config['default_context_window'] ?? 200000;
        $budgetPercent = $config['budget_percent'] ?? 5;
        $marginPercent = $config['margin_percent'] ?? 10;
        $floorTokens = $config['floor_tokens'] ?? 1000;
        $ceilingTokens = $config['ceiling_tokens'] ?? 8000;

        // Step 1: Calculate base tokens (percentage of context window)
        $baseTokens = (int) ($contextWindow * $budgetPercent / 100);

        // Step 2: Apply safety margin reduction
        $withMargin = (int) ($baseTokens * (100 - $marginPercent) / 100);

        // Step 3: Clamp to floor/ceiling
        return max($floorTokens, min($ceilingTokens, $withMargin));
    }

    /**
     * Internal method to build context.
     *
     * @throws \RuntimeException If file operations fail
     */
    private function doBuildContext(AgentJobRun $run): string
    {
        $job = $run->job;
        if ($job === null) {
            throw new \RuntimeException('Run has no associated job');
        }

        $userId = $job->user_id;

        // Get original task content
        $originalContent = $this->getOriginalTaskContent($job->task_markdown_path);

        // Calculate token budget for identity + instructions section
        $tokenBudget = $this->calculateTokenBudget();
        $charBudget = $tokenBudget * ($this->getCharsPerToken());

        // Resolve identity via SoulResolver (memory core blocks serve as defaults)
        $resolvedSoul = $this->soulResolver->resolve(null, $userId);

        // Build wrapper markdown sections
        $sections = [];

        // Agent Identity section (from resolved soul)
        if (! empty($resolvedSoul['personality'])) {
            $sections[] = "## Agent Identity\n\n".$resolvedSoul['personality'];
        }

        // User Context section (from resolved soul)
        if (! empty($resolvedSoul['user_context'])) {
            $sections[] = "## User Context\n\n".$resolvedSoul['user_context'];
        }

        // Memory API instructions (instead of dumping retrieved memories)
        $memorySection = $this->buildMemoryApiSection();
        if ($memorySection !== null) {
            $sections[] = $memorySection;
        }

        // Truncate sections to fit budget if needed
        $combinedSections = $this->truncateToCharBudget(
            implode("\n\n", $sections),
            $charBudget
        );

        // Build final wrapper content
        // Always include separator before original content for consistent structure
        $wrapperContent = $combinedSections;
        if ($wrapperContent !== '') {
            $wrapperContent .= "\n\n";
        }
        $wrapperContent .= "---\n\n";
        $wrapperContent .= $originalContent;

        // Store to file
        $contextPath = $this->storeContextFile($run, $wrapperContent);

        return $contextPath;
    }

    /**
     * Build the memory API instructions section for CLI agents.
     *
     * Tells CLI agents about the memory API endpoint so they can query
     * memory on-the-fly rather than receiving a bulk context dump.
     */
    private function buildMemoryApiSection(): ?string
    {
        if (! config('memory.api_enabled')) {
            return null;
        }

        return "## Memory Access\n\n"
            ."You have access to a 4-layer memory system via the MEMORY_API_BASE_URL environment variable.\n"
            ."- POST /retrieve — Search past conversations and learned facts\n"
            ."- GET /core-blocks — Read persistent memory blocks\n"
            ."- PUT /core-blocks/{key} — Update operational blocks (task_state, active_goals)\n"
            .'Query memory when you need context about past work.';
    }

    /**
     * Get the original task content from the task markdown path.
     */
    private function getOriginalTaskContent(?string $taskPath): string
    {
        if ($taskPath === null || ! File::exists($taskPath)) {
            return '';
        }

        return File::get($taskPath);
    }

    /**
     * Truncate content to character budget.
     */
    private function truncateToCharBudget(string $content, int $charBudget): string
    {
        if (mb_strlen($content) <= $charBudget) {
            return $content;
        }

        // Truncate and add ellipsis
        return mb_substr($content, 0, $charBudget - 3).'...';
    }

    /**
     * Store the context wrapper to a file.
     *
     * File path: storage/app/memory/context/{YYYY-MM-DD}/{uuid}.md
     *
     * @return string Absolute path to the stored file
     *
     * @throws \RuntimeException If file storage fails
     */
    private function storeContextFile(AgentJobRun $run, string $content): string
    {
        $date = now()->format('Y-m-d');
        $uuid = Str::uuid()->toString();
        $filename = $uuid.'.md';

        $directory = storage_path('app/memory/context/'.$date);

        if (! File::isDirectory($directory)) {
            File::makeDirectory($directory, 0775, true);
        }

        $path = $directory.'/'.$filename;

        if (File::put($path, $content) === false) {
            throw new \RuntimeException("Failed to write context file: {$path}");
        }

        Log::debug('MemoryContextBuilder: Created context file', [
            'run_id' => $run->id,
            'path' => $path,
            'size' => strlen($content),
        ]);

        return $path;
    }

    /**
     * Get the character-to-token conversion ratio.
     */
    private function getCharsPerToken(): int
    {
        return (int) (config('memory.context_injection.chars_per_token') ?? 4);
    }
}
