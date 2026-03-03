<?php

namespace App\Support\Interrogation\Adapters;

use App\Models\InterrogationSession;
use App\Support\Interrogation\Contracts\InterrogationRunnerAdapter;

class ClaudeAdapter implements InterrogationRunnerAdapter
{
    /**
     * @return array<int, string>
     */
    public function buildDiscoveryCommand(InterrogationSession $session, string $discoveryPrompt, string $systemPrompt): array
    {
        return [
            $this->executable(),
            '-p',
            '--verbose',
            '--output-format',
            'stream-json',
            '--system-prompt',
            $systemPrompt,
            '--tools=Read,Glob,Grep',
            $discoveryPrompt,
        ];
    }

    /**
     * @return array<int, string>
     */
    public function buildQuestionCommand(InterrogationSession $session, string $userMessage, string $systemPrompt): array
    {
        $command = [
            $this->executable(),
            '-p',
        ];

        if (is_string($session->cli_session_id) && $session->cli_session_id !== '') {
            $command[] = '--resume';
            $command[] = $session->cli_session_id;
        }

        $command[] = '--output-format';
        $command[] = 'json';
        $command[] = '--json-schema';
        $command[] = $this->questionSchema();
        $command[] = '--system-prompt';
        $command[] = $systemPrompt;
        $command[] = '--tools=Read,Glob,Grep';
        $command[] = $userMessage;

        return $command;
    }

    /**
     * @return array<int, string>
     */
    public function buildQuestionBankCommand(InterrogationSession $session, string $userMessage, string $systemPrompt): array
    {
        $command = [
            $this->executable(),
            '-p',
        ];

        if (is_string($session->cli_session_id) && $session->cli_session_id !== '') {
            $command[] = '--resume';
            $command[] = $session->cli_session_id;
        }

        $command[] = '--output-format';
        $command[] = 'json';
        $command[] = '--json-schema';
        $command[] = $this->questionBankSchema();
        $command[] = '--system-prompt';
        $command[] = $systemPrompt;
        $command[] = '--tools=Read,Glob,Grep';
        $command[] = $userMessage;

        return $command;
    }

    /**
     * @return array<int, string>
     */
    public function buildSummaryCommand(InterrogationSession $session, string $summaryPrompt, string $systemPrompt): array
    {
        $command = [
            $this->executable(),
            '-p',
        ];

        if (is_string($session->cli_session_id) && $session->cli_session_id !== '') {
            $command[] = '--resume';
            $command[] = $session->cli_session_id;
        }

        $command[] = '--output-format';
        $command[] = 'json';
        $command[] = '--json-schema';
        $command[] = $this->summarySchema();
        $command[] = '--system-prompt';
        $command[] = $systemPrompt;
        $command[] = '--tools=Read,Glob,Grep';
        $command[] = $summaryPrompt;

        return $command;
    }

    /**
     * @return array<int, string>
     */
    public function buildPlanCommand(InterrogationSession $session, string $planningPrompt, string $systemPrompt): array
    {
        $command = [
            $this->executable(),
            '-p',
        ];

        if (is_string($session->cli_session_id) && $session->cli_session_id !== '') {
            $command[] = '--resume';
            $command[] = $session->cli_session_id;
        }

        $command[] = '--output-format';
        $command[] = 'json';
        $command[] = '--json-schema';
        $command[] = $this->planSchema();
        $command[] = '--system-prompt';
        $command[] = $systemPrompt;
        $command[] = '--tools=Read,Glob,Grep,Write,Edit';
        $command[] = $planningPrompt;

        return $command;
    }

    /**
     * @return array<int, string>
     */
    public function buildBuildTasksCommand(InterrogationSession $session, string $prompt, string $systemPrompt): array
    {
        $command = [
            $this->executable(),
            '-p',
        ];

        if (is_string($session->cli_session_id) && $session->cli_session_id !== '') {
            $command[] = '--resume';
            $command[] = $session->cli_session_id;
        }

        $command[] = '--output-format';
        $command[] = 'json';
        $command[] = '--json-schema';
        $command[] = $this->buildTasksSchema();
        $command[] = '--system-prompt';
        $command[] = $systemPrompt;
        $command[] = '--tools=Read,Glob,Grep';
        $command[] = $prompt;

        return $command;
    }

    /**
     * @return array<int, string>
     */
    public function buildReconstructCommand(InterrogationSession $session, string $conversationHistory, string $systemPrompt): array
    {
        return [
            $this->executable(),
            '-p',
            '--output-format',
            'json',
            '--json-schema',
            $this->questionSchema(),
            '--system-prompt',
            $systemPrompt,
            '--tools=Read,Glob,Grep',
            $conversationHistory,
        ];
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

        $decoded = json_decode($line, true);

        if (! is_array($decoded)) {
            return null;
        }

        $payload = [
            'source' => 'claude',
            'message' => $this->extractStreamMessage($decoded),
        ];

        if (isset($decoded['session_id']) && is_string($decoded['session_id'])) {
            $payload['cli_session_id'] = $decoded['session_id'];
        }

        if (isset($decoded['id']) && is_string($decoded['id'])) {
            $payload['event_id'] = $decoded['id'];
        }

        $type = (string) ($decoded['type'] ?? 'message');

        return [
            'type' => $type,
            'payload' => $payload,
        ];
    }

    /**
     * @param  array<string, mixed>  $decoded
     */
    private function extractStreamMessage(array $decoded): string
    {
        $type = (string) ($decoded['type'] ?? '');

        if ($type === 'assistant') {
            return $this->extractAssistantStreamMessage($decoded);
        }

        if ($type === 'user') {
            return $this->extractToolResultMessage($decoded);
        }

        if ($type === 'result') {
            $result = $decoded['result'] ?? $decoded['message'] ?? $decoded['content'] ?? null;

            return $this->flattenStreamValue($result, 320);
        }

        if ($type === 'system') {
            $subtype = (string) ($decoded['subtype'] ?? '');

            if ($subtype === 'init') {
                return 'Discovery session initialized.';
            }
        }

        foreach (['text', 'message', 'content', 'delta'] as $key) {
            if (! array_key_exists($key, $decoded)) {
                continue;
            }

            $message = $this->flattenStreamValue($decoded[$key], 320);
            if ($message !== '') {
                return $message;
            }
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $decoded
     */
    private function extractAssistantStreamMessage(array $decoded): string
    {
        $message = is_array($decoded['message'] ?? null) ? $decoded['message'] : [];
        $content = is_array($message['content'] ?? null) ? $message['content'] : [];

        $fragments = [];

        foreach ($content as $block) {
            if (! is_array($block)) {
                continue;
            }

            $blockType = (string) ($block['type'] ?? '');

            if ($blockType === 'text') {
                $text = $this->flattenStreamValue($block['text'] ?? null, 320);
                if ($text !== '') {
                    $fragments[] = $text;
                }

                continue;
            }

            if ($blockType === 'tool_use') {
                $toolMessage = $this->formatToolUseMessage($block);
                if ($toolMessage !== '') {
                    $fragments[] = $toolMessage;
                }
            }
        }

        $fragments = array_values(array_unique($fragments));

        return $fragments === [] ? '' : implode("\n", $fragments);
    }

    /**
     * @param  array<string, mixed>  $decoded
     */
    private function extractToolResultMessage(array $decoded): string
    {
        $toolUseResult = is_array($decoded['tool_use_result'] ?? null) ? $decoded['tool_use_result'] : null;

        if ($toolUseResult !== null && isset($toolUseResult['numFiles'])) {
            $count = (int) $toolUseResult['numFiles'];
            $truncated = ((bool) ($toolUseResult['truncated'] ?? false)) ? ' (truncated)' : '';

            return sprintf('Tool result: %d file%s%s.', $count, $count === 1 ? '' : 's', $truncated);
        }

        $message = is_array($decoded['message'] ?? null) ? $decoded['message'] : [];
        $content = is_array($message['content'] ?? null) ? $message['content'] : [];

        foreach ($content as $block) {
            if (! is_array($block) || (string) ($block['type'] ?? '') !== 'tool_result') {
                continue;
            }

            $candidate = $this->flattenStreamValue($block['content'] ?? null, 320);
            $candidate = trim((string) preg_replace('/<[^>]+>/', '', $candidate));

            if ($candidate === '' || stripos($candidate, 'Sibling tool call errored') !== false) {
                return '';
            }

            if ($candidate !== '' && $this->isLikelyToolError($candidate)) {
                return 'Tool error: '.$candidate;
            }
        }

        return '';
    }

    private function isLikelyToolError(string $message): bool
    {
        $normalized = trim($message);

        if ($normalized === '') {
            return false;
        }

        // Avoid misclassifying code snippets (e.g. "ErrorEnvelope") as tool errors.
        if (preg_match('/^\d+→<\?php/i', $normalized) === 1 || str_starts_with($normalized, '<?php')) {
            return false;
        }

        return preg_match('/\b(?:error|failed|denied|not[_\s-]?available)\b/i', $normalized) === 1;
    }

    /**
     * @param  array<string, mixed>  $block
     */
    private function formatToolUseMessage(array $block): string
    {
        $name = (string) ($block['name'] ?? 'tool');
        $input = is_array($block['input'] ?? null) ? $block['input'] : [];

        return match ($name) {
            'Read' => isset($input['file_path']) && is_string($input['file_path'])
                ? 'Reading '.basename($input['file_path'])
                : 'Reading file',
            'Glob' => isset($input['pattern']) && is_string($input['pattern'])
                ? 'Searching files: '.$input['pattern']
                : 'Searching files',
            'Grep' => isset($input['pattern']) && is_string($input['pattern'])
                ? 'Scanning text: '.$input['pattern']
                : 'Scanning text',
            default => 'Using tool: '.$name,
        };
    }

    private function flattenStreamValue(mixed $value, int $maxLength = 600): string
    {
        if (is_string($value)) {
            $text = $this->sanitizeUtf8(trim($value));

            return mb_substr($text, 0, $maxLength);
        }

        if (is_bool($value) || is_int($value) || is_float($value)) {
            return (string) $value;
        }

        if (! is_array($value)) {
            return '';
        }

        $fragments = [];

        if (isset($value['text'])) {
            $text = $this->flattenStreamValue($value['text'], $maxLength);
            if ($text !== '') {
                $fragments[] = $text;
            }
        }

        if (isset($value['content'])) {
            $content = $this->flattenStreamValue($value['content'], $maxLength);
            if ($content !== '') {
                $fragments[] = $content;
            }
        }

        if (isset($value['message'])) {
            $message = $this->flattenStreamValue($value['message'], $maxLength);
            if ($message !== '') {
                $fragments[] = $message;
            }
        }

        foreach ($value as $item) {
            $fragment = $this->flattenStreamValue($item, $maxLength);
            if ($fragment !== '') {
                $fragments[] = $fragment;
            }
        }

        $fragments = array_values(array_unique($fragments));

        if ($fragments !== []) {
            return mb_substr(implode("\n", $fragments), 0, $maxLength);
        }

        $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return is_string($encoded) ? mb_substr($encoded, 0, $maxLength) : '';
    }

    private function sanitizeUtf8(string $value): string
    {
        if (mb_check_encoding($value, 'UTF-8')) {
            return $value;
        }

        $converted = @iconv('UTF-8', 'UTF-8//IGNORE', $value);
        if (is_string($converted) && $converted !== '') {
            return $converted;
        }

        return mb_convert_encoding($value, 'UTF-8', 'UTF-8');
    }

    /**
     * @return array<string, mixed>|null
     */
    public function parseQuestionResponse(string $output): ?array
    {
        $decoded = $this->decodeStructuredOutput($output);

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
                : null,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function parseQuestionBankResponse(string $output): ?array
    {
        $decoded = $this->decodeStructuredOutput($output);

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
    public function parsePlanResponse(string $output): ?array
    {
        $decoded = $this->decodeStructuredOutput($output);

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
        $decoded = $this->decodeStructuredOutput($output);

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
     * @return array<string, string|bool>
     */
    public function buildEnvironment(InterrogationSession $session): array
    {
        $env = [];

        foreach ($_ENV as $key => $value) {
            if (is_string($key) && is_scalar($value)) {
                $env[$key] = (string) $value;
            }
        }

        $env['INTERROGATION_SESSION_ID'] = (string) $session->id;

        return $env;
    }

    private function executable(): string
    {
        return (string) (config('agent.runner_executables.claude') ?: 'claude');
    }

    private function questionSchema(): string
    {
        return json_encode([
            'type' => 'object',
            'required' => ['question_text', 'answer_type', 'progress_estimate', 'reasoning'],
            'additionalProperties' => false,
            'properties' => [
                'question_id' => ['type' => 'string'],
                'canonical_key' => ['type' => 'string'],
                'question_text' => ['type' => 'string'],
                'answer_type' => ['type' => 'string', 'enum' => ['choice', 'freetext', 'skip_allowed']],
                'options' => ['type' => 'array', 'items' => ['type' => 'string']],
                'reasoning' => ['type' => 'string', 'minLength' => 1],
                'category' => ['type' => 'string'],
                'progress_estimate' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 100],
                'is_complete' => ['type' => 'boolean'],
                'cli_session_id' => ['type' => 'string'],
            ],
        ], JSON_UNESCAPED_SLASHES) ?: '{}';
    }

    private function questionBankSchema(): string
    {
        return json_encode([
            'type' => 'object',
            'required' => ['questions'],
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
            'required' => ['plan_markdown'],
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
                        'required' => ['title'],
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

    /**
     * Returns the JSON schema for adversarial reviewer responses.
     *
     * @return array<string, mixed>
     */
    public function reviewerSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'verdict' => [
                    'type' => 'string',
                    'enum' => ['pass', 'revise', 'needs_clarification'],
                    'description' => 'Review verdict',
                ],
                'issues' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'type' => [
                                'type' => 'string',
                                'enum' => ['missing_requirement', 'contradiction', 'ambiguity', 'weak_acceptance_criteria', 'scope_drift', 'unresolved_dependency'],
                            ],
                            'severity' => [
                                'type' => 'string',
                                'enum' => ['low', 'medium', 'high', 'critical'],
                            ],
                            'message' => ['type' => 'string'],
                            'evidence' => ['type' => 'string'],
                        ],
                        'required' => ['type', 'severity', 'message'],
                    ],
                ],
                'required_changes' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                ],
                'clarification_questions' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                    'maxItems' => 3,
                ],
                'confidence' => [
                    'type' => 'number',
                    'minimum' => 0,
                    'maximum' => 1,
                ],
                'review_notes' => ['type' => 'string'],
            ],
            'required' => ['verdict', 'issues', 'confidence'],
        ];
    }

    /**
     * Builds the CLI command array for running an adversarial review.
     *
     * @return array<int, string>
     */
    public function buildReviewerCommand(string $projectDirectory, string $prompt): array
    {
        $schemaJson = json_encode($this->reviewerSchema(), JSON_UNESCAPED_SLASHES);

        return [
            $this->executable(),
            '-p',
            '--output-format',
            'json',
            '--json-schema',
            $schemaJson !== false ? $schemaJson : '{}',
            '--tools=Read,Glob,Grep',
            $prompt,
        ];
    }

    /**
     * Parses the raw output from an adversarial review subprocess.
     *
     * @return array<string, mixed>
     *
     * @throws \RuntimeException if the response cannot be parsed or lacks a verdict
     */
    public function parseReviewerResponse(string $rawOutput): array
    {
        $rawOutput = trim($rawOutput);

        if ($rawOutput === '') {
            throw new \RuntimeException('Failed to parse reviewer response: empty output');
        }

        // Handle stream-json format: look for verdict in multiline output
        $lines = explode("\n", $rawOutput);

        foreach (array_reverse($lines) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $decoded = json_decode($line, true);
            if (! is_array($decoded)) {
                continue;
            }

            // Direct verdict payload
            if (isset($decoded['verdict']) && is_string($decoded['verdict'])) {
                return $decoded;
            }

            // Wrapped in result key
            if (isset($decoded['result']) && is_array($decoded['result']) && isset($decoded['result']['verdict'])) {
                return $decoded['result'];
            }

            // Wrapped in structured_output key
            if (isset($decoded['structured_output']) && is_array($decoded['structured_output']) && isset($decoded['structured_output']['verdict'])) {
                return $decoded['structured_output'];
            }
        }

        // Try parsing entire output as single JSON object
        $decoded = json_decode($rawOutput, true);
        if (is_array($decoded)) {
            if (isset($decoded['verdict']) && is_string($decoded['verdict'])) {
                return $decoded;
            }
            if (isset($decoded['result']) && is_array($decoded['result']) && isset($decoded['result']['verdict'])) {
                return $decoded['result'];
            }
            if (isset($decoded['structured_output']) && is_array($decoded['structured_output']) && isset($decoded['structured_output']['verdict'])) {
                return $decoded['structured_output'];
            }
        }

        throw new \RuntimeException('Failed to parse reviewer response: '.json_last_error_msg());
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

        $decoded = json_decode($output, true);

        if (is_array($decoded)) {
            return $decoded;
        }

        foreach (preg_split('/\R/', $output) ?: [] as $line) {
            $line = trim((string) $line);
            if ($line === '') {
                continue;
            }

            $lineDecoded = json_decode($line, true);
            if (is_array($lineDecoded)) {
                return $lineDecoded;
            }
        }

        return null;
    }
}
