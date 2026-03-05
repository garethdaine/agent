<?php

namespace App\Support\Interrogation\Adapters;

use App\Models\InterrogationSession;
use App\Support\Agent\DatabaseIsolationEnvironment;
use App\Support\Interrogation\Contracts\InterrogationRunnerAdapter;
use RuntimeException;

class CodexAdapter implements InterrogationRunnerAdapter
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
        $command[] = $this->schemaFilePath('question', $this->questionSchema());
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
        $command[] = $this->schemaFilePath('question-bank', $this->questionBankSchema());
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
        $command[] = $this->schemaFilePath('plan', $this->planSchema());
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
        $command[] = $this->schemaFilePath('build-tasks', $this->buildTasksSchema());
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
            $this->schemaFilePath('question', $this->questionSchema()),
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

        $endpoint = trim((string) ($matches[1] ?? ''));

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
     * @return array<string, mixed>|null
     */
    public function parseQuestionResponse(string $output): ?array
    {
        $decoded = $this->decodeBestEffortJson($output);

        if (! is_array($decoded)) {
            return null;
        }

        $questionText = (string) ($decoded['question_text'] ?? $decoded['question'] ?? '');

        if ($questionText === '') {
            return null;
        }

        return [
            'question_id' => (string) ($decoded['question_id'] ?? 'q-'.substr(sha1($questionText), 0, 12)),
            'canonical_key' => is_string($decoded['canonical_key'] ?? null) ? $decoded['canonical_key'] : null,
            'question_text' => $questionText,
            'answer_type' => (string) ($decoded['answer_type'] ?? 'freetext'),
            'options' => is_array($decoded['options'] ?? null) ? array_values($decoded['options']) : [],
            'reasoning' => (string) ($decoded['reasoning'] ?? ''),
            'category' => (string) ($decoded['category'] ?? 'general'),
            'progress_estimate' => (int) ($decoded['progress_estimate'] ?? 0),
            'is_complete' => (bool) ($decoded['is_complete'] ?? false),
            'cli_session_id' => is_string($decoded['cli_session_id'] ?? null)
                ? $decoded['cli_session_id']
                : (is_string($decoded['session_id'] ?? null) ? $decoded['session_id'] : null),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function parseQuestionBankResponse(string $output): ?array
    {
        $decoded = $this->decodeBestEffortJson($output);

        if (! is_array($decoded) || ! is_array($decoded['questions'] ?? null)) {
            return null;
        }

        $questions = array_values(array_filter(
            $decoded['questions'],
            static fn ($question): bool => is_array($question)
        ));

        return [
            'questions' => $questions,
            'cli_session_id' => is_string($decoded['cli_session_id'] ?? null)
                ? $decoded['cli_session_id']
                : (is_string($decoded['session_id'] ?? null) ? $decoded['session_id'] : null),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function parseSummaryResponse(string $output): ?array
    {
        $decoded = $this->decodeStructuredOutput($output);

        if (! is_array($decoded)) {
            return null;
        }

        if (! isset($decoded['summary_markdown']) && ! isset($decoded['summary'])) {
            return null;
        }

        return $this->normalizeSummaryPayload([
            'summary_markdown' => (string) ($decoded['summary_markdown'] ?? $decoded['summary'] ?? ''),
            'goals' => is_array($decoded['goals'] ?? null) ? array_values($decoded['goals']) : [],
            'constraints' => is_array($decoded['constraints'] ?? null) ? array_values($decoded['constraints']) : [],
            'acceptance_criteria' => is_array($decoded['acceptance_criteria'] ?? null) ? array_values($decoded['acceptance_criteria']) : [],
            'open_questions' => is_array($decoded['open_questions'] ?? null) ? array_values($decoded['open_questions']) : [],
            'private_notes' => (string) ($decoded['private_notes'] ?? ''),
            'cli_session_id' => is_string($decoded['cli_session_id'] ?? null)
                ? $decoded['cli_session_id']
                : (is_string($decoded['session_id'] ?? null) ? $decoded['session_id'] : null),
        ]);
    }

    /**
     * @param  array<string, mixed>  $summary
     * @return array<string, mixed>
     */
    private function normalizeSummaryPayload(array $summary): array
    {
        $fields = ['goals', 'constraints', 'acceptance_criteria', 'open_questions'];
        $markdown = (string) ($summary['summary_markdown'] ?? '');
        $embedded = $this->extractEmbeddedParameterLists($markdown, $fields);
        $summary['summary_markdown'] = $embedded['clean_markdown'];

        foreach ($fields as $field) {
            $existing = $this->toStringList($summary[$field] ?? null);

            if ($existing === [] && isset($embedded['lists'][$field])) {
                $existing = $embedded['lists'][$field];
            }

            $summary[$field] = $existing;
        }

        return $summary;
    }

    /**
     * @param  array<int, string>  $fields
     * @return array{clean_markdown:string,lists:array<string,array<int,string>>}
     */
    private function extractEmbeddedParameterLists(string $markdown, array $fields): array
    {
        if ($markdown === '') {
            return ['clean_markdown' => '', 'lists' => []];
        }

        $fieldAlternation = implode('|', array_map(static fn (string $field): string => preg_quote($field, '/'), $fields));
        $pattern = '/<parameter\s+name="('.$fieldAlternation.')">\s*(\[[\s\S]*?\])\s*(?:<\/parameter>)?/i';
        $lists = [];

        $cleanMarkdown = preg_replace_callback($pattern, function (array $matches) use (&$lists): string {
            $name = strtolower((string) ($matches[1] ?? ''));
            $decoded = json_decode((string) ($matches[2] ?? '[]'), true);

            if (is_array($decoded)) {
                $lists[$name] = $this->toStringList($decoded);
            }

            return '';
        }, $markdown);

        if (! is_string($cleanMarkdown)) {
            $cleanMarkdown = $markdown;
        }

        $cleanMarkdown = preg_replace('/\n{3,}/', "\n\n", $cleanMarkdown) ?? $cleanMarkdown;
        $cleanMarkdown = trim($cleanMarkdown, " \t\n\r\0\x0B,");

        return [
            'clean_markdown' => $cleanMarkdown,
            'lists' => $lists,
        ];
    }

    /**
     * @return array<int, string>
     */
    private function toStringList(mixed $value): array
    {
        if (is_string($value)) {
            $trimmed = trim($value);
            if ($trimmed === '') {
                return [];
            }

            if (str_starts_with($trimmed, '[') && str_ends_with($trimmed, ']')) {
                $decoded = json_decode($trimmed, true);
                if (is_array($decoded)) {
                    $value = $decoded;
                }
            }
        }

        if (! is_array($value)) {
            return [];
        }

        $items = [];

        foreach ($value as $item) {
            if (! is_string($item)) {
                continue;
            }

            $normalized = trim($item);
            if ($normalized === '') {
                continue;
            }

            $items[] = $normalized;
        }

        return array_values(array_unique($items));
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decodeStructuredOutput(string $output): ?array
    {
        $sessionId = $this->extractSessionIdFromOutput($output);
        $decoded = $this->decodeBestEffortJson($output);

        if (! is_array($decoded)) {
            return null;
        }

        if (! isset($decoded['cli_session_id']) && is_string($sessionId) && $sessionId !== '') {
            $decoded['cli_session_id'] = $sessionId;
        }

        if (is_array($decoded['structured_output'] ?? null)) {
            $normalized = $decoded['structured_output'];

            if (! isset($normalized['cli_session_id']) && is_string($sessionId) && $sessionId !== '') {
                $normalized['cli_session_id'] = $sessionId;
            }

            return $normalized;
        }

        if (is_array($decoded['result'] ?? null)) {
            $normalized = $decoded['result'];

            if (! isset($normalized['cli_session_id']) && is_string($sessionId) && $sessionId !== '') {
                $normalized['cli_session_id'] = $sessionId;
            }

            return $normalized;
        }

        if (is_string($decoded['result'] ?? null) && trim($decoded['result']) !== '') {
            $parsedResult = $this->decodeBestEffortJson((string) $decoded['result']);
            if (is_array($parsedResult)) {
                if (! isset($parsedResult['cli_session_id']) && is_string($sessionId) && $sessionId !== '') {
                    $parsedResult['cli_session_id'] = $sessionId;
                }

                return $parsedResult;
            }
        }

        return $decoded;
    }

    private function extractSessionIdFromOutput(string $output): ?string
    {
        $trimmed = trim($output);
        if ($trimmed === '') {
            return null;
        }

        $decoded = json_decode($trimmed, true);
        if (is_array($decoded) && is_string($decoded['session_id'] ?? null) && $decoded['session_id'] !== '') {
            return $decoded['session_id'];
        }

        foreach (preg_split('/\R/', $trimmed) ?: [] as $line) {
            $line = trim((string) $line);
            if ($line === '') {
                continue;
            }

            $lineDecoded = json_decode($line, true);
            if (is_array($lineDecoded) && is_string($lineDecoded['session_id'] ?? null) && $lineDecoded['session_id'] !== '') {
                return $lineDecoded['session_id'];
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function parsePlanResponse(string $output): ?array
    {
        $decoded = $this->decodeBestEffortJson($output);

        if (! is_array($decoded)) {
            return null;
        }

        if (! isset($decoded['plan_markdown']) && ! isset($decoded['plan'])) {
            return null;
        }

        return [
            'plan_markdown' => (string) ($decoded['plan_markdown'] ?? $decoded['plan'] ?? ''),
            'sections' => is_array($decoded['sections'] ?? null) ? array_values($decoded['sections']) : [],
            'risks' => is_array($decoded['risks'] ?? null) ? array_values($decoded['risks']) : [],
            'assumptions' => is_array($decoded['assumptions'] ?? null) ? array_values($decoded['assumptions']) : [],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function parseBuildTasksResponse(string $output): ?array
    {
        $decoded = $this->decodeBestEffortJson($output);

        if (! is_array($decoded)) {
            return null;
        }

        $tasks = is_array($decoded['tasks'] ?? null) ? array_values($decoded['tasks']) : [];
        if ($tasks === []) {
            return null;
        }

        $normalizedTasks = [];

        foreach ($tasks as $index => $task) {
            if (! is_array($task)) {
                continue;
            }

            $title = trim((string) ($task['title'] ?? $task['name'] ?? ''));
            if ($title === '') {
                $title = 'Task '.($index + 1);
            }

            $description = trim((string) ($task['description'] ?? ''));
            $instructions = trim((string) ($task['instructions_markdown'] ?? $task['instructions'] ?? $description));
            $order = (int) ($task['sequence'] ?? $task['order'] ?? ($index + 1));

            $normalizedTasks[] = [
                'sequence' => max(1, $order),
                'title' => $title,
                'description' => $description,
                'instructions_markdown' => $instructions,
            ];
        }

        if ($normalizedTasks === []) {
            return null;
        }

        usort($normalizedTasks, static fn (array $a, array $b): int => $a['sequence'] <=> $b['sequence']);
        $normalizedTasks = array_values(array_map(static function (array $task, int $index): array {
            $task['sequence'] = $index + 1;

            return $task;
        }, $normalizedTasks, array_keys($normalizedTasks)));

        return [
            'tasks' => $normalizedTasks,
        ];
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

    private function executable(): string
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

    private function model(): string
    {
        return trim((string) (
            config('agent.interrogation.codex_model')
            ?: config('agent.runner_models.codex')
        ));
    }

    private function questionSchema(): string
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

    private function questionBankSchema(): string
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

    private function summarySchema(): string
    {
        return json_encode([
            'type' => 'object',
            'required' => ['summary_markdown', 'goals', 'constraints', 'acceptance_criteria', 'open_questions', 'private_notes'],
            'additionalProperties' => false,
            'properties' => [
                'summary_markdown' => ['type' => 'string'],
                'goals' => ['type' => 'array', 'items' => ['type' => 'string']],
                'constraints' => ['type' => 'array', 'items' => ['type' => 'string']],
                'acceptance_criteria' => ['type' => 'array', 'items' => ['type' => 'string']],
                'open_questions' => ['type' => 'array', 'items' => ['type' => 'string']],
                'private_notes' => ['type' => 'string'],
            ],
        ], JSON_UNESCAPED_SLASHES) ?: '{}';
    }

    private function planSchema(): string
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

    private function buildTasksSchema(): string
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
    private function decodeBestEffortJson(string $output): ?array
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

        $bucket[] = [
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
