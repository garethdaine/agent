<?php

declare(strict_types=1);

namespace App\Listeners\Compliance;

use App\Events\AgentJobRunFinished;
use App\Jobs\Compliance\LessonExtractionJob;
use App\Models\AgentJobRun;
use App\Support\Agent\FeatureFlagManager;
use Illuminate\Support\Facades\Log;

class LessonExtractionListener
{
    public function handle(AgentJobRunFinished $event): void
    {
        if (! app(FeatureFlagManager::class)->enabled(FeatureFlagManager::COMPLIANCE_LESSONS)) {
            return;
        }

        $run = $event->run;
        $status = $event->status;

        if ($status !== AgentJobRun::STATUS_FAILED) {
            return;
        }

        $run->loadMissing('job');
        if ($run->job === null) {
            return;
        }

        $lessonContent = $this->extractLessonContent($run);

        if ($lessonContent === null) {
            return;
        }

        try {
            LessonExtractionJob::dispatch(
                lessonContent: $lessonContent,
                source: 'auto_failure_extraction',
                projectDirectory: base_path(),
                context: [
                    'task_title' => $run->job->name ?? 'Unknown Job',
                    'task_category' => $run->job->task_category?->value ?? 'unknown',
                    'runner_type' => $run->job->runner_type ?? 'unknown',
                    'run_id' => $run->id,
                    'error_code' => $run->error_code,
                    'status' => $status,
                ],
            );
        } catch (\Throwable $e) {
            Log::warning('LessonExtractionListener: Failed to dispatch job', [
                'run_id' => $run->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function extractLessonContent(AgentJobRun $run): ?string
    {
        $metadata = (array) ($run->metadata_json ?? []);

        // Prefer the STAR-classified suggested lesson
        $suggestedLesson = $metadata['failure_mode_hint']['suggested_lesson'] ?? null;

        if (is_string($suggestedLesson) && trim($suggestedLesson) !== '') {
            return $suggestedLesson;
        }

        // Fall back to error_code + error_summary
        $errorCode = $run->error_code ?? null;
        $errorSummary = $run->error_summary ?? null;

        if ($errorCode === null && ($errorSummary === null || trim((string) $errorSummary) === '')) {
            return null;
        }

        return sprintf(
            'Agent run failed with error %s: %s',
            $errorCode ?? 'UNKNOWN',
            is_string($errorSummary) && trim($errorSummary) !== '' ? substr($errorSummary, 0, 300) : 'No details available'
        );
    }
}
