<?php

namespace App\Jobs;

use App\Models\InterrogationBuildTask;
use App\Models\InterrogationSession;
use App\Support\Interrogation\BuildTaskGenerator;
use App\Support\Interrogation\InterrogationEventWriter;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RegenerateInterrogationBuildTaskJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 900;

    public function __construct(
        public int $sessionId,
        public int $taskId,
        public string $amendNotes,
    ) {
        $this->onConnection('redis');
        $this->onQueue('interrogation');
    }

    public function handle(BuildTaskGenerator $buildTaskGenerator): void
    {
        $session = InterrogationSession::query()->find($this->sessionId);
        if ($session === null) {
            return;
        }

        $task = InterrogationBuildTask::query()
            ->where('interrogation_session_id', $session->id)
            ->find($this->taskId);

        if ($task === null) {
            return;
        }

        $writer = new InterrogationEventWriter($session);
        $now = CarbonImmutable::now('UTC');

        $writer->appendSystem([
            'notice' => 'build_task_regeneration_started',
            'task_id' => (int) $task->id,
            'at' => $now->toIso8601String(),
        ]);

        try {
            $payload = $buildTaskGenerator->regenerateTask($session, $task, $this->amendNotes);

            $task->title = trim((string) ($payload['title'] ?? $task->title));
            $task->description = $this->normalizedNullableText($payload['description'] ?? $task->description);
            $task->instructions_markdown = $this->normalizedNullableText($payload['instructions_markdown'] ?? $task->instructions_markdown);
            $task->status = InterrogationBuildTask::STATUS_PENDING;
            $task->attempt_count = 0;
            $task->agent_job_run_id = null;
            $task->last_error = null;
            $task->started_at = null;
            $task->finished_at = null;

            $taskMetadata = is_array($task->metadata_json) ? $task->metadata_json : [];
            $taskMetadata['regeneration'] = [
                'status' => 'completed',
                'requested_at' => data_get($taskMetadata, 'regeneration.requested_at'),
                'requested_by_user_id' => data_get($taskMetadata, 'regeneration.requested_by_user_id'),
                'amend_notes' => $this->amendNotes,
                'completed_at' => CarbonImmutable::now('UTC')->toIso8601String(),
                'error' => null,
            ];
            $task->metadata_json = $taskMetadata;
            $task->save();

            $this->resetBuildMetadataAfterTaskChange($session);

            $writer->appendSystem([
                'notice' => 'build_task_regenerated',
                'task_id' => (int) $task->id,
                'at' => CarbonImmutable::now('UTC')->toIso8601String(),
            ]);
        } catch (\Throwable $throwable) {
            report($throwable);

            $normalizedError = $this->normalizeError($throwable);

            $taskMetadata = is_array($task->metadata_json) ? $task->metadata_json : [];
            $taskMetadata['regeneration'] = [
                'status' => 'failed',
                'requested_at' => data_get($taskMetadata, 'regeneration.requested_at'),
                'requested_by_user_id' => data_get($taskMetadata, 'regeneration.requested_by_user_id'),
                'amend_notes' => $this->amendNotes,
                'completed_at' => CarbonImmutable::now('UTC')->toIso8601String(),
                'error' => $normalizedError,
            ];
            $task->metadata_json = $taskMetadata;
            $task->last_error = $normalizedError;
            $task->save();

            $metadata = is_array($session->metadata_json) ? $session->metadata_json : [];
            $build = is_array($metadata['build'] ?? null) ? $metadata['build'] : [];
            $build['status'] = 'ready';
            $build['error'] = $normalizedError;
            $build['updated_at'] = CarbonImmutable::now('UTC')->toIso8601String();
            $metadata['build'] = $build;
            $session->metadata_json = $metadata;
            $session->save();

            $writer->appendError([
                'code' => 'BUILD_TASK_REGENERATION_FAILED',
                'message' => $normalizedError,
                'task_id' => (int) $task->id,
            ]);
        }
    }

    private function resetBuildMetadataAfterTaskChange(InterrogationSession $session): void
    {
        $metadata = is_array($session->metadata_json) ? $session->metadata_json : [];
        $build = is_array($metadata['build'] ?? null) ? $metadata['build'] : [];

        $build['status'] = 'ready';
        $build['task_count'] = (int) $session->buildTasks()->count();
        $build['error'] = null;
        $build['active_task_id'] = null;
        $build['active_run_id'] = null;
        $build['tasks_approved_at'] = null;
        $build['tasks_approved_by_user_id'] = null;
        $build['task_provider_sync'] = [
            'status' => 'idle',
            'driver' => null,
            'project_mode' => 'create_new',
            'project_id' => null,
            'project_name' => null,
            'project_url' => null,
            'synced_task_count' => 0,
            'error' => null,
            'updated_at' => CarbonImmutable::now('UTC')->toIso8601String(),
        ];
        $build['updated_at'] = CarbonImmutable::now('UTC')->toIso8601String();

        $metadata['build'] = $build;
        $session->metadata_json = $metadata;
        $session->save();
    }

    private function normalizedNullableText(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }

    private function normalizeError(\Throwable $throwable): string
    {
        $message = trim((string) $throwable->getMessage());
        if ($message === '') {
            return 'Build task regeneration failed unexpectedly.';
        }

        if (str_contains($message, 'exceeded the timeout of')) {
            return 'Build task regeneration timed out. Try again with more specific amend notes.';
        }

        $firstLine = trim((string) strtok($message, "\n"));

        return substr($firstLine !== '' ? $firstLine : $message, 0, 300);
    }
}
