<?php

declare(strict_types=1);

namespace App\Services\Interrogation;

use App\Jobs\ExecuteInterrogationBuildJob;
use App\Jobs\GenerateInterrogationBuildTasksJob;
use App\Jobs\RegenerateInterrogationBuildTaskJob;
use App\Jobs\SyncInterrogationTasksToTaskProviderJob;
use App\Models\AgentJobRun;
use App\Models\AgentRunEvent;
use App\Models\ConnectedProvider;
use App\Models\InterrogationBuildTask;
use App\Models\InterrogationSession;
use App\Support\Agent\RunStateTransitionService;
use App\Support\Interrogation\InterrogationEventWriter;
use App\Support\Interrogation\PlanPayloadGuard;
use App\Support\Interrogation\SessionStateTransitionService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class InterrogationBuildService
{
    /**
     * @return array<string, mixed>
     */
    public function buildMetadata(InterrogationSession $session): array
    {
        $metadata = is_array($session->metadata_json) ? $session->metadata_json : [];

        return is_array($metadata['build'] ?? null) ? $metadata['build'] : [];
    }

    /**
     * @param  array<string, mixed>  $build
     */
    public function saveBuildMetadata(InterrogationSession $session, array $build): void
    {
        $metadata = is_array($session->metadata_json) ? $session->metadata_json : [];
        $metadata['build'] = $build;
        $session->metadata_json = $metadata;
        $session->save();
    }

    public function hasMeaningfulPlan(InterrogationSession $session): bool
    {
        if (! is_array($session->plan_json)) {
            return false;
        }

        $guard = new PlanPayloadGuard;
        $validation = $guard->validate($session->plan_json);

        return (bool) $validation['valid'];
    }

    /**
     * @return array{provided:bool,rules:array<int,array<string,mixed>>}
     */
    public function resolveProjectRulesPayload(Request $request): array
    {
        $provided = $request->exists('project_rules') || $request->hasFile('project_rule_files');
        $rawRules = $request->input('project_rules');

        if (is_string($rawRules)) {
            $decoded = json_decode($rawRules, true);
            if ($rawRules !== '' && ! is_array($decoded)) {
                throw ValidationException::withMessages([
                    'project_rules' => 'project_rules must be a valid JSON array when provided as text.',
                ]);
            }

            $rawRules = $decoded;
        }

        if ($rawRules === null) {
            $rawRules = [];
        }

        if (! is_array($rawRules)) {
            throw ValidationException::withMessages([
                'project_rules' => 'project_rules must be an array.',
            ]);
        }

        $rules = $this->normalizedProjectRules($rawRules, true);
        $uploadedRuleFiles = $request->file('project_rule_files', []);
        $uploads = is_array($uploadedRuleFiles) ? $uploadedRuleFiles : [$uploadedRuleFiles];
        $uploadRules = $this->normalizedProjectRulesFromUploads($uploads);

        return [
            'provided' => $provided,
            'rules' => array_merge($rules, $uploadRules),
        ];
    }

    public function generateBuildTasks(
        InterrogationSession $session,
        Request $request,
    ): void {
        $validated = $request->validate([
            'notes' => ['nullable', 'string', 'max:6000'],
            'project_rules' => ['nullable'],
            'project_rule_files' => ['nullable', 'array', 'max:20'],
            'project_rule_files.*' => ['file', 'max:2048', 'mimes:md,markdown,txt'],
        ]);

        $projectRulePayload = $this->resolveProjectRulesPayload($request);

        $build = $this->buildMetadata($session);
        $build['status'] = 'generating_tasks';
        $build['notes'] = trim((string) ($validated['notes'] ?? ''));
        $build['task_count'] = 0;
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
        if ($projectRulePayload['provided']) {
            $build['project_rules'] = $projectRulePayload['rules'];
            $build['project_rules_updated_at'] = CarbonImmutable::now('UTC')->toIso8601String();
        }
        $build['updated_at'] = CarbonImmutable::now('UTC')->toIso8601String();

        $this->saveBuildMetadata($session, $build);

        GenerateInterrogationBuildTasksJob::dispatch(
            (int) $session->id,
            $build['notes'] !== '' ? $build['notes'] : null,
        );
    }

    public function storeBuildTask(
        InterrogationSession $session,
        array $validated,
    ): InterrogationBuildTask {
        $title = trim((string) ($validated['title'] ?? ''));
        if ($title === '') {
            throw ValidationException::withMessages([
                'title' => 'Task title is required.',
            ]);
        }

        /** @var InterrogationBuildTask $task */
        $task = InterrogationBuildTask::query()->create([
            'interrogation_session_id' => (int) $session->id,
            'sequence' => ((int) ($session->buildTasks()->max('sequence') ?? 0)) + 1,
            'title' => $title,
            'description' => $this->normalizedNullableText($validated['description'] ?? null),
            'instructions_markdown' => $this->normalizedNullableText($validated['instructions_markdown'] ?? null),
            'status' => InterrogationBuildTask::STATUS_PENDING,
            'attempt_count' => 0,
            'agent_job_run_id' => null,
            'last_error' => null,
            'metadata_json' => [
                'source' => 'manual',
                'created_at' => CarbonImmutable::now('UTC')->toIso8601String(),
            ],
        ]);

        return $task;
    }

    /**
     * @param  array<int, int>  $requestedIds
     */
    public function reorderBuildTasks(
        InterrogationSession $session,
        array $requestedIds,
    ): void {
        $tasks = $session->buildTasks()->ordered()->get(['id', 'sequence']);

        if ($tasks->isEmpty()) {
            return;
        }

        $existingIds = $tasks
            ->pluck('id')
            ->map(static fn (mixed $value): int => (int) $value)
            ->values()
            ->all();

        $requestedSorted = $requestedIds;
        $existingSorted = $existingIds;
        sort($requestedSorted);
        sort($existingSorted);

        if (count($requestedIds) !== count($existingIds) || $requestedSorted !== $existingSorted) {
            throw ValidationException::withMessages([
                'task_ids' => 'Task order payload must include every build task exactly once.',
            ]);
        }

        $temporaryOffset = max(
            100,
            (int) $tasks->max('sequence') + count($requestedIds) + 10,
        );

        DB::transaction(function () use ($requestedIds, $temporaryOffset): void {
            foreach ($requestedIds as $index => $taskId) {
                InterrogationBuildTask::query()
                    ->whereKey($taskId)
                    ->update(['sequence' => $temporaryOffset + $index + 1]);
            }

            foreach ($requestedIds as $index => $taskId) {
                InterrogationBuildTask::query()
                    ->whereKey($taskId)
                    ->update(['sequence' => $index + 1]);
            }
        });

        $this->invalidateBuildTaskApproval($session);
    }

    public function updateBuildTask(
        InterrogationBuildTask $task,
        InterrogationSession $session,
        array $validated,
    ): InterrogationBuildTask {
        if (array_key_exists('title', $validated)) {
            $title = trim((string) $validated['title']);
            if ($title === '') {
                throw ValidationException::withMessages([
                    'title' => 'Task title is required.',
                ]);
            }

            $task->title = $title;
        }

        if (array_key_exists('description', $validated)) {
            $task->description = $this->normalizedNullableText($validated['description']);
        }

        if (array_key_exists('instructions_markdown', $validated)) {
            $task->instructions_markdown = $this->normalizedNullableText($validated['instructions_markdown']);
        }

        $task->status = InterrogationBuildTask::STATUS_PENDING;
        $task->attempt_count = 0;
        $task->agent_job_run_id = null;
        $task->last_error = null;
        $task->started_at = null;
        $task->finished_at = null;
        $task->save();

        $this->invalidateBuildTaskApproval($session);

        return $task;
    }

    public function destroyBuildTask(
        InterrogationBuildTask $task,
        InterrogationSession $session,
    ): void {
        $task->delete();
        $this->resequenceBuildTasks($session);
        $this->invalidateBuildTaskApproval($session);
    }

    public function regenerateBuildTask(
        InterrogationBuildTask $task,
        InterrogationSession $session,
        string $amendNotes,
        ?string $additionalContext,
        int $userId,
    ): void {
        $metadata = is_array($task->metadata_json) ? $task->metadata_json : [];
        $metadata['regeneration'] = [
            'status' => 'queued',
            'requested_at' => CarbonImmutable::now('UTC')->toIso8601String(),
            'requested_by_user_id' => $userId,
            'amend_notes' => $amendNotes,
            'additional_context' => $additionalContext,
        ];
        $task->metadata_json = $metadata;
        $task->save();

        $this->invalidateBuildTaskApproval($session);

        RegenerateInterrogationBuildTaskJob::dispatch(
            (int) $session->id,
            (int) $task->id,
            $amendNotes,
            $additionalContext,
        );
    }

    /**
     * @return array{approved:bool,tasks_approved_at:string,task_provider_sync_queued:bool}
     */
    public function approveBuildTasks(
        InterrogationSession $session,
        int $userId,
    ): array {
        $build = $this->buildMetadata($session);
        $approvedAt = CarbonImmutable::now('UTC')->toIso8601String();
        $build['tasks_approved_at'] = $approvedAt;
        $build['tasks_approved_by_user_id'] = $userId;
        $build['updated_at'] = CarbonImmutable::now('UTC')->toIso8601String();

        $provider = $session->providerIntegrations()
            ->where('category', 'task_management')
            ->orderByDesc('id')
            ->first();

        $syncQueued = false;

        if ($provider instanceof ConnectedProvider) {
            $projectSync = $this->providerProjectSyncPreference($provider);
            $build['task_provider_sync'] = [
                'driver' => (string) $provider->driver,
                'status' => 'queued',
                'project_mode' => $projectSync['mode'],
                'project_id' => $projectSync['project_id'],
                'project_name' => $projectSync['project_name'],
                'project_url' => $projectSync['project_url'],
                'synced_task_count' => 0,
                'error' => null,
                'updated_at' => CarbonImmutable::now('UTC')->toIso8601String(),
            ];

            $syncQueued = true;
        }

        $this->saveBuildMetadata($session, $build);

        if ($syncQueued) {
            SyncInterrogationTasksToTaskProviderJob::dispatch((int) $session->id);
        }

        return [
            'approved' => true,
            'tasks_approved_at' => $approvedAt,
            'task_provider_sync_queued' => $syncQueued,
        ];
    }

    public function startBuild(
        InterrogationSession $session,
        SessionStateTransitionService $transitions,
        bool $restartFailed,
        bool $restartAll,
    ): void {
        $tasks = $session->buildTasks()->ordered()->get();
        $build = $this->buildMetadata($session);
        $currentBuildStatus = $this->normalizedBuildStatus($build);

        if ($restartAll) {
            $restartFailed = true;
        }

        if ($restartFailed) {
            foreach ($tasks as $task) {
                if ($restartAll) {
                    if ($task->status === InterrogationBuildTask::STATUS_PENDING) {
                        continue;
                    }

                    $task->status = InterrogationBuildTask::STATUS_PENDING;
                    $task->agent_job_run_id = null;
                    $task->last_error = null;
                    $task->started_at = null;
                    $task->finished_at = null;
                    $task->save();

                    continue;
                }

                if (
                    $task->status === InterrogationBuildTask::STATUS_FAILED
                    || $task->status === InterrogationBuildTask::STATUS_BLOCKED
                    || $task->status === InterrogationBuildTask::STATUS_IN_PROGRESS
                    || ($task->status === InterrogationBuildTask::STATUS_COMPLETED && $currentBuildStatus === 'completed')
                ) {
                    $task->status = InterrogationBuildTask::STATUS_PENDING;
                    $task->agent_job_run_id = null;
                    $task->last_error = null;
                    $task->started_at = null;
                    $task->finished_at = null;
                    $task->save();
                }
            }
        }

        $build['status'] = 'running';
        $build['error'] = null;
        $build['completion_summary'] = null;
        $build['pause_reason'] = null;
        $build['paused_at'] = null;
        $build['active_task_id'] = null;
        $build['active_run_id'] = null;
        $build['started_at'] = $build['started_at'] ?? CarbonImmutable::now('UTC')->toIso8601String();
        $build['updated_at'] = CarbonImmutable::now('UTC')->toIso8601String();
        $build['approval_required'] = false;
        $build['permission_required'] = false;
        $build['permission_excerpt'] = null;
        $build['clarification_required'] = false;
        $build['clarification_excerpt'] = null;
        $build['rate_limit_detected'] = false;
        $build['rate_limit_reset_at'] = null;
        $build['rate_limit_excerpt'] = null;

        $this->saveBuildMetadata($session, $build);

        if ((int) $session->phase === InterrogationSession::PHASE_BUILD_TASKS) {
            $moved = $transitions->transitionPhase(
                (int) $session->id,
                InterrogationSession::PHASE_BUILD_TASKS,
                InterrogationSession::PHASE_BUILD_EXECUTION,
                InterrogationSession::STATUS_BUILD_EXECUTING,
                [InterrogationSession::STATUS_BUILD_TASKS, InterrogationSession::STATUS_PAUSED],
            );

            if ($moved) {
                $session->refresh();
                $writer = new InterrogationEventWriter($session);
                $writer->appendPhaseTransition(
                    InterrogationSession::PHASE_BUILD_TASKS,
                    InterrogationSession::PHASE_BUILD_EXECUTION,
                    (string) $session->status,
                    ['at' => CarbonImmutable::now('UTC')->toIso8601String()],
                );
            }
        }

        ExecuteInterrogationBuildJob::dispatch((int) $session->id);
    }

    public function pauseBuild(
        InterrogationSession $session,
        RunStateTransitionService $runTransitions,
    ): void {
        $build = $this->buildMetadata($session);

        $build['status'] = 'paused';
        $build['pause_reason'] = 'user';
        $build['paused_at'] = CarbonImmutable::now('UTC')->toIso8601String();
        $build['updated_at'] = CarbonImmutable::now('UTC')->toIso8601String();

        /** @var InterrogationBuildTask|null $activeTask */
        $activeTask = $session->buildTasks()
            ->with('run')
            ->where('status', InterrogationBuildTask::STATUS_IN_PROGRESS)
            ->orderBy('sequence')
            ->first();

        if ($activeTask?->run !== null && in_array((string) $activeTask->run->status, [
            AgentJobRun::STATUS_QUEUED,
            AgentJobRun::STATUS_STARTING,
            AgentJobRun::STATUS_RUNNING,
        ], true)) {
            $runTransitions->transition(
                (int) $activeTask->run->id,
                [AgentJobRun::STATUS_QUEUED, AgentJobRun::STATUS_STARTING, AgentJobRun::STATUS_RUNNING],
                AgentJobRun::STATUS_STOPPING,
            );

            if (is_int($activeTask->run->pid) && $activeTask->run->pid > 0 && function_exists('posix_kill')) {
                @posix_kill((int) $activeTask->run->pid, SIGTERM);
            }
        }

        $this->saveBuildMetadata($session, $build);
    }

    public function resumeBuild(
        InterrogationSession $session,
        SessionStateTransitionService $transitions,
    ): void {
        $tasks = $session->buildTasks()->with('run')->ordered()->get();

        foreach ($tasks as $task) {
            if ($task->status === InterrogationBuildTask::STATUS_BLOCKED) {
                $task->status = InterrogationBuildTask::STATUS_PENDING;
                $task->agent_job_run_id = null;
                $task->last_error = null;
                $task->started_at = null;
                $task->finished_at = null;
                $task->save();

                continue;
            }

            if ($task->status === InterrogationBuildTask::STATUS_IN_PROGRESS) {
                $run = $task->run;
                if ($run === null || in_array((string) $run->status, AgentJobRun::TERMINAL_STATUSES, true)) {
                    $task->status = InterrogationBuildTask::STATUS_PENDING;
                    $task->agent_job_run_id = null;
                    $task->last_error = null;
                    $task->started_at = null;
                    $task->finished_at = null;
                    $task->save();
                }
            }
        }

        $build = $this->buildMetadata($session);
        $build['status'] = 'running';
        $build['pause_reason'] = null;
        $build['paused_at'] = null;
        $build['error'] = null;
        $build['completion_summary'] = null;
        $build['active_task_id'] = null;
        $build['active_run_id'] = null;
        $build['updated_at'] = CarbonImmutable::now('UTC')->toIso8601String();
        $build['approval_required'] = false;
        $build['permission_required'] = false;
        $build['permission_excerpt'] = null;
        $build['clarification_required'] = false;
        $build['clarification_excerpt'] = null;
        $build['rate_limit_detected'] = false;
        $build['rate_limit_reset_at'] = null;
        $build['rate_limit_excerpt'] = null;
        $build['task_review_summary'] = null;
        $build['task_review_task_id'] = null;

        $this->saveBuildMetadata($session, $build);

        $transitions->transition(
            (int) $session->id,
            [InterrogationSession::STATUS_PAUSED, InterrogationSession::STATUS_BUILD_EXECUTING],
            InterrogationSession::STATUS_BUILD_EXECUTING,
        );

        ExecuteInterrogationBuildJob::dispatch((int) $session->id);
    }

    /**
     * @return array{task_id:int,build_status:string}
     */
    public function clarifyBuild(
        InterrogationSession $session,
        InterrogationBuildTask $targetTask,
        string $message,
        int $userId,
        RunStateTransitionService $runTransitions,
    ): array {
        $taskMetadata = is_array($targetTask->metadata_json) ? $targetTask->metadata_json : [];
        $clarifications = is_array($taskMetadata['clarifications'] ?? null) ? $taskMetadata['clarifications'] : [];
        $clarifications[] = [
            'message' => $message,
            'by_user_id' => $userId,
            'at' => CarbonImmutable::now('UTC')->toIso8601String(),
        ];
        $taskMetadata['clarifications'] = array_values($clarifications);
        $targetTask->metadata_json = $taskMetadata;

        $build = $this->buildMetadata($session);
        $build['last_clarification_at'] = CarbonImmutable::now('UTC')->toIso8601String();
        $build['updated_at'] = CarbonImmutable::now('UTC')->toIso8601String();
        $build['clarification_required'] = false;
        $build['clarification_excerpt'] = null;

        if ($targetTask->status === InterrogationBuildTask::STATUS_IN_PROGRESS) {
            $targetTask->status = InterrogationBuildTask::STATUS_BLOCKED;
            $targetTask->last_error = 'Clarification submitted. Resume build to retry this task with the new context.';
            $targetTask->finished_at = CarbonImmutable::now('UTC');

            if ($targetTask->run !== null && in_array((string) $targetTask->run->status, [
                AgentJobRun::STATUS_QUEUED,
                AgentJobRun::STATUS_STARTING,
                AgentJobRun::STATUS_RUNNING,
            ], true)) {
                $runTransitions->transition(
                    (int) $targetTask->run->id,
                    [AgentJobRun::STATUS_QUEUED, AgentJobRun::STATUS_STARTING, AgentJobRun::STATUS_RUNNING],
                    AgentJobRun::STATUS_STOPPING,
                );

                if (is_int($targetTask->run->pid) && $targetTask->run->pid > 0 && function_exists('posix_kill')) {
                    @posix_kill((int) $targetTask->run->pid, SIGTERM);
                }
            }

            $build['status'] = 'paused';
            $build['pause_reason'] = 'clarification';
            $build['paused_at'] = CarbonImmutable::now('UTC')->toIso8601String();
        }

        $targetTask->save();
        $this->saveBuildMetadata($session, $build);

        $writer = new InterrogationEventWriter($session);
        $writer->appendAnnotation([
            'type' => 'build_clarification',
            'task_id' => (int) $targetTask->id,
            'message' => $message,
        ]);

        if (($build['status'] ?? null) === 'running') {
            ExecuteInterrogationBuildJob::dispatch((int) $session->id);
        }

        return [
            'task_id' => (int) $targetTask->id,
            'build_status' => (string) ($build['status'] ?? 'ready'),
        ];
    }

    // -- Helpers --

    public function buildTaskEditingConflict(InterrogationSession $session): ?string
    {
        if ((int) $session->phase !== InterrogationSession::PHASE_BUILD_TASKS) {
            return 'Build tasks can only be managed from the build tasks phase.';
        }

        if (! in_array((string) $session->status, [
            InterrogationSession::STATUS_BUILD_TASKS,
            InterrogationSession::STATUS_PAUSED,
            InterrogationSession::STATUS_FAILED,
        ], true)) {
            return 'Build tasks cannot be managed in the current session state.';
        }

        $build = $this->buildMetadata($session);
        if (($build['status'] ?? null) === 'generating_tasks') {
            return 'Build tasks are still being generated.';
        }

        return null;
    }

    public function resequenceBuildTasks(InterrogationSession $session): void
    {
        $tasks = $session->buildTasks()->ordered()->get();

        foreach ($tasks as $index => $task) {
            $nextSequence = $index + 1;
            if ((int) $task->sequence === $nextSequence) {
                continue;
            }

            $task->sequence = $nextSequence;
            $task->save();
        }
    }

    public function invalidateBuildTaskApproval(InterrogationSession $session): void
    {
        $build = $this->buildMetadata($session);
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

        $this->saveBuildMetadata($session, $build);
    }

    public function checkApproveTasksPreconditions(InterrogationSession $session): ?string
    {
        if ((int) $session->phase !== InterrogationSession::PHASE_BUILD_TASKS) {
            return 'Build task approval is only available from build tasks phase.';
        }

        $tasks = $session->buildTasks()->ordered()->get();
        if ($tasks->isEmpty()) {
            return 'No build tasks are available to approve.';
        }

        $build = $this->buildMetadata($session);
        if (($build['status'] ?? null) === 'generating_tasks') {
            return 'Build tasks are still being generated.';
        }

        return null;
    }

    public function checkStartBuildPreconditions(InterrogationSession $session, bool $restartFailed = false): ?string
    {
        if (! in_array((int) $session->phase, [InterrogationSession::PHASE_BUILD_TASKS, InterrogationSession::PHASE_BUILD_EXECUTION], true)) {
            return 'Build can only start from build phases.';
        }

        if ($session->approved_at === null) {
            return 'Plan must be approved before starting build.';
        }

        $tasks = $session->buildTasks()->ordered()->get();
        if ($tasks->isEmpty()) {
            return 'No build tasks are available. Generate build tasks first.';
        }

        $build = $this->buildMetadata($session);
        if (($build['status'] ?? null) === 'generating_tasks') {
            return 'Build tasks are still being generated.';
        }

        if (! is_string($build['tasks_approved_at'] ?? null) || trim((string) $build['tasks_approved_at']) === '') {
            return 'Build tasks must be approved before execution starts.';
        }

        $taskProviderSync = is_array($build['task_provider_sync'] ?? null) ? $build['task_provider_sync'] : [];
        if (in_array((string) ($taskProviderSync['status'] ?? ''), ['queued', 'syncing'], true)) {
            return 'Task provider sync is still running. Wait for completion before starting build.';
        }

        $currentBuildStatus = $this->normalizedBuildStatus($build);
        if ($currentBuildStatus === 'completed' && ! $restartFailed) {
            return 'Build is already completed. Use restart_failed or restart_all to re-run.';
        }

        return null;
    }

    public function checkPauseBuildPreconditions(InterrogationSession $session): ?string
    {
        if ((int) $session->phase !== InterrogationSession::PHASE_BUILD_EXECUTION) {
            return 'Build execution is not active.';
        }

        $build = $this->buildMetadata($session);
        if (($build['status'] ?? null) !== 'running' && ($build['status'] ?? null) !== 'paused') {
            return 'Build is not running.';
        }

        return null;
    }

    public function isBuildAlreadyPaused(InterrogationSession $session): bool
    {
        return ($this->buildMetadata($session)['status'] ?? null) === 'paused';
    }

    public function checkResumeBuildPreconditions(InterrogationSession $session): ?string
    {
        if ((int) $session->phase !== InterrogationSession::PHASE_BUILD_EXECUTION) {
            return 'Build execution is not active.';
        }

        $build = $this->buildMetadata($session);
        if (($build['status'] ?? null) !== 'paused') {
            return 'Build is not paused.';
        }

        $tasks = $session->buildTasks()->with('run')->ordered()->get();
        if ($tasks->isEmpty()) {
            return 'No build tasks are available to resume.';
        }

        return null;
    }

    public function checkGenerateTasksPreconditions(InterrogationSession $session): ?string
    {
        if (
            ! in_array((int) $session->phase, [InterrogationSession::PHASE_BUILD_RULES, InterrogationSession::PHASE_BUILD_TASKS], true)
            || ! in_array($session->status, [InterrogationSession::STATUS_BUILD_RULES, InterrogationSession::STATUS_BUILD_TASKS, InterrogationSession::STATUS_PAUSED], true)
        ) {
            return 'Session is not in build rules or build tasks phase.';
        }

        if ($session->approved_at === null || ! $this->hasMeaningfulPlan($session)) {
            return 'Session is not ready to generate build tasks.';
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $build
     */
    public function normalizedBuildStatus(array $build): string
    {
        $status = trim((string) ($build['status'] ?? ''));

        return $status !== '' ? $status : 'idle';
    }

    /**
     * @return array<string, mixed>
     */
    public function transformBuildState(InterrogationSession $session, bool $includeDetails): array
    {
        $build = $this->buildMetadata($session);
        $status = $this->normalizedBuildStatus($build);

        if (! $includeDetails) {
            $projectRules = $this->normalizedProjectRules((array) ($build['project_rules'] ?? []));
            $taskProviderSync = is_array($build['task_provider_sync'] ?? null) ? $build['task_provider_sync'] : [];

            return [
                'status' => $status,
                'summary' => [
                    'total' => (int) ($build['task_count'] ?? 0),
                    'pending' => null,
                    'in_progress' => null,
                    'blocked' => null,
                    'completed' => null,
                    'failed' => null,
                    'skipped' => null,
                ],
                'active_task_id' => isset($build['active_task_id']) ? (int) $build['active_task_id'] : null,
                'active_run_id' => isset($build['active_run_id']) ? (int) $build['active_run_id'] : null,
                'project_rules_count' => count($projectRules),
                'tasks_approved_at' => isset($build['tasks_approved_at']) ? (string) $build['tasks_approved_at'] : null,
                'task_provider_sync' => [
                    'driver' => isset($taskProviderSync['driver']) ? (string) $taskProviderSync['driver'] : null,
                    'status' => isset($taskProviderSync['status']) ? (string) $taskProviderSync['status'] : 'idle',
                    'project_mode' => isset($taskProviderSync['project_mode']) ? (string) $taskProviderSync['project_mode'] : 'create_new',
                    'project_url' => isset($taskProviderSync['project_url']) ? (string) $taskProviderSync['project_url'] : null,
                    'error' => isset($taskProviderSync['error']) ? (string) $taskProviderSync['error'] : null,
                    'updated_at' => isset($taskProviderSync['updated_at']) ? (string) $taskProviderSync['updated_at'] : null,
                ],
                'updated_at' => $build['updated_at'] ?? null,
            ];
        }

        $tasks = $session->buildTasks()->with('run')->ordered()->get();

        $summary = [
            'total' => $tasks->count(),
            'pending' => $tasks->where('status', InterrogationBuildTask::STATUS_PENDING)->count(),
            'in_progress' => $tasks->where('status', InterrogationBuildTask::STATUS_IN_PROGRESS)->count(),
            'blocked' => $tasks->where('status', InterrogationBuildTask::STATUS_BLOCKED)->count(),
            'completed' => $tasks->where('status', InterrogationBuildTask::STATUS_COMPLETED)->count(),
            'failed' => $tasks->where('status', InterrogationBuildTask::STATUS_FAILED)->count(),
            'skipped' => $tasks->where('status', InterrogationBuildTask::STATUS_SKIPPED)->count(),
        ];

        /** @var InterrogationBuildTask|null $activeTask */
        $activeTask = $tasks->firstWhere('status', InterrogationBuildTask::STATUS_IN_PROGRESS);
        if ($activeTask === null && isset($build['active_task_id'])) {
            $activeTask = $tasks->firstWhere('id', (int) $build['active_task_id']);
        }

        $activeRun = $activeTask?->run;
        if ($activeRun === null && isset($build['active_run_id'])) {
            $activeRun = AgentJobRun::query()->find((int) $build['active_run_id']);
        }

        $runMetadata = is_array($activeRun?->metadata_json) ? $activeRun->metadata_json : [];

        $clarificationExcerpt = $this->normalizeClarificationExcerpt(
            is_string($runMetadata['clarification_excerpt'] ?? null)
                ? $runMetadata['clarification_excerpt']
                : (is_string($build['clarification_excerpt'] ?? null) ? $build['clarification_excerpt'] : null)
        );

        $flags = [
            'approval_required' => (bool) (($runMetadata['approval_required'] ?? null) ?? ($build['approval_required'] ?? false)),
            'approval_excerpt' => is_string($runMetadata['approval_excerpt'] ?? null)
                ? $runMetadata['approval_excerpt']
                : null,
            'permission_required' => (bool) (($runMetadata['permission_blocker_detected'] ?? null) ?? ($build['permission_required'] ?? false)),
            'permission_excerpt' => is_string($runMetadata['permission_blocker_excerpt'] ?? null)
                ? $runMetadata['permission_blocker_excerpt']
                : (is_string($build['permission_excerpt'] ?? null) ? $build['permission_excerpt'] : null),
            'clarification_required' => (bool) (($runMetadata['clarification_required'] ?? null) ?? ($build['clarification_required'] ?? false)),
            'clarification_excerpt' => $clarificationExcerpt,
            'rate_limit_detected' => (bool) (($runMetadata['rate_limit_detected'] ?? null) ?? ($build['rate_limit_detected'] ?? false)),
            'rate_limit_reset_at' => (string) ($runMetadata['rate_limit_reset_at'] ?? $runMetadata['rate_limit_hold_until'] ?? $build['rate_limit_reset_at'] ?? ''),
            'rate_limit_excerpt' => is_string($runMetadata['rate_limit_excerpt'] ?? null)
                ? $runMetadata['rate_limit_excerpt']
                : (is_string($build['rate_limit_excerpt'] ?? null) ? $build['rate_limit_excerpt'] : null),
        ];

        $issues = is_array($runMetadata['issues'] ?? null) ? $runMetadata['issues'] : [];
        $normalizedIssues = $this->normalizeBuildIssues($issues);

        if ($flags['rate_limit_reset_at'] === '') {
            $flags['rate_limit_reset_at'] = null;
        }

        $activeRunPayload = null;
        if ($activeRun !== null) {
            $activeRunPayload = $this->transformBuildRun($activeRun);
            $activeRunPayload['effective_status'] = $this->effectiveBuildRunStatus($activeRun, $activeTask, $flags);
        }

        $artefacts = [];
        foreach ($tasks as $t) {
            $tMeta = is_array($t->metadata_json) ? $t->metadata_json : [];
            $taskArtefacts = is_array($tMeta['artefacts'] ?? null) ? $tMeta['artefacts'] : [];
            foreach ($taskArtefacts as $artefact) {
                if (is_array($artefact)) {
                    $artefacts[] = array_merge($artefact, [
                        'task_id' => $t->id,
                        'task_title' => $t->title,
                    ]);
                }
            }
        }

        return [
            'status' => $status,
            'summary' => $summary,
            'tasks' => $tasks->map(fn (InterrogationBuildTask $task): array => $this->transformBuildTask($task))->values(),
            'active_task' => $activeTask !== null ? $this->transformBuildTask($activeTask) : null,
            'active_run' => $activeRunPayload,
            'project_rules' => $this->normalizedProjectRules((array) ($build['project_rules'] ?? [])),
            'flags' => $flags,
            'issues' => $normalizedIssues,
            'artefacts' => $artefacts,
            'pause_reason' => isset($build['pause_reason']) ? (string) $build['pause_reason'] : null,
            'error' => isset($build['error']) ? (string) $build['error'] : null,
            'completion_summary' => isset($build['completion_summary']) ? (string) $build['completion_summary'] : null,
            'task_review_summary' => isset($build['task_review_summary']) ? (string) $build['task_review_summary'] : null,
            'task_review_task_id' => isset($build['task_review_task_id']) ? (int) $build['task_review_task_id'] : null,
            'started_at' => isset($build['started_at']) ? (string) $build['started_at'] : null,
            'paused_at' => isset($build['paused_at']) ? (string) $build['paused_at'] : null,
            'finished_at' => isset($build['finished_at']) ? (string) $build['finished_at'] : null,
            'tasks_approved_at' => isset($build['tasks_approved_at']) ? (string) $build['tasks_approved_at'] : null,
            'tasks_approved_by_user_id' => isset($build['tasks_approved_by_user_id']) ? (int) $build['tasks_approved_by_user_id'] : null,
            'task_provider_sync' => is_array($build['task_provider_sync'] ?? null) ? $build['task_provider_sync'] : null,
            'updated_at' => isset($build['updated_at']) ? (string) $build['updated_at'] : null,
            'active_task_id' => $activeTask?->id,
            'active_run_id' => $activeRun?->id,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function transformBuildTask(InterrogationBuildTask $task): array
    {
        return [
            'id' => $task->id,
            'sequence' => $task->sequence,
            'title' => $task->title,
            'description' => $task->description,
            'instructions_markdown' => $task->instructions_markdown,
            'status' => $task->status,
            'attempt_count' => $task->attempt_count,
            'agent_job_run_id' => $task->agent_job_run_id,
            'last_error' => $task->last_error,
            'metadata_json' => $task->metadata_json,
            'started_at' => $this->toRfc3339Millis($task->started_at),
            'finished_at' => $this->toRfc3339Millis($task->finished_at),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function transformBuildRun(AgentJobRun $run): array
    {
        return [
            'id' => $run->id,
            'status' => $run->status,
            'agent_job_id' => $run->agent_job_id,
            'trigger_type' => $run->trigger_type,
            'started_at' => $this->toRfc3339Millis($run->started_at),
            'finished_at' => $this->toRfc3339Millis($run->finished_at),
            'error_code' => $run->error_code,
            'error_summary' => $run->error_summary,
            'metadata_json' => $run->metadata_json,
            'log_tail' => $this->runLogTail($run),
        ];
    }

    /**
     * @param  array<string, mixed>  $flags
     */
    public function effectiveBuildRunStatus(AgentJobRun $run, ?InterrogationBuildTask $activeTask, array $flags): string
    {
        $runStatus = Str::lower(trim((string) $run->status));
        $taskStatus = Str::lower(trim((string) ($activeTask->status ?? '')));

        if ($taskStatus === InterrogationBuildTask::STATUS_FAILED) {
            return 'failed_task';
        }

        if ($taskStatus !== InterrogationBuildTask::STATUS_BLOCKED) {
            return $runStatus !== '' ? $runStatus : (string) $run->status;
        }

        if (($flags['clarification_required'] ?? false) === true) {
            return 'blocked_clarification';
        }

        if (($flags['permission_required'] ?? false) === true) {
            return 'blocked_permission';
        }

        if (($flags['rate_limit_detected'] ?? false) === true) {
            return 'blocked_rate_limit';
        }

        return 'blocked';
    }

    public function normalizeClarificationExcerpt(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $excerpt = trim($value);
        if ($excerpt === '') {
            return null;
        }

        $decoded = null;
        if (Str::startsWith($excerpt, '{') || Str::startsWith($excerpt, '[')) {
            $decoded = json_decode($excerpt, true);
        }

        if (is_array($decoded)) {
            $candidate = $this->extractClarificationText($decoded);
            if (is_string($candidate) && trim($candidate) !== '') {
                $excerpt = trim($candidate);
            }
        }

        if (preg_match('/\\\\n/', $excerpt) === 1 && ! Str::contains($excerpt, "\n")) {
            $excerpt = str_replace('\\n', "\n", $excerpt);
        }

        return trim(Str::limit($excerpt, 1000, ''));
    }

    public function extractClarificationText(mixed $payload): ?string
    {
        if (is_string($payload)) {
            $value = trim($payload);

            return $value === '' ? null : $value;
        }

        if (! is_array($payload)) {
            return null;
        }

        foreach (['text', 'message', 'excerpt', 'question', 'prompt', 'detail', 'content'] as $key) {
            if (is_string($payload[$key] ?? null) && trim((string) $payload[$key]) !== '') {
                return trim((string) $payload[$key]);
            }
        }

        foreach (['item', 'payload', 'data', 'result', 'response', 'error'] as $key) {
            $candidate = $this->extractClarificationText($payload[$key] ?? null);
            if ($candidate !== null) {
                return $candidate;
            }
        }

        foreach ($payload as $key => $child) {
            if (in_array((string) $key, ['id', 'type', 'status', 'event_type', 'role'], true)) {
                continue;
            }

            $candidate = $this->extractClarificationText($child);
            if ($candidate !== null && preg_match('/\s/', $candidate) === 1) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function runLogTail(AgentJobRun $run): array
    {
        return AgentRunEvent::query()
            ->where('agent_job_run_id', $run->id)
            ->orderByDesc('sequence')
            ->limit(60)
            ->get()
            ->sortBy('sequence')
            ->values()
            ->map(function (AgentRunEvent $event): array {
                return [
                    'id' => $event->id,
                    'sequence' => $event->sequence,
                    'event_type' => $event->event_type,
                    'payload' => $event->payload,
                    'created_at' => $this->toRfc3339Millis($event->created_at),
                ];
            })
            ->all();
    }

    /**
     * @param  array<int, mixed>  $rules
     * @return array<int, array<string, mixed>>
     */
    public function normalizedProjectRules(array $rules, bool $strict = false): array
    {
        $normalized = [];

        foreach ($rules as $index => $rule) {
            if (! is_array($rule)) {
                continue;
            }

            $title = trim((string) ($rule['title'] ?? $rule['name'] ?? ''));
            $markdown = trim((string) ($rule['markdown'] ?? $rule['content'] ?? ''));
            $source = strtolower(trim((string) ($rule['source'] ?? 'manual')));
            $filename = trim((string) ($rule['filename'] ?? ''));

            if ($title === '' && $markdown === '') {
                continue;
            }

            if ($markdown === '') {
                if ($strict) {
                    throw ValidationException::withMessages([
                        'project_rules' => sprintf('project_rules[%d].markdown is required when title is provided.', (int) $index),
                    ]);
                }

                continue;
            }

            if (mb_strlen($title) > 120) {
                if ($strict) {
                    throw ValidationException::withMessages([
                        'project_rules' => sprintf('project_rules[%d].title must be 120 characters or fewer.', (int) $index),
                    ]);
                }

                $title = mb_substr($title, 0, 120);
            }

            if (mb_strlen($markdown) > 120000) {
                if ($strict) {
                    throw ValidationException::withMessages([
                        'project_rules' => sprintf('project_rules[%d].markdown must be 120000 characters or fewer.', (int) $index),
                    ]);
                }

                $markdown = mb_substr($markdown, 0, 120000);
            }

            if (! in_array($source, ['manual', 'uploaded'], true)) {
                $source = 'manual';
            }

            $normalized[] = [
                'id' => trim((string) ($rule['id'] ?? '')) !== '' ? substr(trim((string) $rule['id']), 0, 80) : 'rule-'.Str::uuid()->toString(),
                'title' => $title !== '' ? $title : 'Rule '.(count($normalized) + 1),
                'markdown' => $markdown,
                'source' => $source,
                'filename' => $filename !== '' ? substr($filename, 0, 255) : null,
            ];
        }

        return $normalized;
    }

    /**
     * @param  array<int, mixed>  $uploads
     * @return array<int, array<string, mixed>>
     */
    public function normalizedProjectRulesFromUploads(array $uploads): array
    {
        $normalized = [];

        foreach ($uploads as $index => $upload) {
            if (! $upload instanceof UploadedFile) {
                continue;
            }

            $rawContent = @file_get_contents($upload->getRealPath());
            if (! is_string($rawContent)) {
                throw ValidationException::withMessages([
                    'project_rule_files' => sprintf('Could not read uploaded project rule file at index %d.', (int) $index),
                ]);
            }

            if (! mb_check_encoding($rawContent, 'UTF-8')) {
                throw ValidationException::withMessages([
                    'project_rule_files' => sprintf('Uploaded project rule file "%s" must be UTF-8 encoded.', $upload->getClientOriginalName()),
                ]);
            }

            $markdown = trim($rawContent);
            if ($markdown === '') {
                throw ValidationException::withMessages([
                    'project_rule_files' => sprintf('Uploaded project rule file "%s" is empty.', $upload->getClientOriginalName()),
                ]);
            }

            if (mb_strlen($markdown) > 120000) {
                throw ValidationException::withMessages([
                    'project_rule_files' => sprintf('Uploaded project rule file "%s" exceeds 120000 characters.', $upload->getClientOriginalName()),
                ]);
            }

            $originalName = trim((string) $upload->getClientOriginalName());
            $title = pathinfo($originalName !== '' ? $originalName : 'Uploaded Rule '.($index + 1), PATHINFO_FILENAME);
            $title = trim((string) $title);

            $normalized[] = [
                'id' => 'rule-'.Str::uuid()->toString(),
                'title' => $title !== '' ? substr($title, 0, 120) : 'Uploaded Rule '.(count($normalized) + 1),
                'markdown' => $markdown,
                'source' => 'uploaded',
                'filename' => $originalName !== '' ? substr($originalName, 0, 255) : null,
            ];
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $issues
     * @return array<int, array<string, mixed>>
     */
    public function normalizeBuildIssues(array $issues): array
    {
        $normalized = [];

        foreach ($issues as $key => $issue) {
            if (! is_array($issue)) {
                continue;
            }

            $code = trim((string) ($issue['code'] ?? strtoupper((string) $key)));
            $title = trim((string) ($issue['title'] ?? 'Issue detected'));
            $detail = trim((string) ($issue['detail'] ?? ''));
            $suggestedAction = trim((string) ($issue['suggested_action'] ?? ''));

            if ($title === '' || $detail === '') {
                continue;
            }

            $normalized[] = [
                'key' => (string) $key,
                'code' => $code,
                'title' => $title,
                'detail' => $detail,
                'suggested_action' => $suggestedAction !== '' ? $suggestedAction : null,
                'endpoint' => isset($issue['endpoint']) ? (string) $issue['endpoint'] : null,
                'count' => max(1, (int) ($issue['count'] ?? 1)),
                'first_detected_at' => isset($issue['first_detected_at']) ? (string) $issue['first_detected_at'] : null,
                'last_detected_at' => isset($issue['last_detected_at']) ? (string) $issue['last_detected_at'] : null,
                'excerpt' => isset($issue['excerpt']) ? (string) $issue['excerpt'] : null,
            ];
        }

        return $normalized;
    }

    public function normalizedNullableText(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }

    public function toRfc3339Millis(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        try {
            return CarbonImmutable::parse($value, 'UTC')->format('Y-m-d\TH:i:s.v\Z');
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return array{mode:string,project_id:?string,project_name:?string,project_url:?string}
     */
    public function providerProjectSyncPreference(ConnectedProvider $provider): array
    {
        $metadata = is_array($provider->metadata_json) ? $provider->metadata_json : [];
        $projectSync = is_array($metadata['project_sync'] ?? null) ? $metadata['project_sync'] : [];
        $mode = in_array(($projectSync['mode'] ?? null), ['create_new', 'existing'], true)
            ? (string) $projectSync['mode']
            : 'create_new';

        if ($mode !== 'existing') {
            return [
                'mode' => 'create_new',
                'project_id' => null,
                'project_name' => null,
                'project_url' => null,
            ];
        }

        $projectId = trim((string) ($projectSync['selected_project_id'] ?? ''));
        if ($projectId === '') {
            return [
                'mode' => 'create_new',
                'project_id' => null,
                'project_name' => null,
                'project_url' => null,
            ];
        }

        return [
            'mode' => 'existing',
            'project_id' => $projectId,
            'project_name' => is_string($projectSync['selected_project_name'] ?? null)
                ? trim((string) $projectSync['selected_project_name'])
                : null,
            'project_url' => is_string($projectSync['selected_project_url'] ?? null)
                ? trim((string) $projectSync['selected_project_url'])
                : null,
        ];
    }
}
