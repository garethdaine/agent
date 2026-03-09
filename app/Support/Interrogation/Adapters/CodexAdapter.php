<?php

declare(strict_types=1);

namespace App\Support\Interrogation\Adapters;

use App\Models\InterrogationSession;
use App\Support\Agent\DatabaseIsolationEnvironment;
use RuntimeException;

class CodexAdapter extends AbstractBuildAdapter
{
    /**
     * @return array<int, string>
     */
    public function buildDiscoveryCommand(InterrogationSession $session, string $discoveryPrompt, string $systemPrompt): array
    {
        return [
            ...$this->baseExecCommand(),
            '--json',
            $this->composePrompt($systemPrompt, $discoveryPrompt),
        ];
    }

    /**
     * @return array<int, string>
     */
    public function buildQuestionCommand(InterrogationSession $session, string $userMessage, string $systemPrompt): array
    {
        $command = $this->baseExecCommand();

        if (is_string($session->cli_session_id) && $session->cli_session_id !== '') {
            $command[] = 'resume';
            $command[] = $session->cli_session_id;
        }

        $command[] = '--json';
        $command[] = '--output-schema';
        $command[] = $this->schemaFilePath('question', $this->codexQuestionSchema());
        $command[] = $this->composePrompt($systemPrompt, $userMessage);

        return $command;
    }

    /**
     * @return array<int, string>
     */
    public function buildQuestionBankCommand(InterrogationSession $session, string $userMessage, string $systemPrompt): array
    {
        $command = $this->baseExecCommand();

        if (is_string($session->cli_session_id) && $session->cli_session_id !== '') {
            $command[] = 'resume';
            $command[] = $session->cli_session_id;
        }

        $command[] = '--json';
        $command[] = '--output-schema';
        $command[] = $this->schemaFilePath('question-bank', $this->codexQuestionBankSchema());
        $command[] = $this->composePrompt($systemPrompt, $userMessage);

        return $command;
    }

    /**
     * @return array<int, string>
     */
    public function buildSummaryCommand(InterrogationSession $session, string $summaryPrompt, string $systemPrompt): array
    {
        $command = $this->baseExecCommand();

        if (is_string($session->cli_session_id) && $session->cli_session_id !== '') {
            $command[] = 'resume';
            $command[] = $session->cli_session_id;
        }

        $command[] = '--json';
        $command[] = '--output-schema';
        $command[] = $this->schemaFilePath('summary', $this->summarySchema());
        $command[] = $this->composePrompt($systemPrompt, $summaryPrompt);

        return $command;
    }

    /**
     * @return array<int, string>
     */
    public function buildPlanCommand(InterrogationSession $session, string $planningPrompt, string $systemPrompt): array
    {
        $command = $this->baseExecCommand();

        if (is_string($session->cli_session_id) && $session->cli_session_id !== '') {
            $command[] = 'resume';
            $command[] = $session->cli_session_id;
        }

        $command[] = '--json';
        $command[] = '--output-schema';
        $command[] = $this->schemaFilePath('plan', $this->codexPlanSchema());
        $command[] = $this->composePrompt($systemPrompt, $planningPrompt);

        return $command;
    }

    /**
     * @return array<int, string>
     */
    public function buildBuildTasksCommand(InterrogationSession $session, string $prompt, string $systemPrompt): array
    {
        $command = $this->baseExecCommand();

        if (is_string($session->cli_session_id) && $session->cli_session_id !== '') {
            $command[] = 'resume';
            $command[] = $session->cli_session_id;
        }

        $command[] = '--json';
        $command[] = '--output-schema';
        $command[] = $this->schemaFilePath('build-tasks', $this->codexBuildTasksSchema());
        $command[] = $this->composePrompt($systemPrompt, $prompt);

        return $command;
    }

    /**
     * @return array<int, string>
     */
    public function buildReconstructCommand(InterrogationSession $session, string $conversationHistory, string $systemPrompt): array
    {
        return [
            ...$this->baseExecCommand(),
            '--json',
            '--output-schema',
            $this->schemaFilePath('question', $this->codexQuestionSchema()),
            $this->composePrompt($systemPrompt, $conversationHistory),
        ];
    }

    private function composePrompt(string $systemPrompt, string $userPrompt): string
    {
        $system = trim($systemPrompt);
        $user = trim($userPrompt);

        if ($system === '') {
            return $user;
        }

        return "Follow the SYSTEM instructions exactly.\n\n"
            ."<SYSTEM>\n{$system}\n</SYSTEM>\n\n"
            ."<USER_REQUEST>\n{$user}\n</USER_REQUEST>";
    }

    /**
     * @return array<string, mixed>|null
     */
    public function parseStreamEvent(string $line): ?array
    {
        $line = trim($line);

        if ($line === '') {
            return null;
        }

        $mcpEndpoint = $this->extractMcpUnavailableEndpoint($line);
        if ($mcpEndpoint !== null) {
            return [
                'type' => 'issue',
                'payload' => [
                    'source' => 'codex',
                    'code' => 'MCP_SERVER_UNAVAILABLE',
                    'title' => 'MCP server unavailable',
                    'message' => sprintf(
                        'MCP server unavailable. Could not connect to %s (connection refused). Start/restart the MCP server on port 3333 or update MCP endpoint config.',
                        $mcpEndpoint
                    ),
                    'endpoint' => $mcpEndpoint,
                ],
            ];
        }

        if ($this->isIgnorableCodexStateWarning($line)) {
            return [
                'type' => 'diagnostic',
                'payload' => [
                    'source' => 'codex',
                    'message' => '',
                ],
            ];
        }

        $decoded = json_decode($line, true);

        if (is_string($decoded)) {
            $nested = json_decode($decoded, true);
            if (is_array($nested)) {
                $decoded = $nested;
            } else {
                return [
                    'type' => 'message',
                    'payload' => [
                        'source' => 'codex',
                        'message' => $this->truncateMessage($decoded),
                    ],
                ];
            }
        }

        if (! is_array($decoded)) {
            return null;
        }

        $raw = $this->compactRawEvent($decoded);
        $message = (string) ($decoded['message'] ?? $decoded['text'] ?? $decoded['content'] ?? '');
        if ($message === '') {
            $message = $this->extractNestedStreamMessage($decoded);
        }
        if ($message === '') {
            $message = $this->summarizeCodexStreamState($decoded);
        }

        $mcpEndpoint = $this->extractMcpUnavailableEndpoint(json_encode($decoded, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '');
        if ($mcpEndpoint !== null) {
            $message = sprintf(
                'MCP server unavailable. Could not connect to %s (connection refused). Start/restart the MCP server on port 3333 or update MCP endpoint config.',
                $mcpEndpoint
            );
        }

        $payload = [
            'source' => 'codex',
            'raw' => $raw,
            'message' => $this->truncateMessage($message),
        ];

        if (isset($decoded['session_id']) && is_string($decoded['session_id'])) {
            $payload['cli_session_id'] = $decoded['session_id'];
        }

        $usage = $this->extractUsageFromEvent($decoded);
        if ($usage !== null) {
            $payload['usage'] = $usage;
        }

        return [
            'type' => (string) ($decoded['type'] ?? 'message'),
            'payload' => $payload,
        ];
    }

    /**
     * Extract token usage from a stream event when present.
     *
     * @param  array<string, mixed>  $decoded
     * @return array{input_tokens: int, output_tokens: int}|null
     */
    private function extractUsageFromEvent(array $decoded): ?array
    {
        $usage = $decoded['usage'] ?? null;

        if (! is_array($usage)) {
            $item = is_array($decoded['item'] ?? null) ? $decoded['item'] : [];
            $usage = $item['usage'] ?? null;
        }

        if (! is_array($usage)) {
            $result = is_array($decoded['result'] ?? null) ? $decoded['result'] : [];
            $usage = $result['usage'] ?? null;
        }

        if (! is_array($usage)) {
            return null;
        }

        $input = (int) ($usage['input_tokens'] ?? $usage['prompt_tokens'] ?? 0);
        $output = (int) ($usage['output_tokens'] ?? $usage['completion_tokens'] ?? 0);

        if ($input === 0 && $output === 0) {
            return null;
        }

        return [
            'input_tokens' => $input,
            'output_tokens' => $output,
        ];
    }

    /**
     * @param  array<string, mixed>  $decoded
     */
    private function extractNestedStreamMessage(array $decoded): string
    {
        $candidates = [];

        if (is_array($decoded['item'] ?? null)) {
            $candidates[] = $decoded['item'];
        }

        if (is_array($decoded['delta'] ?? null)) {
            $candidates[] = $decoded['delta'];
        }

        if (is_array($decoded['result'] ?? null)) {
            $candidates[] = $decoded['result'];
        }

        foreach ($candidates as $candidate) {
            if ((string) ($candidate['type'] ?? '') === 'command_execution') {
                return $this->formatCommandExecutionMessage($candidate);
            }

            $text = (string) ($candidate['message'] ?? $candidate['text'] ?? $candidate['content'] ?? '');
            if ($text !== '') {
                return $text;
            }

            $aggregatedOutput = trim((string) ($candidate['aggregated_output'] ?? $candidate['output'] ?? ''));
            if ($aggregatedOutput !== '') {
                if ($this->isLikelyCodeOrBlob($aggregatedOutput)) {
                    return 'Reading source files.';
                }

                return $aggregatedOutput;
            }

            if (is_array($candidate['content'] ?? null)) {
                $parts = [];

                foreach ($candidate['content'] as $part) {
                    if (is_array($part)) {
                        $partText = (string) ($part['text'] ?? $part['message'] ?? '');
                        if ($partText !== '') {
                            $parts[] = $partText;
                        }
                    }
                }

                if ($parts !== []) {
                    return trim(implode("\n", $parts));
                }
            }
        }

        return '';
    }

    private function truncateMessage(string $message, int $maxLength = 2000): string
    {
        $message = trim($message);

        if ($message === '') {
            return '';
        }

        if (mb_strlen($message) <= $maxLength) {
            return $message;
        }

        return mb_substr($message, 0, $maxLength).'… [truncated]';
    }

    /**
     * @param  array<string, mixed>  $candidate
     */
    private function formatCommandExecutionMessage(array $candidate): string
    {
        $command = trim((string) ($candidate['command'] ?? ''));
        $status = strtolower(trim((string) ($candidate['status'] ?? '')));
        $exitCode = isset($candidate['exit_code']) && is_numeric($candidate['exit_code'])
            ? (int) $candidate['exit_code']
            : null;
        $aggregatedOutput = trim((string) ($candidate['aggregated_output'] ?? $candidate['output'] ?? ''));

        if ($command === '' && $aggregatedOutput !== '' && ! $this->isLikelyCodeOrBlob($aggregatedOutput)) {
            return $this->truncateMessage($aggregatedOutput, 320);
        }

        $base = $this->summarizeCommandIntent($command);

        if ($status === 'completed' && $exitCode !== null && $exitCode !== 0) {
            return $base.' Command exited with code '.$exitCode.'.';
        }

        return $base;
    }

    private function summarizeCommandIntent(string $command): string
    {
        $command = strtolower(trim($command));

        if ($command === '') {
            return 'Running discovery command.';
        }

        if (str_contains($command, 'rg ') || str_contains($command, 'grep ')) {
            return 'Searching repository files.';
        }

        if (str_contains($command, 'find ') || str_contains($command, 'ls ') || str_contains($command, 'glob')) {
            return 'Listing project files.';
        }

        if (str_contains($command, 'sed -n') || str_contains($command, 'cat ') || str_contains($command, 'head ') || str_contains($command, 'tail ')) {
            return 'Reading source files.';
        }

        if (str_contains($command, 'php artisan')) {
            return 'Inspecting Laravel application state.';
        }

        return 'Running discovery command.';
    }

    /**
     * @param  array<string, mixed>  $decoded
     */
    private function summarizeCodexStreamState(array $decoded): string
    {
        $type = strtolower(trim((string) ($decoded['type'] ?? '')));

        return match ($type) {
            'thread.started' => 'Starting Codex analysis.',
            'turn.started' => 'Running Codex analysis step.',
            'turn.completed' => 'Completed Codex analysis step.',
            'turn.failed', 'error' => 'Codex reported an execution error.',
            default => '',
        };
    }

    private function extractMcpUnavailableEndpoint(string $value): ?string
    {
        if ($value === '') {
            return null;
        }

        if (preg_match('/rmcp::transport::worker[\s\S]{0,1800}?transport channel closed[\s\S]{0,1800}?(https?:\/\/[^\s"\']+\/mcp)[\s\S]{0,1800}?connection refused/i', $value, $matches) !== 1) {
            return null;
        }

        $endpoint = trim((string) ($matches[1] ?? '')); // @phpstan-ignore nullCoalesce.offset

        return $endpoint !== '' ? $endpoint : null;
    }

    private function isLikelyCodeOrBlob(string $message): bool
    {
        $message = trim($message);

        if ($message === '') {
            return false;
        }

        if (mb_strlen($message) >= 600) {
            return true;
        }

        if (preg_match('/<\?php|^namespace\s+[A-Za-z0-9_\\\\]+;|^\s*class\s+[A-Za-z0-9_]+/mi', $message) === 1) {
            return true;
        }

        if (preg_match('/\b(?:function|public|private|protected)\s+[A-Za-z0-9_]+\s*\(/', $message) === 1) {
            return true;
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $decoded
     * @return array<string, mixed>
     */
    private function compactRawEvent(array $decoded): array
    {
        $item = $decoded['item'] ?? null;
        if (! is_array($item)) {
            return $decoded;
        }

        if ((string) ($item['type'] ?? '') !== 'command_execution') {
            return $decoded;
        }

        $compact = $decoded;
        $compactItem = $item;
        $aggregatedOutput = trim((string) ($compactItem['aggregated_output'] ?? ''));
        $output = trim((string) ($compactItem['output'] ?? ''));
        $outputLength = max(mb_strlen($aggregatedOutput), mb_strlen($output));

        unset($compactItem['aggregated_output'], $compactItem['output']);

        if ($outputLength > 0) {
            $compactItem['output_omitted'] = true;
            $compactItem['output_length'] = $outputLength;
        }

        $compact['item'] = $compactItem;

        return $compact;
    }

    /**
     * @return array<string, string|bool>
     */
    public function buildEnvironment(InterrogationSession $session): array
    {
        $env = DatabaseIsolationEnvironment::build($_ENV);

        $blockedKeys = [
            'CODEX_THREAD_ID',
            'CODEX_SESSION_ID',
            'CODEX_INTERNAL_ORIGINATOR_OVERRIDE',
        ];

        foreach ($blockedKeys as $blockedKey) {
            $env[$blockedKey] = false;
        }

        $env['INTERROGATION_SESSION_ID'] = (string) $session->id;

        return $env;
    }

    protected function executable(): string
    {
        return (string) (config('agent.runner_executables.codex') ?: 'codex');
    }

    /**
     * @return array<int, string>
     */
    private function baseExecCommand(): array
    {
        $command = [
            $this->executable(),
        ];

        $model = $this->model();
        if ($model !== '') {
            $command[] = '--model';
            $command[] = $model;
        }

        $command[] = 'exec';

        return $command;
    }

    protected function model(): string
    {
        if ($this->modelOverride !== null && $this->modelOverride !== '') {
            return $this->modelOverride;
        }

        return trim((string) (
            config('agent.interrogation.codex_model')
            ?: config('agent.runner_models.codex')
        ));
    }

    /**
     * Codex-specific question schema with all fields required.
     */
    private function codexQuestionSchema(): string
    {
        return json_encode([
            'type' => 'object',
            'required' => ['question_id', 'canonical_key', 'question_text', 'answer_type', 'options', 'reasoning', 'category', 'progress_estimate', 'is_complete', 'cli_session_id'],
            'additionalProperties' => false,
            'properties' => [
                'question_id' => ['type' => 'string'],
                'canonical_key' => ['type' => 'string'],
                'question_text' => ['type' => 'string'],
                'answer_type' => ['type' => 'string', 'enum' => ['choice', 'freetext', 'skip_allowed']],
                'options' => ['type' => 'array', 'items' => ['type' => 'string']],
                'reasoning' => ['type' => 'string'],
                'category' => ['type' => 'string'],
                'progress_estimate' => ['type' => 'integer'],
                'is_complete' => ['type' => 'boolean'],
                'cli_session_id' => ['type' => 'string'],
            ],
        ], JSON_UNESCAPED_SLASHES) ?: '{}';
    }

    /**
     * Codex-specific question bank schema with cli_session_id required.
     */
    private function codexQuestionBankSchema(): string
    {
        return json_encode([
            'type' => 'object',
            'required' => ['questions', 'cli_session_id'],
            'additionalProperties' => false,
            'properties' => [
                'questions' => [
                    'type' => 'array',
                    'minItems' => 1,
                    'maxItems' => 30,
                    'items' => [
                        'type' => 'object',
                        'required' => ['category', 'decision_axis', 'prompt', 'answer_type', 'options', 'depends_on_axes', 'priority', 'rationale'],
                        'additionalProperties' => false,
                        'properties' => [
                            'category' => ['type' => 'string'],
                            'decision_axis' => ['type' => 'string'],
                            'prompt' => ['type' => 'string'],
                            'answer_type' => ['type' => 'string', 'enum' => ['choice', 'freetext']],
                            'options' => ['type' => 'array', 'items' => ['type' => 'string']],
                            'depends_on_axes' => ['type' => 'array', 'items' => ['type' => 'string']],
                            'priority' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100],
                            'rationale' => ['type' => 'string'],
                        ],
                    ],
                ],
                'cli_session_id' => ['type' => 'string'],
            ],
        ], JSON_UNESCAPED_SLASHES) ?: '{}';
    }

    /**
     * Codex-specific plan schema with all fields required.
     */
    private function codexPlanSchema(): string
    {
        return json_encode([
            'type' => 'object',
            'required' => ['plan_markdown', 'sections', 'risks', 'assumptions'],
            'additionalProperties' => false,
            'properties' => [
                'plan_markdown' => ['type' => 'string'],
                'sections' => ['type' => 'array', 'items' => ['type' => 'string']],
                'risks' => ['type' => 'array', 'items' => ['type' => 'string']],
                'assumptions' => ['type' => 'array', 'items' => ['type' => 'string']],
            ],
        ], JSON_UNESCAPED_SLASHES) ?: '{}';
    }

    /**
     * Codex-specific build tasks schema with all fields required.
     */
    private function codexBuildTasksSchema(): string
    {
        return json_encode([
            'type' => 'object',
            'required' => ['tasks'],
            'additionalProperties' => false,
            'properties' => [
                'tasks' => [
                    'type' => 'array',
                    'minItems' => 1,
                    'items' => [
                        'type' => 'object',
                        'required' => ['sequence', 'title', 'description', 'instructions_markdown'],
                        'additionalProperties' => false,
                        'properties' => [
                            'sequence' => ['type' => 'integer', 'minimum' => 1],
                            'title' => ['type' => 'string'],
                            'description' => ['type' => 'string'],
                            'instructions_markdown' => ['type' => 'string'],
                        ],
                    ],
                ],
            ],
        ], JSON_UNESCAPED_SLASHES) ?: '{}';
    }

    private function schemaFilePath(string $name, string $schema): string
    {
        $directory = storage_path('framework/interrogation-schemas');
        if (! is_dir($directory) && ! @mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new RuntimeException('Unable to create Codex schema directory: '.$directory);
        }

        $path = $directory.'/codex-'.$name.'.schema.json';
        $existing = @file_get_contents($path);

        if (! is_string($existing) || trim($existing) !== trim($schema)) {
            if (@file_put_contents($path, $schema) === false) {
                throw new RuntimeException('Unable to write Codex schema file: '.$path);
            }
        }

        return $path;
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function decodeBestEffortJson(string $output): ?array
    {
        $output = trim($output);

        if ($output === '') {
            return null;
        }

        $candidates = [];

        $decoded = json_decode($output, true);
        if (is_array($decoded)) {
            $candidates[] = $decoded;
        }

        foreach (preg_split('/\R/', $output) ?: [] as $line) {
            $line = trim((string) $line);
            if ($line === '') {
                continue;
            }

            $lineDecoded = json_decode($line, true);
            if (is_array($lineDecoded)) {
                $candidates[] = $lineDecoded;
            }
        }

        if ($candidates === []) {
            return null;
        }

        $structuredCandidates = [];

        foreach ($candidates as $position => $candidate) {
            $this->collectStructuredPayloadCandidate($structuredCandidates, $candidate, $position * 10);
            $this->collectStructuredPayloadCandidate($structuredCandidates, $candidate['structured_output'] ?? null, ($position * 10) + 1);
            $this->collectStructuredPayloadCandidate($structuredCandidates, $candidate['result'] ?? null, ($position * 10) + 2);

            $item = $candidate['item'] ?? null;
            if (! is_array($item)) {
                continue;
            }

            $this->collectStructuredPayloadCandidate($structuredCandidates, $item['structured_output'] ?? null, ($position * 10) + 3);
            $this->collectStructuredPayloadCandidate($structuredCandidates, $item['result'] ?? null, ($position * 10) + 4);

            foreach (['text', 'message', 'content'] as $offset => $field) {
                $text = $item[$field] ?? null;
                if (! is_string($text) || trim($text) === '') {
                    continue;
                }

                $parsedText = json_decode(trim($text), true);
                $this->collectStructuredPayloadCandidate(
                    $structuredCandidates,
                    $parsedText,
                    ($position * 10) + 5 + $offset
                );
            }
        }

        if ($structuredCandidates !== []) {
            return $this->bestStructuredPayload($structuredCandidates);
        }

        return $candidates[array_key_last($candidates)] ?? null;
    }

    /**
     * @param  array<int, array{payload:array<string, mixed>,position:int}>  $bucket
     */
    private function collectStructuredPayloadCandidate(array &$bucket, mixed $value, int $position): void
    {
        if (! is_array($value) || ! $this->looksLikeStructuredPayload($value)) {
            return;
        }

        $bucket[] = [ // @phpstan-ignore parameterByRef.type
            'payload' => $value,
            'position' => $position,
        ];
    }

    /**
     * @param  array<int, array{payload:array<string, mixed>,position:int}>  $structuredCandidates
     * @return array<string, mixed>
     */
    private function bestStructuredPayload(array $structuredCandidates): array
    {
        usort($structuredCandidates, function (array $left, array $right): int {
            $scoreComparison = $this->structuredPayloadScore($left['payload']) <=> $this->structuredPayloadScore($right['payload']);
            if ($scoreComparison !== 0) {
                return $scoreComparison;
            }

            return $left['position'] <=> $right['position'];
        });

        $best = end($structuredCandidates);

        return is_array($best['payload'] ?? null) ? $best['payload'] : [];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function structuredPayloadScore(array $payload): int
    {
        $score = 0;

        foreach (['question_text', 'question', 'summary_markdown', 'summary', 'plan_markdown', 'plan', 'tasks'] as $key) {
            if (! array_key_exists($key, $payload)) {
                continue;
            }

            $score += 10;

            if (is_string($payload[$key])) {
                $score += min(300, mb_strlen(trim((string) $payload[$key])));
            } elseif (is_array($payload[$key])) {
                $score += min(120, count($payload[$key]) * 6);
            }
        }

        foreach (['question_id', 'answer_type', 'options', 'reasoning', 'category', 'progress_estimate', 'is_complete', 'cli_session_id'] as $key) {
            if (array_key_exists($key, $payload)) {
                $score += 3;
            }
        }

        return $score;
    }

    /**
     * @param  array<string, mixed>  $decoded
     */
    private function looksLikeStructuredPayload(array $decoded): bool
    {
        foreach (['question_text', 'question', 'summary_markdown', 'summary', 'plan_markdown', 'plan', 'tasks'] as $key) {
            if (array_key_exists($key, $decoded)) {
                return true;
            }
        }

        return false;
    }

    private function isIgnorableCodexStateWarning(string $line): bool
    {
        return str_contains($line, 'codex_core::rollout::list: state db missing rollout path for thread')
            || str_contains($line, 'codex_core::state_db: state db record_discrepancy');
    }
}
