<?php

declare(strict_types=1);

namespace App\Services\Messenger;

use App\DTOs\Messenger\ParsedAction;
use App\Enums\Messenger\ChatActionType;
use App\Enums\Runtime\RuntimeMode;
use App\Models\ChatAttachment;
use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Services\Security\MessengerSecurityGuard;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

class ChatIntentParser
{
    private const CONFIDENCE_THRESHOLD = 0.7;

    public function __construct(
        private ?MessengerSecurityGuard $securityGuard = null,
    ) {}

    /**
     * Parse a chat message into a structured action.
     */
    public function parse(
        ChatMessage $message,
        ChatSession $session,
    ): ?ParsedAction {
        $content = trim($message->content);

        if ($content === '') {
            return null;
        }

        $patternResult = $this->tryPatternMatch($content);
        if ($patternResult !== null) {
            return $patternResult;
        }

        $attachmentContext = $this->buildAttachmentContext($message);

        $aiResult = $this->parseWithAI($content, $session, $attachmentContext);

        if ($aiResult !== null) {
            return $aiResult;
        }

        return new ParsedAction(
            type: ChatActionType::GENERAL_TASK,
            parameters: [
                'task_description' => $content,
                'original_message' => $content,
                'attachment_context' => $attachmentContext,
            ],
            confidence: 0.8,
            requiresConfirmation: false,
            rawIntent: $content,
        );
    }

    /**
     * Try to match common command patterns without AI.
     */
    private function tryPatternMatch(string $content): ?ParsedAction
    {
        $normalized = strtolower(trim($content));

        // Simple list commands
        if (preg_match('/^(list|show|get)\s*(my\s+)?((active|enabled|disabled|paused)\s+)?(jobs?|cron)$/i', $normalized, $matches)) {
            $filterKeyword = strtolower(trim((string) ($matches[3] ?? ''))); // @phpstan-ignore nullCoalesce.offset
            $parameters = [];

            if ($filterKeyword !== '') {
                $parameters['filter'] = $filterKeyword;
            }

            return new ParsedAction(
                type: ChatActionType::JOBS_LIST,
                parameters: $parameters,
                confidence: 1.0,
                requiresConfirmation: false,
                rawIntent: $content,
            );
        }

        if (preg_match('/^(list|show|get)\s*(active\s+)?(runs?|running)$/i', $normalized)) {
            return new ParsedAction(
                type: ChatActionType::RUNS_LIST_ACTIVE,
                parameters: [],
                confidence: 1.0,
                requiresConfirmation: false,
                rawIntent: $content,
            );
        }

        // Stop run commands
        if (preg_match('/^stop\s+(?:run\s+)?([a-z0-9_-]+)$/i', $normalized, $matches)) {
            return new ParsedAction(
                type: ChatActionType::RUNS_STOP,
                parameters: ['run_id' => $matches[1]],
                confidence: 0.95,
                requiresConfirmation: true, // Always require confirmation for stop
                rawIntent: $content,
            );
        }

        // Retry run commands
        if (preg_match('/^retry\s+(?:run\s+)?([a-z0-9_-]+)$/i', $normalized, $matches)) {
            return new ParsedAction(
                type: ChatActionType::RUNS_RETRY,
                parameters: ['run_id' => $matches[1]],
                confidence: 0.95,
                requiresConfirmation: false,
                rawIntent: $content,
            );
        }

        // Run now commands
        if (preg_match('/^run\s+(?:job\s+)?([a-z0-9_-]+)(?:\s+now)?$/i', $normalized, $matches)) {
            return new ParsedAction(
                type: ChatActionType::RUNS_RUN_NOW,
                parameters: ['job_id' => $matches[1]],
                confidence: 0.95,
                requiresConfirmation: false,
                rawIntent: $content,
            );
        }

        // Run history commands
        if (preg_match('/^(show|list|get)\s+(run\s+)?history(\s+(?:for\s+)?(?:job\s+)?([a-z0-9_-]+))?$/i', $normalized, $matches)) {
            $parameters = [];
            if (isset($matches[4]) && $matches[4] !== '') { // @phpstan-ignore notIdentical.alwaysTrue
                $parameters['job_id'] = $matches[4];
            }

            return new ParsedAction(
                type: ChatActionType::RUNS_LIST_HISTORY,
                parameters: $parameters,
                confidence: 0.95,
                requiresConfirmation: false,
                rawIntent: $content,
            );
        }

        // "did job X complete" / "did jobs X and Y complete" patterns
        if (preg_match('/^(?:did|has|have)\s+jobs?\s+([\d,\s]+(?:and\s+\d+)?)\s+(?:complete|finish|succeed|run|pass)/i', $normalized, $matches)) {
            $jobIds = preg_split('/[\s,]+(?:and\s+)?/', trim($matches[1]));
            $jobIds = array_filter(array_map('trim', $jobIds ?: []), fn ($id) => $id !== '');

            if (count($jobIds) === 1) {
                return new ParsedAction(
                    type: ChatActionType::RUNS_LIST_HISTORY,
                    parameters: ['job_id' => $jobIds[0]],
                    confidence: 0.9,
                    requiresConfirmation: false,
                    rawIntent: $content,
                );
            }

            return new ParsedAction(
                type: ChatActionType::RUNS_LIST_HISTORY,
                parameters: ['job_ids' => $jobIds],
                confidence: 0.9,
                requiresConfirmation: false,
                rawIntent: $content,
            );
        }

        // Show specific job details
        if (preg_match('/^(?:show|get|describe|info)\s+(?:job\s+)(\d+)$/i', $normalized, $matches)) {
            return new ParsedAction(
                type: ChatActionType::JOBS_SHOW,
                parameters: ['job_id' => $matches[1]],
                confidence: 0.95,
                requiresConfirmation: false,
                rawIntent: $content,
            );
        }

        // Show specific run details
        if (preg_match('/^(?:show|get|describe|info)\s+(?:run\s+)(\d+)$/i', $normalized, $matches)) {
            return new ParsedAction(
                type: ChatActionType::RUNS_SHOW,
                parameters: ['run_id' => $matches[1]],
                confidence: 0.95,
                requiresConfirmation: false,
                rawIntent: $content,
            );
        }

        return null;
    }

    /**
     * Parse intent using AI (Claude CLI).
     */
    private function parseWithAI(string $content, ChatSession $session, string $attachmentContext = ''): ?ParsedAction
    {
        $sessionHistory = $this->buildSessionContext($session);
        $prompt = $this->buildParsingPrompt($content, $sessionHistory, $attachmentContext);

        try {
            $result = Process::timeout(30)->run([
                $this->executable(),
                '-p',
                '--output-format',
                'json',
                '--json-schema',
                $this->actionSchema(),
                $prompt,
            ]);

            if (! $result->successful()) {
                Log::warning('ChatIntentParser: AI parsing failed', [
                    'session_id' => $session->id,
                    'error' => $result->errorOutput(),
                ]);

                return null;
            }

            return $this->parseAIResponse($result->output(), $content, $session);

        } catch (\Throwable $e) {
            Log::error('ChatIntentParser: Exception during AI parsing', [
                'session_id' => $session->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Build the prompt for AI intent parsing.
     */
    private function buildParsingPrompt(string $content, string $sessionHistory, string $attachmentContext = ''): string
    {
        $availableActions = implode(', ', ChatActionType::values());

        $attachmentSection = $attachmentContext !== ''
            ? "\nAttached files:\n{$attachmentContext}\n"
            : '';

        return <<<PROMPT
You are an intent parser for an agent operations system. Parse the user's message into a structured action.

Available actions: {$availableActions}

Action descriptions:
- jobs.list: List all configured jobs
- jobs.create: Create a new scheduled job (requires name; runner_type, working_directory, task_brief, schedule optional)
- jobs.update: Update an existing job configuration
- jobs.delete: Permanently delete a job
- jobs.show: Show detailed information about a specific job (requires job_id)
- runs.list_active: List currently active runs
- runs.list_history: List run history for a job or all jobs (optional: job_id, status, limit)
- runs.show: Show detailed information about a specific run (requires run_id)
- runs.stop: Stop a running process (requires run_id)
- runs.run_now: Trigger immediate execution of a job (requires job_id)
- runs.retry: Retry a failed or stopped run (requires run_id)
- runs.steer: Provide guidance to a running process (requires run_id, guidance)
- general.task: Handle any general question, request, or task that doesn't fit the above categories

IMPORTANT: If the user's message is a general question, greeting, request for help, capability inquiry, or any task that doesn't clearly map to a specific job/run action, use general.task with task_description set to the user's full message. Do NOT return clarification_needed for general questions — use general.task instead.

Session context:
{$sessionHistory}
{$attachmentSection}
User message: {$content}

Parse this message and return the structured action. If the user has attached files, consider their content as part of the request context. If the intent could be a specific job/run action but is genuinely ambiguous between two specific actions, set clarification_needed. Set confidence to a value between 0 and 1.
PROMPT;
    }

    private function buildAttachmentContext(ChatMessage $message): string
    {
        $attachments = $message->attachments()->get();

        if ($attachments->isEmpty()) {
            return '';
        }

        $context = [];
        $disk = Storage::disk(config('messenger.attachment_config.storage_disk', 'local'));
        $maxInlineBytes = 50_000;

        foreach ($attachments as $attachment) {
            /** @var ChatAttachment $attachment */
            $entry = sprintf(
                '- %s (%s, %s bytes)',
                $attachment->filename,
                $attachment->mime_type,
                number_format($attachment->size_bytes)
            );

            if ($this->isReadableTextMime($attachment->mime_type) && $attachment->size_bytes <= $maxInlineBytes) {
                try {
                    $contents = $disk->get($attachment->storage_path);
                    if ($contents !== null && mb_check_encoding($contents, 'UTF-8')) {
                        if ($this->securityGuard !== null) {
                            $scanResult = $this->securityGuard->scanAttachment($contents, RuntimeMode::Safe);
                            if ($scanResult->sanitized) {
                                $contents = $scanResult->content;
                            }
                        }
                        $entry .= "\n```\n".trim($contents)."\n```";
                    }
                } catch (\Throwable $e) {
                    Log::debug('ChatIntentParser: Could not read attachment content', [
                        'attachment_id' => $attachment->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $context[] = $entry;
        }

        return implode("\n", $context);
    }

    private function isReadableTextMime(string $mimeType): bool
    {
        if (str_starts_with($mimeType, 'text/')) {
            return true;
        }

        $readableMimes = [
            'application/json',
            'application/xml',
            'application/x-yaml',
            'application/yaml',
            'application/javascript',
            'application/typescript',
            'application/x-sh',
            'application/csv',
            'application/sql',
            'application/x-httpd-php',
        ];

        return in_array($mimeType, $readableMimes, true);
    }

    /**
     * Build session context from recent messages.
     */
    private function buildSessionContext(ChatSession $session): string
    {
        /** @var Collection<int, ChatMessage> $messages */
        $messages = $session->messages()
            ->orderByDesc('created_at')
            ->limit(5)
            ->get()
            ->reverse();

        if ($messages->isEmpty()) {
            return 'No previous messages in this session.';
        }

        $context = [];
        foreach ($messages as $message) {
            $direction = $message->direction === ChatMessage::DIRECTION_INBOUND ? 'User' : 'Bot';
            $context[] = "{$direction}: {$message->content}";
        }

        return implode("\n", $context);
    }

    /**
     * Parse the AI response into a ParsedAction.
     */
    private function parseAIResponse(
        string $output,
        string $rawIntent,
        ChatSession $session
    ): ?ParsedAction {
        $output = trim($output);

        if ($output === '') {
            return null;
        }

        $decoded = json_decode($output, true);

        if (! is_array($decoded)) {
            // Try to extract JSON from the output
            $decoded = $this->extractJsonFromOutput($output);
            if ($decoded === null) {
                return null;
            }
        }

        // Handle structured output wrapper
        if (isset($decoded['structured_output']) && is_array($decoded['structured_output'])) {
            $decoded = $decoded['structured_output'];
        }

        if (isset($decoded['result']) && is_array($decoded['result'])) {
            $decoded = $decoded['result'];
        }

        // Check for clarification needed
        $clarification = $decoded['clarification_needed'] ?? null;
        if (is_string($clarification) && $clarification !== '') {
            return ParsedAction::needsClarification($rawIntent, $clarification);
        }

        // Validate action type
        $actionValue = $decoded['action'] ?? null;
        if (! is_string($actionValue)) {
            return null;
        }

        try {
            $actionType = ChatActionType::from($actionValue);
        } catch (\ValueError $e) {
            Log::warning('ChatIntentParser: Invalid action type from AI', [
                'action' => $actionValue,
                'session_id' => $session->id,
            ]);

            return null;
        }

        $confidence = (float) ($decoded['confidence'] ?? 0.5);
        $parameters = is_array($decoded['parameters'] ?? null) ? $decoded['parameters'] : [];

        // Check confidence threshold
        if ($confidence < self::CONFIDENCE_THRESHOLD) {
            return ParsedAction::needsClarification(
                $rawIntent,
                "I'm not sure what you mean. Could you please rephrase or provide more details about what you'd like to do?"
            );
        }

        $account = $session->connectorAccount;
        $config = $account->config ?? [];

        return new ParsedAction(
            type: $actionType,
            parameters: $parameters,
            confidence: $confidence,
            requiresConfirmation: $actionType->requiresConfirmation($config),
            rawIntent: $rawIntent,
        );
    }

    /**
     * Try to extract JSON from mixed output.
     *
     * @return array<string, mixed>|null
     */
    private function extractJsonFromOutput(string $output): ?array
    {
        // Try each line
        foreach (preg_split('/\R/', $output) ?: [] as $line) {
            $line = trim((string) $line);
            if ($line === '') {
                continue;
            }

            $decoded = json_decode($line, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return null;
    }

    /**
     * Get the Claude CLI executable path.
     */
    private function executable(): string
    {
        return (string) (config('agent.runner_executables.claude') ?: 'claude');
    }

    /**
     * Get the JSON schema for action parsing.
     */
    private function actionSchema(): string
    {
        return json_encode([
            'type' => 'object',
            'required' => ['action', 'parameters', 'confidence'],
            'additionalProperties' => false,
            'properties' => [
                'action' => [
                    'type' => 'string',
                    'enum' => ChatActionType::values(),
                ],
                'parameters' => [
                    'type' => 'object',
                    'additionalProperties' => true,
                ],
                'confidence' => [
                    'type' => 'number',
                    'minimum' => 0,
                    'maximum' => 1,
                ],
                'clarification_needed' => [
                    'type' => 'string',
                ],
            ],
        ], JSON_UNESCAPED_SLASHES) ?: '{}';
    }
}
