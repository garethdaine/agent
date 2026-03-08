<?php

declare(strict_types=1);

namespace App\Services\Runtime;

use App\Models\User;
use App\Services\Credentials\CredentialsManager;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * LLM client for runtime turn execution using Anthropic Messages API with tool use.
 * API key is resolved from the credential manager (user's stored Anthropic key), not from env.
 */
class RuntimeLlmClient
{
    private const API_VERSION = '2023-06-01';

    public function __construct(
        private ToolGateway $toolGateway,
        private CredentialsManager $credentialsManager,
    ) {}

    /**
     * Complete a turn: send messages and tools to the API, return response blocks and usage.
     * When user is provided, the API key is read from the credential manager.
     *
     * @param  array<int, array{role: string, content: string|array}>  $messages
     * @return array{content: array, stop_reason: string, usage: array{input_tokens: int, output_tokens: int}}
     */
    public function complete(array $messages, string $systemPrompt = '', ?User $user = null): array
    {
        $apiKey = $user !== null
            ? $this->credentialsManager->get($user, 'anthropic', 'api_key')
            : config('runtime.llm.anthropic.api_key');

        if ($apiKey === null || $apiKey === '') {
            throw new \RuntimeException(
                'Anthropic API key not found. Add your key in the credential manager (Settings → Credentials → Anthropic).'
            );
        }

        $model = config('runtime.llm.anthropic.model', 'claude-sonnet-4-20250514');
        $maxTokens = config('runtime.llm.anthropic.max_tokens', 8192);
        $tools = $this->toolGateway->getToolSchemasForLlm();

        $payload = [
            'model' => $model,
            'max_tokens' => $maxTokens,
            'messages' => $this->normalizeMessages($messages),
        ];

        if ($systemPrompt !== '') {
            $payload['system'] = $systemPrompt;
        }

        if ($tools !== []) {
            $payload['tools'] = $tools;
            $payload['tool_choice'] = ['type' => 'auto'];
        }

        $response = Http::withHeaders([
            'x-api-key' => $apiKey,
            'anthropic-version' => self::API_VERSION,
            'Content-Type' => 'application/json',
        ])->timeout(120)->post('https://api.anthropic.com/v1/messages', $payload);

        if (! $response->successful()) {
            Log::channel('runtime')->error('RuntimeLlmClient: API error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new \RuntimeException(
                'Anthropic API error: '.$response->status().' '.$response->body()
            );
        }

        $data = $response->json();
        $content = $data['content'] ?? [];
        $stopReason = $data['stop_reason'] ?? 'end_turn';
        $usage = [
            'input_tokens' => (int) ($data['usage']['input_tokens'] ?? 0),
            'output_tokens' => (int) ($data['usage']['output_tokens'] ?? 0),
        ];

        return [
            'content' => $content,
            'stop_reason' => $stopReason,
            'usage' => $usage,
        ];
    }

    /**
     * @param  array<int, array{role: string, content: string|array}>  $messages
     * @return array<int, array{role: string, content: array}>
     */
    private function normalizeMessages(array $messages): array
    {
        $out = [];
        foreach ($messages as $m) {
            $role = $m['role'] ?? 'user';
            $content = $m['content'] ?? '';
            if (is_string($content)) {
                $content = [['type' => 'text', 'text' => $content]];
            }
            $out[] = ['role' => $role, 'content' => $content];
        }

        return $out;
    }
}
