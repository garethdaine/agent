<?php

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RunnerModelsController
{
    public function __invoke(Request $request): JsonResponse
    {
        $request->validate([
            'runner_type' => ['required', 'string', 'in:claude,codex'],
        ]);

        $runnerType = (string) $request->input('runner_type');
        $cacheKey = 'runner_models:'.$runnerType;

        $cached = Cache::get($cacheKey);
        if (is_array($cached)) {
            return response()->json($cached);
        }

        $result = match ($runnerType) {
            'claude' => $this->fetchClaudeModels(),
            'codex' => $this->fetchCodexModels(),
            default => null,
        };

        if ($result !== null) {
            Cache::put($cacheKey, $result, now()->addMinutes(10));

            return response()->json($result);
        }

        $fallback = $this->fallbackModels($runnerType);
        Cache::put($cacheKey, $fallback, now()->addMinutes(2));

        return response()->json($fallback);
    }

    /**
     * @return array{data: list<array{id: string, name: string}>, default: string}|null
     */
    private function fetchClaudeModels(): ?array
    {
        $apiKey = config('runtime.llm.anthropic.api_key') ?? env('ANTHROPIC_API_KEY', '');
        if (trim((string) $apiKey) === '') {
            return null;
        }

        try {
            $response = Http::timeout(10)
                ->withHeaders([
                    'x-api-key' => $apiKey,
                    'anthropic-version' => '2023-06-01',
                ])
                ->get('https://api.anthropic.com/v1/models');

            if (! $response->successful()) {
                Log::warning('RunnerModelsController: Anthropic models API returned '.$response->status());

                return null;
            }

            $body = $response->json();
            $models = [];

            foreach (($body['data'] ?? []) as $model) {
                $id = trim((string) ($model['id'] ?? ''));
                if ($id === '') {
                    continue;
                }
                $name = trim((string) ($model['display_name'] ?? $id));
                $models[] = ['id' => $id, 'name' => $name];
            }

            usort($models, fn (array $a, array $b): int => strcmp($a['name'], $b['name']));

            $default = trim((string) config('agent.runner_models.claude', 'claude-opus-4-6'));

            return ['data' => $models, 'default' => $default];
        } catch (\Throwable $e) {
            Log::warning('RunnerModelsController: Anthropic models API error', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * @return array{data: list<array{id: string, name: string}>, default: string}|null
     */
    private function fetchCodexModels(): ?array
    {
        $apiKey = env('OPENAI_API_KEY', '');
        if (trim((string) $apiKey) === '') {
            return null;
        }

        try {
            $response = Http::timeout(10)
                ->withToken($apiKey)
                ->get('https://api.openai.com/v1/models');

            if (! $response->successful()) {
                Log::warning('RunnerModelsController: OpenAI models API returned '.$response->status());

                return null;
            }

            $body = $response->json();
            $models = [];

            foreach (($body['data'] ?? []) as $model) {
                $id = trim((string) ($model['id'] ?? ''));
                if ($id === '') {
                    continue;
                }
                $models[] = ['id' => $id, 'name' => $id];
            }

            usort($models, fn (array $a, array $b): int => strcmp($a['name'], $b['name']));

            $default = trim((string) config('agent.runner_models.codex', 'gpt-5.3-codex'));

            return ['data' => $models, 'default' => $default];
        } catch (\Throwable $e) {
            Log::warning('RunnerModelsController: OpenAI models API error', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * @return array{data: list<array{id: string, name: string}>, default: string}
     */
    private function fallbackModels(string $runnerType): array
    {
        if ($runnerType === 'claude') {
            $default = trim((string) config('agent.runner_models.claude', 'claude-opus-4-6'));

            return [
                'data' => [
                    ['id' => 'claude-opus-4-6', 'name' => 'Claude Opus 4.6'],
                    ['id' => 'claude-sonnet-4-6', 'name' => 'Claude Sonnet 4.6'],
                    ['id' => 'claude-haiku-4-5-20251001', 'name' => 'Claude Haiku 4.5'],
                ],
                'default' => $default,
            ];
        }

        $default = trim((string) config('agent.runner_models.codex', 'gpt-5.3-codex'));

        return [
            'data' => [
                ['id' => 'gpt-5.3-codex', 'name' => 'GPT-5.3 Codex'],
                ['id' => 'o3', 'name' => 'o3'],
                ['id' => 'o4-mini', 'name' => 'o4-mini'],
            ],
            'default' => $default,
        ];
    }
}
