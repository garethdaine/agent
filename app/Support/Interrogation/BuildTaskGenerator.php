<?php

namespace App\Support\Interrogation;

use App\Models\InterrogationBuildTask;
use App\Models\InterrogationSession;
use RuntimeException;
use Symfony\Component\Process\Process;

class BuildTaskGenerator
{
    private const MIN_TIMEOUT_SECONDS = 3600;

    public function __construct(
        private readonly AdapterFactory $adapterFactory,
        private readonly SystemPromptResolver $promptResolver,
    ) {}

    /**
     * @return array{tasks:array<int,array<string,mixed>>}
     */
    public function generate(InterrogationSession $session, ?string $notes = null): array
    {
        $systemPrompt = $this->promptResolver->resolveForPhase($session, 'build_tasks');
        $prompt = $this->buildPrompt($session, $notes);
        $parsed = $this->runBuildTaskGeneration($session, $prompt, $systemPrompt);

        return [
            'tasks' => array_values($parsed['tasks']),
        ];
    }

    /**
     * @return array{title:string,description:string,instructions_markdown:string}
     */
    public function regenerateTask(
        InterrogationSession $session,
        InterrogationBuildTask $task,
        string $amendNotes,
    ): array {
        $systemPrompt = $this->promptResolver->resolveForPhase($session, 'build_tasks');
        $prompt = $this->buildTaskRegenerationPrompt($session, $task, $amendNotes);
        $parsed = $this->runBuildTaskGeneration($session, $prompt, $systemPrompt);

        $candidate = is_array($parsed['tasks'][0] ?? null) ? $parsed['tasks'][0] : null;
        if ($candidate === null) {
            throw new RuntimeException('Task regeneration response could not be parsed.');
        }

        $title = trim((string) ($candidate['title'] ?? ''));
        if ($title === '') {
            throw new RuntimeException('Task regeneration did not return a valid title.');
        }

        return [
            'title' => $title,
            'description' => trim((string) ($candidate['description'] ?? '')),
            'instructions_markdown' => trim((string) ($candidate['instructions_markdown'] ?? '')),
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
            'Never include destructive database commands (`migrate:fresh`, `migrate:refresh`, `db:wipe`, `DROP`, `TRUNCATE`) in task instructions.',
            'For every task that changes behavior, enforce tests first: write or update tests before implementation/refactor code, verify failure, then implement the minimum clean change until tests pass.',
            'Apply Code Field rules in task instructions: state assumptions first, define scope boundaries, cover edge/failure paths (not only happy path), and require explicit verification before claiming correctness.',
            'If the plan describes user-facing or operator-facing capabilities (for example dashboard, control surface, settings page, wizard, in-app workflow), include explicit tasks for route registration, page/component wiring, and navigation/discoverability.',
            'Include at least one verification step that proves the new surface is reachable in-app (not only API/backend completion).',
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

        $projectRules = $this->projectRulesFromMetadata($session);
        if ($projectRules !== []) {
            $parts[] = "\nProject rules to enforce:\n".$this->renderProjectRules($projectRules);
        }

        $techStacks = $session->techStacks()->ordered()->get(['name', 'documentation_url']);
        if ($techStacks->isNotEmpty()) {
            $parts[] = "\nSelected tech stack:\n- ".$techStacks
                ->map(fn ($stack): string => trim((string) $stack->name).': '.trim((string) $stack->documentation_url))
                ->implode("\n- ");
        }

        $notes = trim((string) $notes);
        if ($notes !== '') {
            $parts[] = "\nUser notes:\n{$notes}";
        }

        return implode("\n", $parts);
    }

    private function buildTaskRegenerationPrompt(
        InterrogationSession $session,
        InterrogationBuildTask $task,
        string $amendNotes,
    ): string {
        $plan = is_array($session->plan_json) ? $session->plan_json : [];
        $summary = is_array($session->summary_json) ? $session->summary_json : [];

        $summaryMarkdown = trim((string) ($summary['summary_markdown'] ?? ''));
        $planMarkdown = trim((string) ($plan['plan_markdown'] ?? ''));

        $parts = [
            'Regenerate exactly one build task based on user amendments.',
            'Return only valid JSON matching the schema with a tasks array that contains exactly one item.',
            'Do not return multiple tasks.',
            'Keep the task independently executable and scoped to a single outcome.',
            'Use concrete instructions with file paths, commands, and expected checks when possible.',
            'Never include destructive database commands (`migrate:fresh`, `migrate:refresh`, `db:wipe`, `DROP`, `TRUNCATE`) in task instructions.',
            'For this task, enforce tests first: write/update tests, verify failure, then implement minimum clean changes until tests pass.',
            'Apply Code Field rules: assumptions first, clear scope boundaries, edge/failure paths, and explicit verification.',
            'If this regenerated task is part of user-facing/operator-facing capability delivery, include explicit route/page/nav wiring and in-app discoverability verification.',
            "\nCurrent task to regenerate:\n"
            .'Sequence: '.((int) $task->sequence)
            ."\nTitle: ".trim((string) $task->title)
            ."\nDescription: ".trim((string) ($task->description ?? ''))
            ."\nInstructions:\n".trim((string) ($task->instructions_markdown ?? '')),
            "\nAmend notes from user:\n".trim($amendNotes),
        ];

        if ($summaryMarkdown !== '') {
            $parts[] = "\nSummary context:\n{$summaryMarkdown}";
        }

        if ($planMarkdown !== '') {
            $parts[] = "\nImplementation plan:\n{$planMarkdown}";
        }

        $projectRules = $this->projectRulesFromMetadata($session);
        if ($projectRules !== []) {
            $parts[] = "\nProject rules to enforce:\n".$this->renderProjectRules($projectRules);
        }

        $techStacks = $session->techStacks()->ordered()->get(['name', 'documentation_url']);
        if ($techStacks->isNotEmpty()) {
            $parts[] = "\nSelected tech stack:\n- ".$techStacks
                ->map(fn ($stack): string => trim((string) $stack->name).': '.trim((string) $stack->documentation_url))
                ->implode("\n- ");
        }

        return implode("\n", $parts);
    }

    /**
     * @return array{tasks:array<int,array<string,mixed>>}
     */
    private function runBuildTaskGeneration(
        InterrogationSession $session,
        string $prompt,
        string $systemPrompt,
    ): array {
        $timeoutSeconds = max(self::MIN_TIMEOUT_SECONDS, (int) config('agent.interrogation.build_task_generation_timeout_seconds', 7200));
        $adapter = $this->adapterFactory->make((string) $session->runner_type);

        $process = new Process(
            $adapter->buildBuildTasksCommand($session, $prompt, $systemPrompt),
            (string) $session->project_directory,
            $adapter->buildEnvironment($session),
        );
        $process->setTimeout($timeoutSeconds);
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
            $fallback->setTimeout($timeoutSeconds);
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
            'tasks' => array_values((array) $parsed['tasks']),
        ];
    }

    /**
     * @return array<int, array{title:string,markdown:string,source:string,filename:?string}>
     */
    private function projectRulesFromMetadata(InterrogationSession $session): array
    {
        $metadata = is_array($session->metadata_json) ? $session->metadata_json : [];
        $build = is_array($metadata['build'] ?? null) ? $metadata['build'] : [];
        $rules = is_array($build['project_rules'] ?? null) ? $build['project_rules'] : [];

        $normalized = [];

        foreach ($rules as $rule) {
            if (! is_array($rule)) {
                continue;
            }

            $title = trim((string) ($rule['title'] ?? ''));
            $markdown = trim((string) ($rule['markdown'] ?? ''));
            if ($markdown === '') {
                continue;
            }

            $source = strtolower(trim((string) ($rule['source'] ?? 'manual')));
            if (! in_array($source, ['manual', 'uploaded'], true)) {
                $source = 'manual';
            }

            $filename = trim((string) ($rule['filename'] ?? ''));

            $normalized[] = [
                'title' => $title !== '' ? $title : 'Rule '.(count($normalized) + 1),
                'markdown' => $markdown,
                'source' => $source,
                'filename' => $filename !== '' ? $filename : null,
            ];
        }

        return array_values($normalized);
    }

    /**
     * @param  array<int, array{title:string,markdown:string,source:string,filename:?string}>  $rules
     */
    private function renderProjectRules(array $rules): string
    {
        $lines = [];

        foreach ($rules as $index => $rule) {
            $title = trim((string) ($rule['title'] ?? ''));
            $markdown = trim((string) ($rule['markdown'] ?? ''));
            if ($markdown === '') {
                continue;
            }

            $source = strtolower(trim((string) ($rule['source'] ?? 'manual')));
            if (! in_array($source, ['manual', 'uploaded'], true)) {
                $source = 'manual';
            }

            $filename = trim((string) ($rule['filename'] ?? ''));
            $label = $title !== '' ? $title : 'Rule '.($index + 1);
            $suffix = $source === 'uploaded' && $filename !== ''
                ? sprintf(' (%s from %s)', $source, $filename)
                : sprintf(' (%s)', $source);

            $lines[] = sprintf('- %s%s', $label, $suffix);
            $lines[] = $markdown;
        }

        return implode("\n", $lines);
    }
}
