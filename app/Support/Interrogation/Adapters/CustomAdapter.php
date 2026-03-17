<?php

declare(strict_types=1);

namespace App\Support\Interrogation\Adapters;

use App\Models\InterrogationSession;
use App\Support\Agent\DatabaseIsolationEnvironment;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class CustomAdapter extends AbstractBuildAdapter
{
    /**
     * @return array<int, string>
     */
    public function buildDiscoveryCommand(InterrogationSession $session, string $discoveryPrompt, string $systemPrompt): array
    {
        return $this->buildMarkdownPromptCommand($session, 'discovery', $discoveryPrompt, $systemPrompt, null);
    }

    /**
     * @return array<int, string>
     */
    public function buildQuestionCommand(InterrogationSession $session, string $userMessage, string $systemPrompt): array
    {
        return $this->buildMarkdownPromptCommand($session, 'question', $userMessage, $systemPrompt, $this->questionSchema());
    }

    /**
     * @return array<int, string>
     */
    public function buildQuestionBankCommand(InterrogationSession $session, string $userMessage, string $systemPrompt): array
    {
        return $this->buildMarkdownPromptCommand($session, 'question-bank', $userMessage, $systemPrompt, $this->questionBankSchema());
    }

    /**
     * @return array<int, string>
     */
    public function buildSummaryCommand(InterrogationSession $session, string $summaryPrompt, string $systemPrompt): array
    {
        return $this->buildMarkdownPromptCommand($session, 'summary', $summaryPrompt, $systemPrompt, $this->summarySchema());
    }

    /**
     * @return array<int, string>
     */
    public function buildPlanCommand(InterrogationSession $session, string $planningPrompt, string $systemPrompt): array
    {
        return $this->buildMarkdownPromptCommand($session, 'plan', $planningPrompt, $systemPrompt, $this->planSchema());
    }

    /**
     * @return array<int, string>
     */
    public function buildBuildTasksCommand(InterrogationSession $session, string $prompt, string $systemPrompt): array
    {
        return $this->buildMarkdownPromptCommand($session, 'build-tasks', $prompt, $systemPrompt, $this->buildTasksSchema());
    }

    /**
     * @return array<int, string>
     */
    public function buildReconstructCommand(InterrogationSession $session, string $conversationHistory, string $systemPrompt): array
    {
        return $this->buildMarkdownPromptCommand($session, 'reconstruct', $conversationHistory, $systemPrompt, $this->questionSchema());
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

        if (is_array($decoded)) {
            return [
                'type' => (string) ($decoded['type'] ?? 'message'),
                'payload' => [
                    'source' => 'custom',
                    'raw' => $decoded,
                    'message' => (string) ($decoded['message'] ?? $decoded['text'] ?? $decoded['content'] ?? ''),
                ],
            ];
        }

        return [
            'type' => 'message',
            'payload' => [
                'source' => 'custom',
                'message' => $line,
            ],
        ];
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

    /**
     * @return array<int, string>
     */
    private function buildMarkdownPromptCommand(
        InterrogationSession $session,
        string $kind,
        string $userPrompt,
        string $systemPrompt,
        ?string $schema,
    ): array {
        return [
            $this->executable(),
            $this->writePromptMarkdown($session, $kind, $userPrompt, $systemPrompt, $schema),
        ];
    }

    protected function executable(): string
    {
        return (string) (config('agent.runner_executables.custom') ?: 'custom');
    }

    protected function model(): string
    {
        return '';
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

    private function writePromptMarkdown(
        InterrogationSession $session,
        string $kind,
        string $userPrompt,
        string $systemPrompt,
        ?string $schema,
    ): string {
        $directory = storage_path('framework/interrogation-custom-prompts');

        if (! File::isDirectory($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        $filename = sprintf(
            'workflow-interrogator-%s-session-%d-%s.md',
            $kind,
            (int) $session->id,
            Str::lower(Str::random(12)),
        );

        $path = $directory.DIRECTORY_SEPARATOR.$filename;
        File::put($path, $this->composePromptMarkdown($systemPrompt, $userPrompt, $schema));

        return $path;
    }

    private function composePromptMarkdown(string $systemPrompt, string $userPrompt, ?string $schema): string
    {
        $sections = [
            '# Workflow Interrogation Task',
            '',
            '## System Instructions',
            trim($systemPrompt) !== '' ? trim($systemPrompt) : 'No additional system instructions provided.',
            '',
            '## User Request',
            trim($userPrompt),
            '',
            '## Output Contract',
            'Return JSON only.',
        ];

        if (is_string($schema) && trim($schema) !== '') {
            $sections[] = '';
            $sections[] = 'The JSON must conform to this schema:';
            $sections[] = '```json';
            $sections[] = trim($schema);
            $sections[] = '```';
        }

        return implode("\n", $sections)."\n";
    }
}
