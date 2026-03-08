<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\InterrogationBuildTask;
use App\Models\InterrogationSession;
use App\Support\Interrogation\BuildTaskGenerator;
use App\Support\Interrogation\InterrogationEventWriter;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

class GenerateInterrogationBuildTasksJob implements ShouldQueue
{
    use Queueable;

    private const MIN_GENERATION_TIMEOUT_SECONDS = 3600;

    private const MIN_JOB_TIMEOUT_SECONDS = 3900;

    public int $tries = 1;

    public int $timeout = self::MIN_JOB_TIMEOUT_SECONDS;

    public function __construct(public int $sessionId, public ?string $notes = null)
    {
        $this->timeout = max(self::MIN_JOB_TIMEOUT_SECONDS, (int) config('agent.interrogation.build_task_generation_job_timeout_seconds', 7500));
        $this->onConnection('redis');
        $this->onQueue('interrogation');
    }

    public function handle(BuildTaskGenerator $buildTaskGenerator): void
    {
        $session = InterrogationSession::query()->find($this->sessionId);

        if ($session === null) {
            return;
        }

        $writer = new InterrogationEventWriter($session);
        $now = CarbonImmutable::now('UTC');

        $metadata = (array) ($session->metadata_json ?? []);
        $build = is_array($metadata['build'] ?? null) ? $metadata['build'] : [];
        $build['status'] = 'generating_tasks';
        $build['error'] = null;
        $build['failed_at'] = null;
        $build['updated_at'] = $now->toIso8601String();
        $build['started_at'] = $build['started_at'] ?? $now->toIso8601String();
        $metadata['build'] = $build;
        $session->metadata_json = $metadata;
        $session->save();

        $writer->appendSystem([
            'notice' => 'build_task_generation_started',
            'message' => 'Build task generation started. Preparing executable task list.',
            'at' => $now->toIso8601String(),
        ]);

        try {
            $generated = $buildTaskGenerator->generate($session, $this->notes);
            $tasks = is_array($generated['tasks'] ?? null) ? array_values($generated['tasks']) : [];

            if ($tasks === []) {
                throw new \RuntimeException('No build tasks were produced by the runner.');
            }

            DB::transaction(function () use ($session, $tasks): void {
                InterrogationBuildTask::query()
                    ->where('interrogation_session_id', $session->id)
                    ->delete();

                foreach ($tasks as $index => $task) {
                    InterrogationBuildTask::query()->create([
                        'interrogation_session_id' => $session->id,
                        'sequence' => (int) ($task['sequence'] ?? ($index + 1)),
                        'title' => trim((string) ($task['title'] ?? 'Task '.($index + 1))),
                        'description' => trim((string) ($task['description'] ?? '')),
                        'instructions_markdown' => trim((string) ($task['instructions_markdown'] ?? '')),
                        'status' => InterrogationBuildTask::STATUS_PENDING,
                        'attempt_count' => 0,
                        'metadata_json' => [
                            'generated_at' => CarbonImmutable::now('UTC')->toIso8601String(),
                        ],
                    ]);
                }

                $metadata = (array) ($session->metadata_json ?? []);
                $build = is_array($metadata['build'] ?? null) ? $metadata['build'] : [];

                $build['status'] = 'ready';
                $build['generated_at'] = CarbonImmutable::now('UTC')->toIso8601String();
                $build['task_count'] = count($tasks);
                $build['error'] = null;
                $build['active_task_id'] = null;
                $build['active_run_id'] = null;
                $build['tasks_approved_at'] = null;
                $build['tasks_approved_by_user_id'] = null;
                $build['task_provider_sync'] = [
                    'status' => 'idle',
                    'driver' => null,
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
                $session->phase = InterrogationSession::PHASE_BUILD_TASKS;
                $session->status = InterrogationSession::STATUS_BUILD_TASKS;
                $session->error_code = null;
                $session->error_summary = null;
                $session->save();
            });

            $writer->appendSystem([
                'notice' => 'build_tasks_generated',
                'count' => count($tasks),
                'at' => CarbonImmutable::now('UTC')->toIso8601String(),
            ]);
        } catch (\Throwable $throwable) {
            report($throwable);

            $metadata = (array) ($session->metadata_json ?? []);
            $build = is_array($metadata['build'] ?? null) ? $metadata['build'] : [];
            $normalizedError = $this->normalizeBuildGenerationError($throwable);

            $build['status'] = 'failed';
            $build['error'] = $normalizedError;
            $build['failed_at'] = CarbonImmutable::now('UTC')->toIso8601String();
            $build['updated_at'] = CarbonImmutable::now('UTC')->toIso8601String();
            $metadata['build'] = $build;
            $session->metadata_json = $metadata;
            if ((int) $session->phase === InterrogationSession::PHASE_BUILD_TASKS) {
                $session->phase = InterrogationSession::PHASE_BUILD_TASKS;
                $session->status = InterrogationSession::STATUS_BUILD_TASKS;
            } else {
                $session->phase = InterrogationSession::PHASE_BUILD_RULES;
                $session->status = InterrogationSession::STATUS_BUILD_RULES;
            }
            $session->error_code = 'BUILD_TASK_GENERATION_FAILED';
            $session->error_summary = $normalizedError;
            $session->save();

            $writer->appendError([
                'code' => 'BUILD_TASK_GENERATION_FAILED',
                'message' => $normalizedError,
            ]);
        }
    }

    private function normalizeBuildGenerationError(\Throwable $throwable): string
    {
        $message = trim((string) $throwable->getMessage());
        $timeoutSeconds = max(self::MIN_GENERATION_TIMEOUT_SECONDS, (int) config('agent.interrogation.build_task_generation_timeout_seconds', 7200));
        if ($message === '') {
            return 'Build task generation failed unexpectedly.';
        }

        if (str_contains($message, 'exceeded the timeout of')) {
            return "Build task generation timed out after {$timeoutSeconds} seconds. Try again or reduce plan scope for task generation.";
        }

        if (str_contains($message, 'The process "') || str_contains($message, '--system-prompt') || str_contains($message, '--json-schema')) {
            return 'Build task generation failed while invoking the AI runner. Try again. If this keeps happening, reduce plan scope or check runner health.';
        }

        $firstLine = trim((string) strtok($message, "\n"));

        return substr($firstLine !== '' ? $firstLine : $message, 0, 300);
    }
}
