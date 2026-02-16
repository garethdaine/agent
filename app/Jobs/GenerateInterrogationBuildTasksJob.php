<?php

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

    public int $tries = 1;

    public int $timeout = 900;

    public function __construct(public int $sessionId, public ?string $notes = null)
    {
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
            $build['status'] = 'failed';
            $build['error'] = substr($throwable->getMessage(), 0, 1000);
            $build['failed_at'] = CarbonImmutable::now('UTC')->toIso8601String();
            $metadata['build'] = $build;
            $session->metadata_json = $metadata;
            $session->phase = InterrogationSession::PHASE_BUILD_TASKS;
            $session->status = InterrogationSession::STATUS_BUILD_TASKS;
            $session->save();

            $writer->appendError([
                'code' => 'BUILD_TASK_GENERATION_FAILED',
                'message' => $throwable->getMessage(),
            ]);
        }
    }
}
