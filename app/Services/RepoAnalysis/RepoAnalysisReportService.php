<?php

declare(strict_types=1);

namespace App\Services\RepoAnalysis;

use App\Jobs\RepoAnalysis\GenerateRepoAnalysisReportJob;
use App\Jobs\RepoAnalysis\ValidateRepoAnalysisCoverageJob;
use App\Models\RepoAnalysisSession;
use App\Models\RepoAnalysisTask;
use App\Support\Agent\ErrorEnvelope;
use App\Support\RepoAnalysis\EventWriter;
use App\Support\RepoAnalysis\SessionStateTransitionService;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\File;

class RepoAnalysisReportService
{
    public function validateCoverage(RepoAnalysisSession $session): ?JsonResponse
    {
        if ((int) $session->phase !== 4) {
            return ErrorEnvelope::make('RUN_TRANSITION_CONFLICT', 'Coverage validation is only available in phase 4.', 409);
        }

        ValidateRepoAnalysisCoverageJob::dispatch((int) $session->id);

        return null;
    }

    public function generateReport(RepoAnalysisSession $session): ?JsonResponse
    {
        if ((int) $session->phase !== 5) {
            return ErrorEnvelope::make('RUN_TRANSITION_CONFLICT', 'Report generation is only available in phase 5.', 409);
        }

        GenerateRepoAnalysisReportJob::dispatch((int) $session->id);

        return null;
    }

    /**
     * @return array<int, string>
     */
    public function collectSessionFilePaths(RepoAnalysisSession $session): array
    {
        $paths = [];

        foreach ($session->reports()->get(['markdown_export_path', 'json_export_path']) as $report) {
            foreach ([(string) ($report->markdown_export_path ?? ''), (string) ($report->json_export_path ?? '')] as $path) {
                if ($path !== '') {
                    $paths[] = $path;
                }
            }
        }

        foreach ($session->artifacts()->get(['storage_path', 'payload_json']) as $artifact) {
            $storagePath = (string) ($artifact->storage_path ?? '');
            if ($storagePath !== '') {
                $paths[] = $storagePath;
            }

            $warningArtifactPath = (string) data_get($artifact->payload_json, 'warning_artifact_path', ''); // @phpstan-ignore property.notFound
            if ($warningArtifactPath !== '') {
                $paths[] = $warningArtifactPath;
            }
        }

        foreach ([
            (string) data_get($session->report_summary_json, 'markdown_export_path', ''),
            (string) data_get($session->report_summary_json, 'json_export_path', ''),
        ] as $path) {
            if ($path !== '') {
                $paths[] = $path;
            }
        }

        return array_values(array_unique($paths));
    }

    /**
     * @param  array<int, string>  $allowedRoots
     */
    public function deletePathIfAllowed(string $path, array $allowedRoots): bool
    {
        $normalizedPath = trim($path);
        if ($normalizedPath === '' || ! str_starts_with($normalizedPath, '/')) {
            return false;
        }

        $normalizedPath = rtrim($normalizedPath, '/');
        if ($normalizedPath === '') {
            return false;
        }

        if (! $this->isPathWithinRoots($normalizedPath, $allowedRoots)) {
            return false;
        }

        foreach ($allowedRoots as $root) {
            if ($normalizedPath === rtrim($root, '/')) {
                return false;
            }
        }

        if (is_file($normalizedPath)) {
            return File::delete($normalizedPath);
        }

        if (is_dir($normalizedPath)) {
            return File::deleteDirectory($normalizedPath);
        }

        return false;
    }

    /**
     * @param  array<int, string>  $allowedRoots
     */
    public function isPathWithinRoots(string $path, array $allowedRoots): bool
    {
        foreach ($allowedRoots as $root) {
            $normalizedRoot = rtrim($root, '/');
            if ($normalizedRoot === '') {
                continue;
            }

            if ($path === $normalizedRoot || str_starts_with($path, $normalizedRoot.'/')) {
                return true;
            }
        }

        return false;
    }

    public function resolvedProjectRoot(string $projectDirectory): ?string
    {
        if ($projectDirectory === '' || ! str_starts_with($projectDirectory, '/')) {
            return null;
        }

        $resolved = realpath($projectDirectory);
        if ($resolved === false || ! is_dir($resolved)) {
            return null;
        }

        return rtrim($resolved, '/');
    }

    public function reconcileStaleRunningTasks(RepoAnalysisSession $session): void
    {
        if (! in_array((string) $session->status, [
            SessionStateTransitionService::STATUS_PAUSED,
            SessionStateTransitionService::STATUS_FAILED,
            SessionStateTransitionService::STATUS_COMPLETED,
        ], true)) {
            return;
        }

        $runningTasks = RepoAnalysisTask::query()
            ->where('repo_analysis_session_id', $session->id)
            ->where('status', 'running')
            ->orderBy('id')
            ->get();

        if ($runningTasks->isEmpty()) {
            return;
        }

        $writer = new EventWriter($session);
        $defaultErrorCode = (string) $session->error_code === 'EXECUTE_TASK_TIMEOUT'
            ? 'EXECUTE_TASK_TIMEOUT'
            : 'EXECUTE_TASK_INTERRUPTED';

        foreach ($runningTasks as $task) {
            $metadata = is_array($task->metadata_json) ? $task->metadata_json : [];
            $metadata['state_reconciled_at'] = CarbonImmutable::now('UTC')->toIso8601String();
            $metadata['state_reconciled_reason'] = 'stale_running_task_while_session_not_executing';

            $errorCode = (string) ($task->error_code ?: $defaultErrorCode);
            $errorSummary = (string) ($task->error_summary ?: sprintf(
                'Task %s was still marked running while session is %s. Status reconciled to failed.',
                (string) $task->task_key,
                (string) $session->status
            ));

            $task->status = 'failed';
            $task->attempt_count = max(1, (int) $task->attempt_count);
            $task->error_code = $errorCode;
            $task->error_summary = $errorSummary;
            $task->finished_at = Carbon::now('UTC');
            $task->metadata_json = $metadata;
            $task->save();

            $writer->append('task_failed', [
                'task_key' => (string) $task->task_key,
                'failure_class' => 'state_reconciled',
                'failure_message' => $errorSummary,
                'timed_out' => $errorCode === 'EXECUTE_TASK_TIMEOUT',
            ], [
                'phase' => (int) $session->phase,
                'status' => (string) $session->status,
                'error_code' => $errorCode,
                'error_summary' => $errorSummary,
            ]);
        }
    }

    public function transformSession(RepoAnalysisSession $session): array
    {
        return [
            'id' => (int) $session->id,
            'user_id' => (int) $session->user_id,
            'name' => $session->name,
            'project_directory' => $session->project_directory,
            'analyzer_profile' => $session->analyzer_profile,
            'runner_type' => $session->runner_type,
            'phase' => (int) $session->phase,
            'status' => (string) $session->status,
            'snapshot_hash' => $session->snapshot_hash,
            'error_code' => $session->error_code,
            'error_summary' => $session->error_summary,
            'manifest_stats' => $session->manifest_stats_json ?? [],
            'report_summary' => $session->report_summary_json ?? [],
            'metadata' => $session->metadata_json ?? [],
            'started_at' => optional($session->started_at)?->toIso8601String(),
            'finished_at' => optional($session->finished_at)?->toIso8601String(),
            'created_at' => optional($session->created_at)?->toIso8601String(),
            'updated_at' => optional($session->updated_at)?->toIso8601String(),
            'deleted_at' => optional($session->deleted_at)?->toIso8601String(),
        ];
    }
}
