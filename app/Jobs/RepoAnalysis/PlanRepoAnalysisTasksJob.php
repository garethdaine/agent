<?php

declare(strict_types=1);

namespace App\Jobs\RepoAnalysis;

use App\Models\RepoAnalysisSession;
use App\Models\RepoAnalysisTask;
use App\Support\Agent\FeatureFlagManager;
use App\Support\RepoAnalysis\Analyzers\AnalyzerRegistry;
use App\Support\RepoAnalysis\EventWriter;
use App\Support\RepoAnalysis\RepoAnalysisExecutionOrchestrator;
use App\Support\RepoAnalysis\SessionStateTransitionService;
use App\Support\RepoAnalysis\TaskGraphBuilder;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class PlanRepoAnalysisTasksJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 900;

    public function __construct(
        public int $sessionId,
        public bool $dispatchNext = true,
    ) {
        $this->onConnection(config('repo_analysis.queue.connection', 'redis'));
        $this->onQueue(config('repo_analysis.queue.name', 'code-analysis'));
    }

    public function handle(
        TaskGraphBuilder $taskGraphBuilder,
        RepoAnalysisExecutionOrchestrator $orchestrator,
        SessionStateTransitionService $transitions,
        AnalyzerRegistry $analyzers,
        FeatureFlagManager $featureFlags,
    ): void {
        $orchestrator->assertQueue($this->queue);

        $session = RepoAnalysisSession::query()->find($this->sessionId);
        if ($session === null) {
            return;
        }

        $metadata = is_array($session->metadata_json) ? $session->metadata_json : [];
        $snapshot = $orchestrator->snapshot($metadata);

        if ($snapshot === [] || ! is_string($session->snapshot_hash) || $session->snapshot_hash === '') {
            return;
        }

        if ((int) $session->phase < 2) {
            return;
        }

        $plan = $taskGraphBuilder->build($snapshot, (string) $session->analyzer_profile);
        $existingTasks = $session->tasks()->get()->keyBy('task_key');
        $plannedTaskKeys = [];

        foreach ($plan['tasks'] as $plannedTask) {
            $taskKey = (string) ($plannedTask['task_key'] ?? '');
            if ($taskKey === '') {
                continue;
            }

            $plannedTaskKeys[] = $taskKey;
            $analyzerName = (string) ($plannedTask['analyzer_key'] ?? '');

            if ($analyzerName === '') {
                continue;
            }

            $analyzer = $analyzers->get($analyzerName);
            $analyzerVersion = $analyzer->version();
            $inputHash = $orchestrator->taskInputHash(
                (string) $session->snapshot_hash,
                $analyzerName,
                $analyzerVersion,
            );

            $existingTask = $existingTasks->get($taskKey);
            $baseAttributes = [
                'repo_analysis_session_id' => $session->id,
                'task_key' => $taskKey,
                'task_type' => 'analyzer',
                'phase' => 3,
                'depends_on_json' => is_array($plannedTask['depends_on'] ?? null)
                    ? array_values($plannedTask['depends_on'])
                    : [],
                'analyzer_name' => $analyzerName,
                'analyzer_version' => $analyzerVersion,
                'input_hash' => $inputHash,
            ];

            if ($existingTask instanceof RepoAnalysisTask) {
                $existingMetadata = is_array($existingTask->metadata_json) ? $existingTask->metadata_json : [];
                $reuseAllowed = (string) $existingTask->status === 'completed'
                    && (string) $existingTask->input_hash === $inputHash
                    && (string) $existingTask->analyzer_version === $analyzerVersion;

                if ($reuseAllowed) {
                    $existingMetadata['reused_output'] = true;
                    $existingMetadata['reuse_checked_at'] = CarbonImmutable::now('UTC')->toIso8601String();

                    $existingTask->fill(array_merge($baseAttributes, [
                        'status' => 'completed',
                        'metadata_json' => $existingMetadata,
                    ]));
                    $existingTask->save();

                    continue;
                }

                $existingMetadata['reused_output'] = false;
                $existingMetadata['reuse_checked_at'] = CarbonImmutable::now('UTC')->toIso8601String();

                $existingTask->fill(array_merge($baseAttributes, [
                    'status' => 'pending',
                    'attempt_count' => 0,
                    'output_hash' => null,
                    'artifact_ids_json' => [],
                    'error_code' => null,
                    'error_summary' => null,
                    'started_at' => null,
                    'finished_at' => null,
                    'metadata_json' => $this->applyFailureScript($metadata, $analyzerName, $existingMetadata),
                ]));
                $existingTask->save();

                continue;
            }

            RepoAnalysisTask::query()->create(array_merge($baseAttributes, [
                'status' => 'pending',
                'attempt_count' => 0,
                'max_attempts' => 2,
                'artifact_ids_json' => [],
                'metadata_json' => $this->applyFailureScript($metadata, $analyzerName, []),
            ]));
        }

        if ($featureFlags->enabled(FeatureFlagManager::REPO_ANALYSIS_AI_ENABLED)) {
            foreach ($this->aiTaskDefinitions() as $aiTaskDefinition) {
                $analyzerName = (string) $aiTaskDefinition['analyzer_name'];
                $analyzerVersion = 'ai-task-1.0.0';
                $taskKey = $this->taskKey((string) $session->analyzer_profile, (string) $session->snapshot_hash, $analyzerName, $analyzerVersion);
                $plannedTaskKeys[] = $taskKey;

                $inputHash = $orchestrator->taskInputHash(
                    (string) $session->snapshot_hash,
                    $analyzerName,
                    $analyzerVersion,
                );

                $dependsOn = is_array($aiTaskDefinition['depends_on'] ?? null)
                    ? array_values(array_filter($aiTaskDefinition['depends_on'], static fn (mixed $dependency): bool => is_string($dependency) && $dependency !== ''))
                    : [];

                $taskMetadata = [
                    'ai_task' => true,
                    'section_key' => (string) ($aiTaskDefinition['section_key'] ?? $analyzerName),
                    'section_title' => (string) ($aiTaskDefinition['section_title'] ?? $analyzerName),
                    'runner_type' => (string) ($session->runner_type ?: 'claude'),
                ];

                $existingTask = $existingTasks->get($taskKey);
                $baseAttributes = [
                    'repo_analysis_session_id' => $session->id,
                    'task_key' => $taskKey,
                    'task_type' => 'ai_analysis',
                    'phase' => 3,
                    'depends_on_json' => $dependsOn,
                    'analyzer_name' => $analyzerName,
                    'analyzer_version' => $analyzerVersion,
                    'input_hash' => $inputHash,
                ];

                if ($existingTask instanceof RepoAnalysisTask) {
                    $existingTaskMetadata = is_array($existingTask->metadata_json) ? $existingTask->metadata_json : [];
                    $existingTask->fill(array_merge($baseAttributes, [
                        'status' => 'pending',
                        'attempt_count' => 0,
                        'output_hash' => null,
                        'artifact_ids_json' => [],
                        'error_code' => null,
                        'error_summary' => null,
                        'started_at' => null,
                        'finished_at' => null,
                        'metadata_json' => array_merge($existingTaskMetadata, $taskMetadata),
                    ]));
                    $existingTask->save();

                    continue;
                }

                RepoAnalysisTask::query()->create(array_merge($baseAttributes, [
                    'status' => 'pending',
                    'attempt_count' => 0,
                    'max_attempts' => 1,
                    'artifact_ids_json' => [],
                    'metadata_json' => $taskMetadata,
                ]));
            }
        }

        if ($plannedTaskKeys !== []) {
            $session->tasks()
                ->whereNotIn('task_key', $plannedTaskKeys)
                ->whereNotIn('status', ['completed'])
                ->update([
                    'status' => 'skipped',
                    'error_code' => 'TASK_NOT_IN_LATEST_PLAN',
                    'error_summary' => 'Task is not part of the latest deterministic plan.',
                    'updated_at' => CarbonImmutable::now('UTC'),
                ]);
        }

        $writer = new EventWriter($session);
        $writer->append('tasks_planned', [
            'task_count' => count($plan['tasks']),
            'skipped_count' => count($plan['skipped']),
        ], [
            'phase' => 2,
            'status' => SessionStateTransitionService::STATUS_PLANNING,
        ]);

        $moved = $transitions->transitionTo($session->id, 3, SessionStateTransitionService::STATUS_EXECUTING);
        if (! $moved) {
            $transitions->resume($session->id);
            $transitions->retry($session->id);
        }

        if ($this->dispatchNext) {
            $orchestrator->dispatchExecute($session->id);
        }
    }

    /**
     * @param  array<string, mixed>  $sessionMetadata
     * @param  array<string, mixed>  $taskMetadata
     * @return array<string, mixed>
     */
    private function applyFailureScript(array $sessionMetadata, string $analyzerName, array $taskMetadata): array
    {
        $scripts = $sessionMetadata['test_failure_scripts'] ?? null;
        if (! is_array($scripts)) {
            return $taskMetadata;
        }

        $script = $scripts[$analyzerName] ?? null;
        if (! is_array($script)) {
            return $taskMetadata;
        }

        $taskMetadata['failure_script'] = array_values(array_filter($script, static fn (mixed $value): bool => is_string($value) && $value !== ''));

        return $taskMetadata;
    }

    /**
     * @return array<int, array{
     *   analyzer_name: string,
     *   section_key: string,
     *   section_title: string,
     *   depends_on: array<int, string>
     * }>
     */
    private function aiTaskDefinitions(): array
    {
        return [
            [
                'analyzer_name' => 'ai_overview',
                'section_key' => 'overview',
                'section_title' => 'Codebase Overview',
                'depends_on' => ['filesystem_manifest', 'dependency_manifest'],
            ],
            [
                'analyzer_name' => 'ai_backend_surface',
                'section_key' => 'backend',
                'section_title' => 'Backend Architecture, Data Model, and Routing',
                'depends_on' => ['routing_surface', 'data_model_surface', 'async_workflows_surface'],
            ],
            [
                'analyzer_name' => 'ai_frontend_surface',
                'section_key' => 'frontend',
                'section_title' => 'Frontend Architecture and UI Patterns',
                'depends_on' => ['frontend_surface'],
            ],
            [
                'analyzer_name' => 'ai_design_patterns',
                'section_key' => 'design_patterns',
                'section_title' => 'Design Pattern Extraction and Architectural Conventions',
                'depends_on' => ['architecture_patterns', 'routing_surface', 'data_model_surface', 'frontend_surface'],
            ],
            [
                'analyzer_name' => 'ai_coding_standards_quality',
                'section_key' => 'coding_standards_quality',
                'section_title' => 'Coding Standards and Code Quality Posture',
                'depends_on' => ['code_quality_standards', 'test_coverage_map', 'risk_hotspot', 'dependency_manifest'],
            ],
            [
                'analyzer_name' => 'ai_quality_risk',
                'section_key' => 'quality_risk',
                'section_title' => 'Testing, Coverage, and Risk Analysis',
                'depends_on' => ['test_coverage_map', 'risk_hotspot'],
            ],
            [
                'analyzer_name' => 'ai_final_report',
                'section_key' => 'final_report',
                'section_title' => 'Final Comprehensive Repository Report',
                'depends_on' => [
                    'ai_overview',
                    'ai_backend_surface',
                    'ai_frontend_surface',
                    'ai_design_patterns',
                    'ai_coding_standards_quality',
                    'ai_quality_risk',
                ],
            ],
        ];
    }

    private function taskKey(string $profile, string $snapshotHash, string $analyzerName, string $analyzerVersion): string
    {
        $hashInput = implode('|', [$profile, $snapshotHash, $analyzerName, $analyzerVersion]);

        return sprintf('%s:%s', $analyzerName, substr(hash('sha256', $hashInput), 0, 24));
    }
}
