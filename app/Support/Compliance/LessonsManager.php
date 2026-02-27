<?php

namespace App\Support\Compliance;

use App\Enums\TaskCategory;
use Illuminate\Support\Facades\File;

class LessonsManager
{
    private const LESSONS_FILENAME = 'tasks/lessons.md';

    private const TOKENS_PER_CHAR = 0.25; // Rough estimate

    public function __construct(
        private readonly int $defaultTokenBudget = 2000
    ) {}

    public static function fromConfig(): self
    {
        return new self(config('agent.compliance.lessons_token_budget', 2000));
    }

    /**
     * Append a lesson to the lessons file.
     *
     * @param  string  $projectDirectory  The project root directory
     * @param  string  $content  The lesson content
     * @param  string  $source  The source type (explicit_rejection, correction_message, failed_retry, manual_add)
     * @param  array<string, mixed>  $context  Additional context (task_title, task_category, etc.)
     */
    public function appendLesson(
        string $projectDirectory,
        string $content,
        string $source,
        array $context = []
    ): void {
        $path = $this->getLessonsPath($projectDirectory);
        $this->ensureDirectoryExists($path);

        $entry = $this->formatEntry($content, $source, $context);

        if (File::exists($path)) {
            File::append($path, "\n".$entry);
        } else {
            File::put($path, "# Lessons\n\n".$entry);
        }
    }

    /**
     * Query lessons from the lessons file with optional filtering and token budget.
     *
     * @param  string  $projectDirectory  The project root directory
     * @param  string|null  $query  Optional keyword query for filtering
     * @param  TaskCategory|null  $category  Optional category filter
     * @param  int|null  $tokenBudget  Optional token budget override
     * @return array<int, array{timestamp: string, source: string, content: string}>
     */
    public function queryLessons(
        string $projectDirectory,
        ?string $query = null,
        ?TaskCategory $category = null,
        ?int $tokenBudget = null
    ): array {
        $path = $this->getLessonsPath($projectDirectory);
        $budget = $tokenBudget ?? $this->defaultTokenBudget;

        if (! File::exists($path)) {
            return [];
        }

        $content = File::get($path);
        $entries = $this->parseEntries($content);

        // Sort by recency (newest first)
        $entries = array_reverse($entries);

        // Filter by category if provided
        if ($category !== null) {
            $entries = array_filter($entries, fn ($e) => ! isset($e['category']) || $e['category'] === $category->value
            );
        }

        // Filter by query keywords if provided
        if ($query !== null) {
            $keywords = $this->extractKeywords($query);
            $entries = array_filter($entries, fn ($e) => $this->matchesKeywords($e['content'], $keywords)
            );
        }

        // Apply token budget
        return $this->applyTokenBudget(array_values($entries), $budget);
    }

    /**
     * Inject lessons into a context array.
     *
     * @param  array<string, mixed>  $context  The context array (passed by reference)
     * @param  string  $projectDirectory  The project root directory
     * @param  TaskCategory|null  $category  Optional category filter
     */
    public function injectIntoContext(array &$context, string $projectDirectory, ?TaskCategory $category = null): void
    {
        $lessons = $this->queryLessons($projectDirectory, null, $category);

        if (! empty($lessons)) {
            $context['relevant_lessons'] = $lessons;
            $context['lessons_injected'] = true;
        }
    }

    private function getLessonsPath(string $projectDirectory): string
    {
        return rtrim($projectDirectory, '/').'/'.self::LESSONS_FILENAME;
    }

    private function ensureDirectoryExists(string $path): void
    {
        $dir = dirname($path);
        if (! File::isDirectory($dir)) {
            File::makeDirectory($dir, 0755, true);
        }
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function formatEntry(string $content, string $source, array $context): string
    {
        $timestamp = now()->toIso8601String();
        $category = $context['task_category'] ?? 'unknown';
        $taskTitle = $context['task_title'] ?? 'Unknown Task';

        return <<<MD
### [{$timestamp}] [{$source}]
{$content}

**Context:**
- Task: {$taskTitle}
- Category: {$category}
MD;
    }

    /**
     * Parse entries from the lessons file content.
     *
     * @return array<int, array{timestamp: string, source: string, content: string, category?: string}>
     */
    private function parseEntries(string $content): array
    {
        $entries = [];
        $pattern = '/### \[([^\]]+)\] \[([^\]]+)\]\n(.+?)(?=### \[|$)/s';

        preg_match_all($pattern, $content, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $entryContent = trim($match[3]);
            $category = null;

            // Extract category from content if present
            if (preg_match('/Category:\s*(\w+)/', $entryContent, $categoryMatch)) {
                $category = $categoryMatch[1];
            }

            $entry = [
                'timestamp' => $match[1],
                'source' => $match[2],
                'content' => $entryContent,
            ];

            if ($category !== null) {
                $entry['category'] = $category;
            }

            $entries[] = $entry;
        }

        return $entries;
    }

    /**
     * @return array<int, string>
     */
    private function extractKeywords(string $query): array
    {
        $words = preg_split('/\s+/', strtolower($query));
        if ($words === false) {
            return [];
        }

        return array_filter($words, fn ($w) => strlen($w) >= 3);
    }

    /**
     * @param  array<int, string>  $keywords
     */
    private function matchesKeywords(string $content, array $keywords): bool
    {
        $contentLower = strtolower($content);
        foreach ($keywords as $keyword) {
            if (str_contains($contentLower, $keyword)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, array{timestamp: string, source: string, content: string}>  $entries
     * @return array<int, array{timestamp: string, source: string, content: string}>
     */
    private function applyTokenBudget(array $entries, int $budget): array
    {
        $result = [];
        $usedTokens = 0;

        foreach ($entries as $entry) {
            $entryTokens = (int) (strlen($entry['content']) * self::TOKENS_PER_CHAR);
            if ($usedTokens + $entryTokens > $budget) {
                break;
            }
            $result[] = $entry;
            $usedTokens += $entryTokens;
        }

        return $result;
    }
}
