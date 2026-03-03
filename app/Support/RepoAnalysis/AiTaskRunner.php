<?php

declare(strict_types=1);

namespace App\Support\RepoAnalysis;

use App\Models\InterrogationSession;
use App\Models\RepoAnalysisArtifact;
use App\Models\RepoAnalysisSession;
use App\Models\RepoAnalysisTask;
use App\Support\Interrogation\AdapterFactory;
use Symfony\Component\Process\Process;

class AiTaskRunner
{
    public function __construct(
        private readonly AdapterFactory $adapterFactory,
    ) {}

    /**
     * @param  array<string, mixed>  $snapshot
     * @return array{
     *   analyzer_key: string,
     *   analyzer_version: string,
     *   artifact_type: string,
     *   artifact_key: string,
     *   payload: array<string, mixed>,
     *   warnings: array<int, array<string, mixed>>,
     *   warning_artifact_path: string|null,
     *   output_hash: string,
     *   cli_session_id: string|null
     * }
     */
    public function run(RepoAnalysisSession $session, RepoAnalysisTask $task, array $snapshot, EventWriter $writer): array
    {
        $runnerType = in_array((string) $session->runner_type, ['claude', 'codex'], true)
            ? (string) $session->runner_type
            : 'claude';

        $bridgeSession = $this->bridgeSession($session);
        $adapter = $this->adapterFactory->make($runnerType);
        $systemPrompt = $this->systemPrompt($task);
        $taskPrompt = $this->taskPrompt($session, $task, $snapshot);
        $command = $adapter->buildSummaryCommand($bridgeSession, $taskPrompt, $systemPrompt);
        $environment = $adapter->buildEnvironment($bridgeSession);

        $process = new Process($command, (string) $session->project_directory, $environment);
        $process->setTimeout((int) config('repo_analysis.ai.task_timeout_seconds', 1200));
        $process->start();

        $stdoutBuffer = '';
        $stderrBuffer = '';

        while ($process->isRunning()) {
            $stdoutBuffer = $this->consumeStreamChunk($adapter, $writer, $task, $stdoutBuffer, $process->getIncrementalOutput());
            $stderrBuffer = $this->consumeStreamChunk($adapter, $writer, $task, $stderrBuffer, $process->getIncrementalErrorOutput());

            usleep(200_000);
        }

        $stdoutBuffer = $this->consumeStreamChunk($adapter, $writer, $task, $stdoutBuffer, $process->getIncrementalOutput(), true);
        $stderrBuffer = $this->consumeStreamChunk($adapter, $writer, $task, $stderrBuffer, $process->getIncrementalErrorOutput(), true);

        $stdout = (string) $process->getOutput();
        $stderr = (string) $process->getErrorOutput();

        if ((int) ($process->getExitCode() ?? 1) !== 0) {
            throw new \RuntimeException(
                trim($stderr) !== ''
                    ? trim($stderr)
                    : 'AI runner command failed for repo analysis task.'
            );
        }

        $parsed = $adapter->parseSummaryResponse($stdout);
        if (! is_array($parsed) || trim((string) ($parsed['summary_markdown'] ?? '')) === '') {
            throw new \RuntimeException('Unable to parse AI runner response for repo analysis task.');
        }

        $payload = [
            'runner_type' => $runnerType,
            'task_key' => (string) $task->task_key,
            'task_name' => (string) $task->analyzer_name,
            'section_key' => (string) data_get($task->metadata_json, 'section_key', $task->analyzer_name),
            'section_title' => (string) data_get($task->metadata_json, 'section_title', $task->analyzer_name),
            'summary_markdown' => (string) ($parsed['summary_markdown'] ?? ''),
            'goals' => $this->stringList($parsed['goals'] ?? []),
            'constraints' => $this->stringList($parsed['constraints'] ?? []),
            'acceptance_criteria' => $this->stringList($parsed['acceptance_criteria'] ?? []),
            'open_questions' => $this->stringList($parsed['open_questions'] ?? []),
            'stderr_excerpt' => mb_substr(trim($stderr), 0, 4000),
        ];

        $result = [
            'analyzer_key' => (string) $task->analyzer_name,
            'analyzer_version' => (string) $task->analyzer_version,
            'artifact_type' => 'ai_analysis_section',
            'artifact_key' => sprintf('ai.%s.json', (string) $task->analyzer_name),
            'payload' => $payload,
            'warnings' => [],
            'warning_artifact_path' => null,
            'cli_session_id' => is_string($parsed['cli_session_id'] ?? null) ? $parsed['cli_session_id'] : null,
        ];

        $result['output_hash'] = hash(
            'sha256',
            json_encode($this->normalizeForHash($result), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}'
        );

        return $result;
    }

    private function consumeStreamChunk(
        object $adapter,
        EventWriter $writer,
        RepoAnalysisTask $task,
        string $buffer,
        string $chunk,
        bool $flush = false,
    ): string {
        if ($chunk !== '') {
            $buffer .= $chunk;
        }

        $parts = preg_split('/\R/', $buffer) ?: [];

        if (! $flush) {
            $buffer = (string) array_pop($parts);
        } else {
            $buffer = '';
        }

        $maxLength = (int) config('repo_analysis.ai.max_stream_message_length', 320);

        foreach ($parts as $line) {
            $line = trim((string) $line);
            if ($line === '') {
                continue;
            }

            $parsed = method_exists($adapter, 'parseStreamEvent')
                ? $adapter->parseStreamEvent($line)
                : null;

            if (! is_array($parsed)) {
                $writer->append('task_progress', [
                    'task_key' => (string) $task->task_key,
                    'message' => mb_substr($line, 0, $maxLength),
                    'stream_type' => 'line',
                    'source' => 'ai_runner',
                ], [
                    'phase' => 3,
                    'status' => SessionStateTransitionService::STATUS_EXECUTING,
                ]);

                continue;
            }

            $payload = is_array($parsed['payload'] ?? null) ? $parsed['payload'] : [];
            $message = trim((string) ($payload['message'] ?? ''));
            if ($message === '') {
                continue;
            }

            $writer->append('task_progress', [
                'task_key' => (string) $task->task_key,
                'message' => mb_substr($message, 0, $maxLength),
                'stream_type' => (string) ($parsed['type'] ?? 'message'),
                'source' => (string) ($payload['source'] ?? 'ai_runner'),
            ], [
                'phase' => 3,
                'status' => SessionStateTransitionService::STATUS_EXECUTING,
            ]);
        }

        return $buffer;
    }

    private function bridgeSession(RepoAnalysisSession $session): InterrogationSession
    {
        $bridge = new InterrogationSession;
        $bridge->id = (int) $session->id;
        $bridge->user_id = (int) $session->user_id;
        $bridge->runner_type = in_array((string) $session->runner_type, ['claude', 'codex'], true)
            ? (string) $session->runner_type
            : 'claude';
        $bridge->project_directory = (string) $session->project_directory;
        $bridge->cli_session_id = (string) data_get($session->metadata_json, 'ai_cli_session_id', '');

        return $bridge;
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    private function taskPrompt(RepoAnalysisSession $session, RepoAnalysisTask $task, array $snapshot): string
    {
        $context = $this->analysisContext($session, $task, $snapshot);
        $instruction = $this->sectionInstruction((string) $task->analyzer_name);

        return trim($instruction)."\n\n"
            .'Use repository inspection tools (`Read`, `Glob`, `Grep`) and ground every claim in observed files.'
            ."\nDo not invent features or dependencies."
            ."\nReturn a deeply detailed markdown section for this task."
            ."\n\nContext JSON:\n```json\n"
            .json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            ."\n```";
    }

    private function systemPrompt(RepoAnalysisTask $task): string
    {
        $sectionTitle = (string) data_get($task->metadata_json, 'section_title', $task->analyzer_name);

        return 'You are a staff-level repository analyst. '
            .'Analyze the codebase step-by-step and produce factual, human-readable documentation. '
            .'Focus section: '.$sectionTitle.'. '
            .'Use only evidence from the repository context and direct file inspection. '
            .'When uncertain, state assumptions explicitly.';
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @return array<string, mixed>
     */
    private function analysisContext(RepoAnalysisSession $session, RepoAnalysisTask $task, array $snapshot): array
    {
        $paths = collect(data_get($snapshot, 'manifest.files', []))
            ->map(fn (mixed $file): ?string => is_array($file) && is_string($file['path'] ?? null) ? $file['path'] : null)
            ->filter()
            ->values()
            ->all();

        $deterministicArtifacts = RepoAnalysisArtifact::query()
            ->where('repo_analysis_session_id', $session->id)
            ->whereIn('artifact_type', [
                'filesystem_manifest',
                'dependency_manifest',
                'routing_surface',
                'data_model_surface',
                'async_workflows_surface',
                'frontend_surface',
                'architecture_patterns',
                'laravel_routes',
                'laravel_models_migrations',
                'queue_jobs_events',
                'frontend_module_graph',
                'test_coverage_map',
                'risk_hotspot',
                'code_quality_standards',
                'coverage_validation',
            ])
            ->orderBy('artifact_type')
            ->get(['artifact_type', 'artifact_key', 'payload_json'])
            ->map(static fn (RepoAnalysisArtifact $artifact): array => [
                'artifact_type' => (string) $artifact->artifact_type,
                'artifact_key' => (string) $artifact->artifact_key,
                'payload' => is_array(data_get($artifact->payload_json, 'payload'))
                    ? data_get($artifact->payload_json, 'payload')
                    : (is_array($artifact->payload_json) ? $artifact->payload_json : []),
            ])
            ->all();

        $aiSections = RepoAnalysisArtifact::query()
            ->where('repo_analysis_session_id', $session->id)
            ->where('artifact_type', 'ai_analysis_section')
            ->orderBy('id')
            ->get(['artifact_key', 'payload_json'])
            ->map(static fn (RepoAnalysisArtifact $artifact): array => [
                'artifact_key' => (string) $artifact->artifact_key,
                'section_key' => (string) data_get($artifact->payload_json, 'payload.section_key', ''),
                'section_title' => (string) data_get($artifact->payload_json, 'payload.section_title', ''),
                'summary_markdown' => (string) data_get($artifact->payload_json, 'payload.summary_markdown', ''),
            ])
            ->all();

        return [
            'session' => [
                'id' => (int) $session->id,
                'name' => (string) ($session->name ?? ''),
                'project_directory' => (string) $session->project_directory,
                'snapshot_hash' => (string) $session->snapshot_hash,
                'runner_type' => (string) $session->runner_type,
                'task_key' => (string) $task->task_key,
                'task_name' => (string) $task->analyzer_name,
            ],
            'snapshot' => [
                'stats' => data_get($snapshot, 'manifest.stats', []),
                'top_level_paths_sample' => array_slice($this->stringList($paths), 0, 200),
            ],
            'deterministic_artifacts' => $deterministicArtifacts,
            'prior_ai_sections' => $aiSections,
        ];
    }

    private function sectionInstruction(string $taskName): string
    {
        return match ($taskName) {
            'ai_repo_overview' => 'Produce a comprehensive repository overview: project purpose, primary features, overall architecture, tech stack, and dependency landscape.',
            'ai_backend_surface' => 'Analyze backend architecture in depth: domain structure, models, migrations, routing/API surfaces, queue/event patterns, and key backend flows.',
            'ai_frontend_surface' => 'Analyze frontend architecture in depth: page structure, component patterns, state/data flow, UI conventions, and integration points with backend APIs.',
            'ai_design_patterns' => 'Extract design and architecture patterns in depth: identify dominant patterns, where they appear, evidence files, consistency, and where patterns are mixed or violated.',
            'ai_coding_standards_quality' => 'Analyze coding standards and code quality posture: lint/format/static-analysis setup, testing quality, CI quality gates, maintainability risks, and standards enforcement gaps.',
            'ai_quality_risk' => 'Assess testing and quality posture: test inventory, likely coverage gaps, operational risks, hotspots, and maintainability concerns with evidence.',
            'ai_final_report' => 'Synthesize a final full report covering dependencies, codebase structure, features, data model, tech stack, routes, migrations, design patterns, coding standards, code quality, tests, risks, and prioritized recommendations.',
            default => 'Analyze the assigned repository section with evidence and produce a detailed markdown report.',
        };
    }

    /**
     * @return array<int, string>
     */
    private function stringList(mixed $values): array
    {
        if (! is_array($values)) {
            return [];
        }

        $items = [];
        foreach ($values as $value) {
            if (! is_scalar($value)) {
                continue;
            }

            $text = trim((string) $value);
            if ($text === '') {
                continue;
            }

            $items[] = $text;
        }

        $items = array_values(array_unique($items));
        sort($items, SORT_STRING);

        return $items;
    }

    private function normalizeForHash(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if ($value === [] || array_keys($value) === range(0, count($value) - 1)) {
            return array_map(fn (mixed $item): mixed => $this->normalizeForHash($item), $value);
        }

        ksort($value, SORT_STRING);
        foreach ($value as $key => $item) {
            $value[$key] = $this->normalizeForHash($item);
        }

        return $value;
    }
}
