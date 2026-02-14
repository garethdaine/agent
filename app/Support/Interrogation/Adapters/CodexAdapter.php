<?php

namespace App\Support\Interrogation\Adapters;

use App\Models\InterrogationSession;
use App\Support\Interrogation\Contracts\InterrogationRunnerAdapter;

class CodexAdapter implements InterrogationRunnerAdapter
{
    /**
     * @return array<int, string>
     */
    public function buildDiscoveryCommand(InterrogationSession $session, string $discoveryPrompt, string $systemPrompt): array
    {
        return [
            $this->executable(),
            'exec',
            '--json',
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
        ];

        if (is_string($session->cli_session_id) && $session->cli_session_id !== '') {
            $command[] = 'resume';
            $command[] = $session->cli_session_id;
        } else {
            $command[] = 'exec';
        }

        $command[] = '--json';
        $command[] = '--output-schema';
        $command[] = $this->questionSchema();
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
        ];

        if (is_string($session->cli_session_id) && $session->cli_session_id !== '') {
            $command[] = 'resume';
            $command[] = $session->cli_session_id;
        } else {
            $command[] = 'exec';
        }

        $command[] = '--json';
        $command[] = '--output-schema';
        $command[] = $this->summarySchema();
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
        ];

        if (is_string($session->cli_session_id) && $session->cli_session_id !== '') {
            $command[] = 'resume';
            $command[] = $session->cli_session_id;
        } else {
            $command[] = 'exec';
        }

        $command[] = '--json';
        $command[] = '--output-schema';
        $command[] = $this->planSchema();
        $command[] = $planningPrompt;

        return $command;
    }

    /**
     * @return array<int, string>
     */
    public function buildReconstructCommand(InterrogationSession $session, string $conversationHistory, string $systemPrompt): array
    {
        return [
            $this->executable(),
            'exec',
            '--json',
            '--output-schema',
            $this->questionSchema(),
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
            'source' => 'codex',
            'raw' => $decoded,
            'message' => (string) ($decoded['message'] ?? $decoded['text'] ?? $decoded['content'] ?? ''),
        ];

        if (isset($decoded['session_id']) && is_string($decoded['session_id'])) {
            $payload['cli_session_id'] = $decoded['session_id'];
        }

        return [
            'type' => (string) ($decoded['type'] ?? 'message'),
            'payload' => $payload,
        ];
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
    public function parseSummaryResponse(string $output): ?array
    {
        $decoded = $this->decodeBestEffortJson($output);

        if (! is_array($decoded)) {
            return null;
        }

        if (! isset($decoded['summary_markdown']) && ! isset($decoded['summary'])) {
            return null;
        }

        return [
            'summary_markdown' => (string) ($decoded['summary_markdown'] ?? $decoded['summary'] ?? ''),
            'goals' => is_array($decoded['goals'] ?? null) ? array_values($decoded['goals']) : [],
            'constraints' => is_array($decoded['constraints'] ?? null) ? array_values($decoded['constraints']) : [],
            'acceptance_criteria' => is_array($decoded['acceptance_criteria'] ?? null) ? array_values($decoded['acceptance_criteria']) : [],
            'open_questions' => is_array($decoded['open_questions'] ?? null) ? array_values($decoded['open_questions']) : [],
            'private_notes' => (string) ($decoded['private_notes'] ?? ''),
        ];
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
     * @return array<string, string>
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
        return (string) (config('agent.runner_executables.codex') ?: 'codex');
    }

    private function questionSchema(): string
    {
        return json_encode([
            'type' => 'object',
            'required' => ['question_text', 'answer_type', 'progress_estimate'],
            'properties' => [
                'question_id' => ['type' => 'string'],
                'question_text' => ['type' => 'string'],
                'answer_type' => ['type' => 'string'],
                'options' => ['type' => 'array', 'items' => ['type' => 'string']],
                'reasoning' => ['type' => 'string'],
                'category' => ['type' => 'string'],
                'progress_estimate' => ['type' => 'integer'],
                'is_complete' => ['type' => 'boolean'],
                'cli_session_id' => ['type' => 'string'],
            ],
        ], JSON_UNESCAPED_SLASHES) ?: '{}';
    }

    private function summarySchema(): string
    {
        return json_encode([
            'type' => 'object',
            'required' => ['summary_markdown'],
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
