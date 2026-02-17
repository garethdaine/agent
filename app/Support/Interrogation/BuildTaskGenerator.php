<?php

namespace App\Support\Interrogation;

use App\Models\InterrogationSession;
use RuntimeException;
use Symfony\Component\Process\Process;

class BuildTaskGenerator
{
    public function __construct(
        private readonly AdapterFactory $adapterFactory,
        private readonly SystemPromptResolver $promptResolver,
    ) {}

    /**
     * @return array{tasks:array<int,array<string,mixed>>}
     */
    public function generate(InterrogationSession $session, ?string $notes = null): array
    {
        $adapter = $this->adapterFactory->make((string) $session->runner_type);
        $systemPrompt = $this->promptResolver->resolveForPhase($session, 'build_tasks');

        $prompt = $this->buildPrompt($session, $notes);

        $process = new Process(
            $adapter->buildBuildTasksCommand($session, $prompt, $systemPrompt),
            (string) $session->project_directory,
            $adapter->buildEnvironment($session),
        );
        $process->setTimeout(600);
        $process->run();
        $parsed = $process->getExitCode() === 0
            ? $adapter->parseBuildTasksResponse((string) $process->getOutput())
            : null;

        if (
            is_string($session->cli_session_id)
            && trim($session->cli_session_id) !== ''
            && ($process->getExitCode() !== 0 || ! is_array($parsed) || ! is_array($parsed['tasks'] ?? null) || $parsed['tasks'] === [])
        ) {
            $freshSession = clone $session;
            $freshSession->cli_session_id = null;

            $fallback = new Process(
                $adapter->buildBuildTasksCommand($freshSession, $prompt, $systemPrompt),
                (string) $session->project_directory,
                $adapter->buildEnvironment($freshSession),
            );
            $fallback->setTimeout(600);
            $fallback->run();

            $fallbackParsed = $fallback->getExitCode() === 0
                ? $adapter->parseBuildTasksResponse((string) $fallback->getOutput())
                : null;

            if ($fallback->getExitCode() === 0 && is_array($fallbackParsed) && is_array($fallbackParsed['tasks'] ?? null) && $fallbackParsed['tasks'] !== []) {
                $process = $fallback;
                $parsed = $fallbackParsed;
                $session->cli_session_id = null;
                $session->save();
            } else {
                $process = $fallback;
                $parsed = $fallbackParsed;
            }
        }

        if ($process->getExitCode() !== 0) {
            throw new RuntimeException(trim((string) $process->getErrorOutput()) ?: 'Build task generation command failed.');
        }

        if (! is_array($parsed) || ! is_array($parsed['tasks'] ?? null) || $parsed['tasks'] === []) {
            throw new RuntimeException('Build task generation response could not be parsed.');
        }

        return [
            'tasks' => array_values($parsed['tasks']),
        ];
    }

    private function buildPrompt(InterrogationSession $session, ?string $notes): string
    {
        $plan = is_array($session->plan_json) ? $session->plan_json : [];
        $summary = is_array($session->summary_json) ? $session->summary_json : [];

        $planMarkdown = trim((string) ($plan['plan_markdown'] ?? ''));
        $summaryMarkdown = trim((string) ($summary['summary_markdown'] ?? ''));

        $parts = [
            'Generate an actionable build task list for execution.',
            'Return only valid JSON matching the schema.',
            'Each task should be independently executable and scoped to a single outcome.',
            'Use concrete instructions with file paths, commands, and expected checks when possible.',
            'Prefer 3-12 tasks unless the plan clearly requires more.',
        ];

        if ($summaryMarkdown !== '') {
            $parts[] = "\nSummary context:\n{$summaryMarkdown}";
        }

        if ($planMarkdown !== '') {
            $parts[] = "\nImplementation plan:\n{$planMarkdown}";
        }

        $sections = is_array($plan['sections'] ?? null) ? array_values($plan['sections']) : [];
        if ($sections !== []) {
            $parts[] = "\nPlan sections:\n- ".implode("\n- ", array_map(static fn ($item): string => trim((string) $item), $sections));
        }

        $risks = is_array($plan['risks'] ?? null) ? array_values($plan['risks']) : [];
        if ($risks !== []) {
            $parts[] = "\nKnown risks:\n- ".implode("\n- ", array_map(static fn ($item): string => trim((string) $item), $risks));
        }

        $assumptions = is_array($plan['assumptions'] ?? null) ? array_values($plan['assumptions']) : [];
        if ($assumptions !== []) {
            $parts[] = "\nAssumptions:\n- ".implode("\n- ", array_map(static fn ($item): string => trim((string) $item), $assumptions));
        }

        $notes = trim((string) $notes);
        if ($notes !== '') {
            $parts[] = "\nUser notes:\n{$notes}";
        }

        return implode("\n", $parts);
    }
}
