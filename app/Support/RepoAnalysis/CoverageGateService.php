<?php

declare(strict_types=1);

namespace App\Support\RepoAnalysis;

use App\Models\RepoAnalysisArtifact;
use App\Models\RepoAnalysisSession;
use App\Models\RepoAnalysisTask;
use Carbon\CarbonImmutable;

class CoverageGateService
{
    /**
     * @return array{
     *   passed: bool,
     *   blocking_failures: array<int, array{code: string, message: string, context: array<string, mixed>}>,
     *   warnings: array<int, array{code: string, message: string, context: array<string, mixed>}>,
     *   required_artifact_classes: array<int, string>,
     *   missing_artifact_classes: array<int, string>,
     *   task_count: int,
     *   completed_task_count: int,
     *   validated_at: string
     * }
     */
    public function evaluate(RepoAnalysisSession $session): array
    {
        $requiredArtifactClasses = $this->requiredArtifactClasses();
        $artifactTypes = $this->artifactTypes($session);
        $missingArtifactClasses = array_values(array_diff($requiredArtifactClasses, $artifactTypes));

        $blockingFailures = [];

        if (trim((string) $session->snapshot_hash) === '') {
            $blockingFailures[] = [
                'code' => 'missing_snapshot_hash',
                'message' => 'Snapshot hash must be present before coverage can pass.',
                'context' => [],
            ];
        }

        if ($missingArtifactClasses !== []) {
            $blockingFailures[] = [
                'code' => 'missing_required_artifact_classes',
                'message' => 'Required artifact classes are missing.',
                'context' => [
                    'missing' => $missingArtifactClasses,
                ],
            ];
        }

        $criticalFailedTasks = $this->criticalFailedTasks($session);
        if ($criticalFailedTasks !== []) {
            $blockingFailures[] = [
                'code' => 'critical_task_failure_present',
                'message' => 'Critical task failures must be resolved before completion.',
                'context' => [
                    'task_keys' => array_column($criticalFailedTasks, 'task_key'),
                    'count' => count($criticalFailedTasks),
                ],
            ];
        }

        $warnings = [];
        if ($this->hasNoTestsWarning($session)) {
            $warnings[] = [
                'code' => 'empty_test_suite',
                'message' => 'No tests discovered for coverage mapping. Completion is allowed with warning.',
                'context' => [],
            ];
        }

        $taskCount = $session->tasks()->count();
        $completedTaskCount = $session->tasks()->where('status', 'completed')->count();

        return [
            'passed' => $blockingFailures === [],
            'blocking_failures' => $blockingFailures,
            'warnings' => $warnings,
            'required_artifact_classes' => $requiredArtifactClasses,
            'missing_artifact_classes' => $missingArtifactClasses,
            'task_count' => $taskCount,
            'completed_task_count' => $completedTaskCount,
            'validated_at' => CarbonImmutable::now('UTC')->toIso8601String(),
        ];
    }

    /**
     * @return array<int, string>
     */
    private function requiredArtifactClasses(): array
    {
        $configured = config('repo_analysis.coverage.required_artifact_classes', []);
        if (! is_array($configured)) {
            return [];
        }

        $required = [];
        foreach ($configured as $artifactClass) {
            if (! is_string($artifactClass)) {
                continue;
            }

            $trimmed = trim($artifactClass);
            if ($trimmed === '') {
                continue;
            }

            $required[] = $trimmed;
        }

        $required = array_values(array_unique($required));
        sort($required, SORT_STRING);

        return $required;
    }

    /**
     * @return array<int, string>
     */
    private function artifactTypes(RepoAnalysisSession $session): array
    {
        return RepoAnalysisArtifact::query()
            ->where('repo_analysis_session_id', $session->id)
            ->whereNotNull('artifact_type')
            ->distinct()
            ->pluck('artifact_type')
            ->map(static fn (mixed $value): string => trim((string) $value))
            ->filter(static fn (string $value): bool => $value !== '')
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{task_key: string, error_code: string|null}>
     */
    private function criticalFailedTasks(RepoAnalysisSession $session): array
    {
        return RepoAnalysisTask::query()
            ->where('repo_analysis_session_id', $session->id)
            ->where('status', 'failed')
            ->get(['task_key', 'error_code', 'metadata_json'])
            ->filter(static function (RepoAnalysisTask $task): bool {
                $severity = strtolower((string) data_get($task->metadata_json, 'severity', 'critical'));

                return $severity === '' || $severity === 'critical';
            })
            ->map(static fn (RepoAnalysisTask $task): array => [
                'task_key' => (string) $task->task_key,
                'error_code' => is_string($task->error_code) ? $task->error_code : null,
            ])
            ->values()
            ->all();
    }

    private function hasNoTestsWarning(RepoAnalysisSession $session): bool
    {
        return RepoAnalysisArtifact::query()
            ->where('repo_analysis_session_id', $session->id)
            ->where('artifact_type', 'test_coverage_map')
            ->get(['payload_json'])
            ->contains(static function (RepoAnalysisArtifact $artifact): bool {
                $warnings = data_get($artifact->payload_json, 'warnings', []);
                if (! is_array($warnings)) {
                    return false;
                }

                foreach ($warnings as $warning) {
                    if (! is_array($warning)) {
                        continue;
                    }

                    if (($warning['code'] ?? null) === 'empty_test_suite') {
                        return true;
                    }
                }

                return false;
            });
    }
}

