<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Memory;

use App\Http\Controllers\Controller;
use App\Support\Memory\MemorySettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Memory models API controller.
 *
 * Returns available models per provider for the memory system.
 * Fetches from provider APIs when keys are configured, with
 * config-based fallbacks when APIs are unreachable.
 */
class MemoryModelsController extends Controller
{
    /**
     * OpenAI model prefixes relevant to each capability.
     */
    private const OPENAI_EXTRACTION_PREFIXES = ['gpt-4', 'gpt-3.5', 'o1', 'o3', 'o4'];

    private const OPENAI_SUMMARIZATION_PREFIXES = ['gpt-4', 'gpt-3.5', 'o1', 'o3', 'o4'];

    private const OPENAI_EMBEDDING_PREFIXES = ['text-embedding'];

    /**
     * Models to exclude (deprecated, internal, fine-tuned, etc.).
     */
    private const OPENAI_EXCLUDE_PATTERNS = [
        'instruct',
        'realtime',
        'audio',
        'tts',
        'whisper',
        'dall-e',
        'davinci',
        'babbage',
        'search',
        ':ft-',
        'ft:',
    ];

    private const CACHE_TTL_SECONDS = 300; // 5 minutes

    public function __construct(
        private readonly MemorySettingsService $settingsService
    ) {}

    /**
     * GET /memory/models
     *
     * Returns available models grouped by provider and capability.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $result = [];

        foreach (['openai', 'anthropic'] as $provider) {
            if (! $this->settingsService->isProviderKeyConfigured($userId, $provider)) {
                continue;
            }

            $cacheKey = "memory_models:{$userId}:{$provider}";
            $models = Cache::remember($cacheKey, self::CACHE_TTL_SECONDS, function () use ($userId, $provider) {
                return $this->fetchModelsForProvider($userId, $provider);
            });

            $result[$provider] = $models;
        }

        return response()->json(['data' => $result]);
    }

    /**
     * Fetch available models from a provider API.
     *
     * Falls back to config-defined known models on failure.
     */
    private function fetchModelsForProvider(int $userId, string $provider): array
    {
        return match ($provider) {
            'openai' => $this->fetchOpenAIModels($userId),
            'anthropic' => $this->getAnthropicModels(),
            default => $this->getFallbackModels($provider),
        };
    }

    /**
     * Fetch models from OpenAI /v1/models endpoint.
     */
    private function fetchOpenAIModels(int $userId): array
    {
        $apiKey = $this->settingsService->getProviderKey($userId, 'openai');

        if ($apiKey === null) {
            return $this->getFallbackModels('openai');
        }

        try {
            $response = Http::withToken($apiKey)
                ->timeout(10)
                ->get('https://api.openai.com/v1/models');

            if (! $response->successful()) {
                Log::warning('Memory: OpenAI models fetch failed', [
                    'status' => $response->status(),
                ]);

                return $this->getFallbackModels('openai');
            }

            $allModels = collect($response->json('data', []))
                ->pluck('id')
                ->filter(fn (string $id) => ! $this->isExcludedModel($id))
                ->sort()
                ->values()
                ->all();

            return [
                'extraction' => $this->filterByPrefixes($allModels, self::OPENAI_EXTRACTION_PREFIXES),
                'summarization' => $this->filterByPrefixes($allModels, self::OPENAI_SUMMARIZATION_PREFIXES),
                'embeddings' => $this->filterByPrefixes($allModels, self::OPENAI_EMBEDDING_PREFIXES),
            ];
        } catch (\Throwable $e) {
            Log::warning('Memory: OpenAI models fetch exception', [
                'error' => $e->getMessage(),
            ]);

            return $this->getFallbackModels('openai');
        }
    }

    /**
     * Get Anthropic models from config.
     *
     * Anthropic doesn't have a public /models list endpoint,
     * so we use a curated list from config.
     */
    private function getAnthropicModels(): array
    {
        return $this->getFallbackModels('anthropic');
    }

    /**
     * Get fallback models from config.
     */
    private function getFallbackModels(string $provider): array
    {
        return config("memory.known_models.{$provider}", [
            'extraction' => [],
            'summarization' => [],
            'embeddings' => [],
        ]);
    }

    /**
     * Filter model IDs by matching prefixes.
     */
    private function filterByPrefixes(array $models, array $prefixes): array
    {
        return array_values(array_filter(
            $models,
            fn (string $id) => collect($prefixes)->contains(
                fn (string $prefix) => str_starts_with($id, $prefix)
            )
        ));
    }

    /**
     * Check if a model should be excluded.
     */
    private function isExcludedModel(string $id): bool
    {
        foreach (self::OPENAI_EXCLUDE_PATTERNS as $pattern) {
            if (str_contains($id, $pattern)) {
                return true;
            }
        }

        return false;
    }
}
