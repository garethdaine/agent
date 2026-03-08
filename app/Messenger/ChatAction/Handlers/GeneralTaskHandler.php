<?php

declare(strict_types=1);

namespace App\Messenger\ChatAction\Handlers;

use App\Messenger\ChatAction\ChatActionContext;
use App\Messenger\ChatAction\ChatActionResult;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

class GeneralTaskHandler implements StreamableHandlerInterface
{
    public function handle(ChatActionContext $context): ChatActionResult
    {
        [$systemPrompt, $fullPrompt] = $this->buildPrompts($context);

        if ($fullPrompt === null) {
            return ChatActionResult::failure('No task description provided.');
        }

        try {
            $timeout = (int) config('messenger.general_task_cli_timeout_seconds', 45);
            $result = Process::timeout($timeout)->run([
                $this->executable(),
                '-p',
                '--system-prompt',
                $systemPrompt,
                $fullPrompt,
            ]);

            if (! $result->successful()) {
                Log::warning('GeneralTaskHandler: Claude CLI failed', [
                    'error' => $result->errorOutput(),
                ]);

                return ChatActionResult::failure(
                    'I encountered an issue processing your request. Please try again.'
                );
            }

            $response = trim($result->output());

            if ($response === '') {
                return ChatActionResult::failure(
                    'I received an empty response. Could you rephrase your question?'
                );
            }

            return ChatActionResult::success($response);

        } catch (\Throwable $e) {
            Log::error('GeneralTaskHandler: Exception', [
                'error' => $e->getMessage(),
            ]);

            return ChatActionResult::failure(
                'Something went wrong processing your request. Please try again.'
            );
        }
    }

    public function handleStreaming(ChatActionContext $context, callable $onChunk): ChatActionResult
    {
        [$systemPrompt, $fullPrompt] = $this->buildPrompts($context);

        if ($fullPrompt === null) {
            return ChatActionResult::failure('No task description provided.');
        }

        try {
            $timeout = (int) config('messenger.general_task_cli_timeout_seconds', 45);
            $process = Process::timeout($timeout)->start([
                $this->executable(),
                '-p',
                '--system-prompt',
                $systemPrompt,
                $fullPrompt,
            ]);

            $streamedLength = 0;

            while ($process->running()) {
                $latest = $process->latestOutput();

                if ($latest !== '') {
                    $streamedLength += strlen($latest);
                    $onChunk($latest);
                }

                usleep(100_000);
            }

            $result = $process->wait();
            $totalOutput = $result->output();

            if (strlen($totalOutput) > $streamedLength) {
                $remaining = substr($totalOutput, $streamedLength);
                $onChunk($remaining);
            }

            if (! $result->successful()) {
                Log::warning('GeneralTaskHandler: Claude CLI failed (streaming)', [
                    'error' => $result->errorOutput(),
                ]);

                return ChatActionResult::failure(
                    'I encountered an issue processing your request. Please try again.'
                );
            }

            $response = trim($totalOutput);

            if ($response === '') {
                return ChatActionResult::failure(
                    'I received an empty response. Could you rephrase your question?'
                );
            }

            return ChatActionResult::success($response);

        } catch (\Throwable $e) {
            Log::error('GeneralTaskHandler: Exception (streaming)', [
                'error' => $e->getMessage(),
            ]);

            return ChatActionResult::failure(
                'Something went wrong processing your request. Please try again.'
            );
        }
    }

    /**
     * @return array{0: string, 1: string|null} [systemPrompt, fullPrompt] — fullPrompt is null when empty
     */
    private function buildPrompts(ChatActionContext $context): array
    {
        $params = $context->getParameters();
        $taskDescription = $params['task_description'] ?? $params['original_message'] ?? '';

        if (trim($taskDescription) === '') {
            return ['', null];
        }

        $soulPrompt = $this->buildSoulPrompt($params);

        $systemPrompt = <<<PROMPT
        {$soulPrompt}

        You are a helpful assistant connected via a messenger chat interface.
        Respond concisely and use markdown formatting (bold, lists, code blocks) for readability.
        Keep responses under 1500 characters when possible.
        If asked about your capabilities, explain that you can help with general questions, codebase analysis, file operations, and job management.
        If a task requires running a job or accessing the local machine, suggest creating a job for it.
        PROMPT;

        $additionalContext = $params['context'] ?? '';
        $attachmentContext = $params['attachment_context'] ?? '';

        $fullPrompt = $taskDescription;
        if ($additionalContext !== '') {
            $fullPrompt .= "\n\nAdditional context:\n{$additionalContext}";
        }
        if ($attachmentContext !== '') {
            $fullPrompt .= "\n\nAttached files:\n{$attachmentContext}";
        }

        return [$systemPrompt, $fullPrompt];
    }

    private function buildSoulPrompt(array $params): string
    {
        $soul = $params['_soul'] ?? null;
        if ($soul === null) {
            return '';
        }

        $parts = [];
        if (! empty($soul['name'])) {
            $parts[] = "Your name is {$soul['name']}.";
        }
        if (! empty($soul['personality'])) {
            $parts[] = $soul['personality'];
        }
        if (! empty($soul['user_context'])) {
            $parts[] = "About the user: {$soul['user_context']}";
        }

        return implode("\n", $parts);
    }

    private function executable(): string
    {
        return (string) (config('agent.runner_executables.claude') ?: 'claude');
    }
}
