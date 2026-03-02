<?php

declare(strict_types=1);

namespace App\Support\Memory\Adapters;

use App\Support\Memory\Contracts\ExtractionProvider;
use Illuminate\Support\Facades\Log;

/**
 * Anthropic adapter implementing extraction capability only.
 *
 * Note: Anthropic does not provide an embeddings API, so this adapter
 * only implements ExtractionProvider. When only Anthropic is configured,
 * the system operates in "degraded" mode with BM25 keyword search only.
 *
 * Provides:
 * - Entity extraction via Claude with structured output
 * - Importance scoring via Claude
 * - Summarization via Claude
 *
 * All API calls use Guzzle HTTP client with retry handling.
 */
class AnthropicAdapter extends GuzzleHttpAdapter implements ExtractionProvider
{
    /**
     * Anthropic API version header.
     */
    private const API_VERSION = '2023-06-01';

    /**
     * Model for extraction operations.
     */
    private string $extractionModel;

    /**
     * Model for summarization operations.
     */
    private string $summarizationModel;

    /**
     * Create a new Anthropic adapter instance.
     *
     * @param  string  $apiKey  Anthropic API key
     * @param  string  $extractionModel  Model for extraction (default: claude-3-haiku-20240307)
     * @param  string  $summarizationModel  Model for summarization (default: claude-3-haiku-20240307)
     */
    public function __construct(
        string $apiKey,
        string $extractionModel = 'claude-3-haiku-20240307',
        string $summarizationModel = 'claude-3-haiku-20240307'
    ) {
        $baseUrl = config('memory.providers.anthropic.base_url', 'https://api.anthropic.com/v1');
        $timeout = config('memory.providers.anthropic.timeout', 30);

        parent::__construct($apiKey, $baseUrl, $timeout);

        $this->extractionModel = $extractionModel;
        $this->summarizationModel = $summarizationModel;
    }

    /**
     * {@inheritdoc}
     */
    protected function getDefaultHeaders(): array
    {
        return [
            'x-api-key' => $this->apiKey,
            'anthropic-version' => self::API_VERSION,
            'Content-Type' => 'application/json',
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getProviderName(): string
    {
        return 'anthropic';
    }

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

        $response = $this->post('/messages', [
            'model' => $this->extractionModel,
            'system' => $systemPrompt,
            'messages' => [
                ['role' => 'user', 'content' => $text],
            ],
            'temperature' => 0.0,
            'max_tokens' => 2000,
        ]);

        if ($response === null) {
            return [];
        }

        $content = $this->extractTextContent($response);

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

        $response = $this->post('/messages', [
            'model' => $this->extractionModel,
            'system' => $systemPrompt,
            'messages' => [
                ['role' => 'user', 'content' => $text],
            ],
            'temperature' => 0.0,
            'max_tokens' => 10,
        ]);

        if ($response === null) {
            // Default to moderate importance on failure
            return 0.5;
        }

        $content = trim($this->extractTextContent($response));

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

        $response = $this->post('/messages', [
            'model' => $this->summarizationModel,
            'system' => $systemPrompt,
            'messages' => [
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

        return $this->extractTextContent($response);
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
     * Extract text content from Anthropic API response.
     *
     * Anthropic returns content as an array of content blocks.
     *
     * @param  array<string, mixed>  $response  API response
     * @return string Extracted text content
     */
    private function extractTextContent(array $response): string
    {
        $contentBlocks = $response['content'] ?? [];

        $text = '';
        foreach ($contentBlocks as $block) {
            if (($block['type'] ?? '') === 'text') {
                $text .= $block['text'] ?? '';
            }
        }

        return $text;
    }

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
