<?php

declare(strict_types=1);

namespace App\Support\Memory\Adapters;

use App\Support\Memory\Contracts\EmbeddingProvider;
use App\Support\Memory\Contracts\ExtractionProvider;
use Illuminate\Support\Facades\Log;

/**
 * OpenAI adapter implementing both embedding and extraction capabilities.
 *
 * Provides:
 * - Embeddings via text-embedding-3-small (1536 dimensions)
 * - Entity extraction via gpt-4o-mini with structured output
 * - Importance scoring via gpt-4o-mini
 * - Summarization via gpt-4.1-nano
 *
 * All API calls use Guzzle HTTP client with retry handling.
 */
class OpenAIAdapter extends GuzzleHttpAdapter implements EmbeddingProvider, ExtractionProvider
{
    /**
     * Embedding dimensions for text-embedding-3-small.
     */
    private const EMBEDDING_DIMENSIONS = 1536;

    /**
     * Model for embeddings.
     */
    private string $embeddingsModel;

    /**
     * Model for extraction operations.
     */
    private string $extractionModel;

    /**
     * Model for summarization operations.
     */
    private string $summarizationModel;

    /**
     * Create a new OpenAI adapter instance.
     *
     * @param  string  $apiKey  OpenAI API key
     * @param  string  $extractionModel  Model for extraction (default: gpt-4o-mini)
     * @param  string  $summarizationModel  Model for summarization (default: gpt-4.1-nano)
     * @param  string  $embeddingsModel  Model for embeddings (default: text-embedding-3-small)
     */
    public function __construct(
        string $apiKey,
        string $extractionModel = 'gpt-4o-mini',
        string $summarizationModel = 'gpt-4.1-nano',
        string $embeddingsModel = 'text-embedding-3-small'
    ) {
        $baseUrl = config('memory.providers.openai.base_url', 'https://api.openai.com/v1');
        $timeout = config('memory.providers.openai.timeout', 30);

        parent::__construct($apiKey, $baseUrl, $timeout);

        $this->extractionModel = $extractionModel;
        $this->summarizationModel = $summarizationModel;
        $this->embeddingsModel = $embeddingsModel;
    }

    /**
     * {@inheritdoc}
     */
    protected function getDefaultHeaders(): array
    {
        return [
            'Authorization' => "Bearer {$this->apiKey}",
            'Content-Type' => 'application/json',
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getProviderName(): string
    {
        return 'openai';
    }

    // =========================================================================
    // EmbeddingProvider Implementation
    // =========================================================================

    /**
     * {@inheritdoc}
     */
    public function embed(string $text): ?array
    {
        if (trim($text) === '') {
            return null;
        }

        $response = $this->post('/embeddings', [
            'model' => $this->embeddingsModel,
            'input' => $text,
            'dimensions' => self::EMBEDDING_DIMENSIONS,
        ]);

        if ($response === null) {
            return null;
        }

        return $response['data'][0]['embedding'] ?? null;
    }

    /**
     * {@inheritdoc}
     */
    public function embedBatch(array $texts): array
    {
        $texts = array_filter($texts, fn ($t) => trim($t) !== '');

        if (empty($texts)) {
            return [];
        }

        $response = $this->post('/embeddings', [
            'model' => $this->embeddingsModel,
            'input' => array_values($texts),
            'dimensions' => self::EMBEDDING_DIMENSIONS,
        ]);

        if ($response === null) {
            return array_fill(0, count($texts), []);
        }

        $embeddings = [];
        foreach ($response['data'] ?? [] as $item) {
            $embeddings[$item['index']] = $item['embedding'] ?? [];
        }

        // Sort by index to maintain input order
        ksort($embeddings);

        return array_values($embeddings);
    }

    /**
     * {@inheritdoc}
     */
    public function getDimensions(): int
    {
        return self::EMBEDDING_DIMENSIONS;
    }

    /**
     * {@inheritdoc}
     */
    public function supportsEmbeddings(): bool
    {
        return true;
    }

    // =========================================================================
    // ExtractionProvider Implementation
    // =========================================================================

    /**
     * {@inheritdoc}
     */
    public function extractEntities(string $text): array
    {
        if (trim($text) === '') {
            return [];
        }

        $entityTypes = config('memory.entity_types', []);
        $entityTypesStr = implode(', ', $entityTypes);

        $systemPrompt = <<<PROMPT
You are an entity extraction system. Extract named entities from the given text.

Entity types to extract: {$entityTypesStr}

Respond with a JSON array of objects, each with:
- "type": The entity type from the list above
- "name": The entity name/value as found in the text
- "confidence": A confidence score between 0.0 and 1.0

Only include entities that are clearly present in the text.
Return an empty array [] if no entities are found.
Respond ONLY with the JSON array, no other text.
PROMPT;

        $response = $this->post('/chat/completions', [
            'model' => $this->extractionModel,
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $text],
            ],
            'temperature' => 0.0,
            'max_tokens' => 2000,
        ]);

        if ($response === null) {
            return [];
        }

        $content = $response['choices'][0]['message']['content'] ?? '';

        return $this->parseJsonArray($content);
    }

    /**
     * {@inheritdoc}
     */
    public function scoreImportance(string $text, array $entities): float
    {
        if (trim($text) === '') {
            return 0.0;
        }

        $entityCount = count($entities);
        $entitySummary = $entityCount > 0
            ? 'Entities found: '.json_encode(array_map(fn ($e) => $e['name'], $entities))
            : 'No entities found.';

        $systemPrompt = <<<PROMPT
You are an importance scoring system for long-term memory formation.

Score the importance of the given text on a scale of 0.0 to 1.0:
- 0.0-0.2: Trivial, ephemeral information (greetings, acknowledgments)
- 0.3-0.5: Contextual information with some future reference value
- 0.6-0.8: Important information with clear actionable or reference value
- 0.9-1.0: Critical information (decisions, instructions, key facts)

Consider:
- Entity density and novelty
- Actionable information present
- Reference potential for future interactions
- Technical depth and specificity

{$entitySummary}

Respond with ONLY a single decimal number between 0.0 and 1.0.
PROMPT;

        $response = $this->post('/chat/completions', [
            'model' => $this->extractionModel,
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $text],
            ],
            'temperature' => 0.0,
            'max_tokens' => 10,
        ]);

        if ($response === null) {
            // Default to moderate importance on failure
            return 0.5;
        }

        $content = trim($response['choices'][0]['message']['content'] ?? '0.5');

        // Parse the score
        $score = (float) preg_replace('/[^0-9.]/', '', $content);

        return max(0.0, min(1.0, $score));
    }

    /**
     * {@inheritdoc}
     */
    public function summarize(string $text, int $maxTokens): string
    {
        if (trim($text) === '') {
            return '';
        }

        $systemPrompt = <<<'PROMPT'
You are a summarization system for working memory management.

Summarize the given conversation content while preserving:
- Key decisions and action items
- Important context and constraints
- Entity references that may be needed later

Be concise but preserve essential information.
PROMPT;

        $response = $this->post('/chat/completions', [
            'model' => $this->summarizationModel,
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $text],
            ],
            'temperature' => 0.0,
            'max_tokens' => $maxTokens,
        ]);

        if ($response === null) {
            // Fallback: truncate to approximate token limit
            $charLimit = $maxTokens * 4;

            return mb_substr($text, 0, $charLimit);
        }

        return $response['choices'][0]['message']['content'] ?? '';
    }

    /**
     * {@inheritdoc}
     */
    public function supportsTextGeneration(): bool
    {
        return true;
    }

    // =========================================================================
    // Helper Methods
    // =========================================================================

    /**
     * Parse a JSON array from LLM response content.
     *
     * @param  string  $content  Raw response content
     * @return array<array<string, mixed>> Parsed array or empty on failure
     */
    private function parseJsonArray(string $content): array
    {
        // Clean up common LLM response artifacts
        $content = trim($content);

        // Remove markdown code blocks if present
        if (str_starts_with($content, '```')) {
            $content = preg_replace('/^```(?:json)?\s*/', '', $content);
            $content = preg_replace('/\s*```$/', '', $content);
        }

        $parsed = json_decode($content, true);

        if (! is_array($parsed)) {
            Log::debug('Failed to parse entity extraction response', [
                'provider' => $this->getProviderName(),
                'content' => mb_substr($content, 0, 200),
            ]);

            return [];
        }

        // Validate structure
        $validated = [];
        foreach ($parsed as $item) {
            if (is_array($item) && isset($item['type'], $item['name'])) {
                $validated[] = [
                    'type' => (string) $item['type'],
                    'name' => (string) $item['name'],
                    'confidence' => isset($item['confidence']) ? (float) $item['confidence'] : 0.8,
                ];
            }
        }

        return $validated;
    }
}
