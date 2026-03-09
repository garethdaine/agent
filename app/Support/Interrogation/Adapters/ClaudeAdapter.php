<?php

declare(strict_types=1);

namespace App\Support\Interrogation\Adapters;

use App\Models\InterrogationSession;
use App\Support\Agent\DatabaseIsolationEnvironment;

class ClaudeAdapter extends AbstractBuildAdapter
{
    /**
     * @return array<int, string>
     */
    public function buildDiscoveryCommand(InterrogationSession $session, string $discoveryPrompt, string $systemPrompt): array
    {
        return [
            ...$this->baseCommand(),
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
            ...$this->baseCommand(),
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
            ...$this->baseCommand(),
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
            ...$this->baseCommand(),
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
            ...$this->baseCommand(),
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
            ...$this->baseCommand(),
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
            ...$this->baseCommand(),
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

        // Extract usage metadata when present (turn.completed, message events)
        $usage = $this->extractUsageFromEvent($decoded);
        if ($usage !== null) {
            $payload['usage'] = $usage;
        }

        return [
            'type' => $type,
            'payload' => $payload,
        ];
    }

    /**
     * Extract token usage from a stream event when present.
     *
     * @param  array<string, mixed>  $decoded
     * @return array{input_tokens: int, output_tokens: int, cached_input_tokens: int}|null
     */
    private function extractUsageFromEvent(array $decoded): ?array
    {
        $usage = $decoded['usage'] ?? null;

        if (! is_array($usage)) {
            $message = is_array($decoded['message'] ?? null) ? $decoded['message'] : [];
            $usage = $message['usage'] ?? null;
        }

        if (! is_array($usage)) {
            return null;
        }

        $input = (int) ($usage['input_tokens'] ?? 0);
        $output = (int) ($usage['output_tokens'] ?? 0);

        if ($input === 0 && $output === 0) {
            return null;
        }

        return [
            'input_tokens' => $input,
            'output_tokens' => $output,
            'cached_input_tokens' => (int) ($usage['cache_read_input_tokens'] ?? $usage['cached_input_tokens'] ?? 0),
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

            if ($candidate !== '' && $this->isLikelyToolError($candidate)) { // @phpstan-ignore notIdentical.alwaysTrue
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
            ...$this->baseCommand(),
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
     * @return array<string, string|bool>
     */
    public function buildEnvironment(InterrogationSession $session): array
    {
        $env = DatabaseIsolationEnvironment::build($_ENV);
        $env['INTERROGATION_SESSION_ID'] = (string) $session->id;

        return $env;
    }

    protected function executable(): string
    {
        return (string) (config('agent.runner_executables.claude') ?: 'claude');
    }

    /**
     * @return array<int, string>
     */
    private function baseCommand(): array
    {
        $command = [$this->executable()];

        $model = $this->model();
        if ($model !== '') {
            $command[] = '--model';
            $command[] = $model;
        }

        return $command;
    }

    protected function model(): string
    {
        if ($this->modelOverride !== null && $this->modelOverride !== '') {
            return $this->modelOverride;
        }

        return trim((string) (
            config('agent.interrogation.claude_model')
            ?: config('agent.runner_models.claude')
        ));
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
