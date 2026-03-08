<?php

namespace Tests\Unit;

use App\Jobs\ExecuteInterrogationBuildJob;
use App\Jobs\SyncInterrogationTaskStatusToTaskProviderJob;
use App\Models\AgentJob;
use App\Models\AgentJobRun;
use App\Models\AgentRunEvent;
use App\Models\InterrogationBuildTask;
use App\Models\InterrogationEvent;
use App\Models\InterrogationSession;
use App\Models\User;
use App\Support\Interrogation\BuildExecutionBackupService;
use App\Support\Interrogation\BuildTaskRunFactory;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ExecuteInterrogationBuildJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_starts_next_pending_task_and_sets_active_run_pointers(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $session = $this->makeSession($user, [
            'status' => 'running',
        ]);

        $task = InterrogationBuildTask::query()->create([
            'interrogation_session_id' => $session->id,
            'sequence' => 1,
            'title' => 'Run task',
            'status' => InterrogationBuildTask::STATUS_PENDING,
            'attempt_count' => 0,
        ]);

        $run = $this->makeRun($user, AgentJobRun::STATUS_QUEUED);

        $factory = $this->mock(BuildTaskRunFactory::class);
        $factory->shouldReceive('create')
            ->once()
            ->andReturn($run);

        $job = new ExecuteInterrogationBuildJob((int) $session->id);
        $this->app->call([$job, 'handle']);

        $task->refresh();
        $session->refresh();

        $this->assertSame(InterrogationBuildTask::STATUS_IN_PROGRESS, (string) $task->status);
        $this->assertSame((int) $run->id, (int) $task->agent_job_run_id);
        $this->assertSame(1, (int) $task->attempt_count);
        $this->assertSame('running', data_get($session->metadata_json, 'build.status'));
        $this->assertSame($task->id, data_get($session->metadata_json, 'build.active_task_id'));
        $this->assertSame($run->id, data_get($session->metadata_json, 'build.active_run_id'));
        $this->assertSame('succeeded', data_get($session->metadata_json, 'build.execution_backup.build_start.status'));
        $this->assertCount(1, (array) data_get($session->metadata_json, 'build.execution_backup.task_starts', []));

        Queue::assertPushed(ExecuteInterrogationBuildJob::class);
        Queue::assertPushed(SyncInterrogationTaskStatusToTaskProviderJob::class, function (SyncInterrogationTaskStatusToTaskProviderJob $job) use ($session, $task): bool {
            return (int) $job->sessionId === (int) $session->id
                && (int) $job->taskId === (int) $task->id;
        });
    }

    public function test_job_marks_build_completed_after_terminal_successful_task(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $session = $this->makeSession($user, [
            'status' => 'running',
        ]);

        $run = $this->makeRun($user, AgentJobRun::STATUS_SUCCEEDED);

        $task = InterrogationBuildTask::query()->create([
            'interrogation_session_id' => $session->id,
            'sequence' => 1,
            'title' => 'Complete task',
            'status' => InterrogationBuildTask::STATUS_IN_PROGRESS,
            'attempt_count' => 1,
            'agent_job_run_id' => $run->id,
        ]);

        $factory = $this->mock(BuildTaskRunFactory::class);
        $factory->shouldReceive('create')->never();

        $job = new ExecuteInterrogationBuildJob((int) $session->id);
        $this->app->call([$job, 'handle']);

        $task->refresh();
        $this->assertSame(InterrogationBuildTask::STATUS_COMPLETED, (string) $task->status);
        Queue::assertPushed(SyncInterrogationTaskStatusToTaskProviderJob::class, function (SyncInterrogationTaskStatusToTaskProviderJob $job) use ($session, $task): bool {
            return (int) $job->sessionId === (int) $session->id
                && (int) $job->taskId === (int) $task->id;
        });

        $this->app->call([$job, 'handle']);

        $session->refresh();

        $this->assertSame('completed', data_get($session->metadata_json, 'build.status'));
    }

    public function test_codex_success_without_actionable_execution_evidence_is_marked_failed(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $session = $this->makeSession($user, [
            'status' => 'running',
        ], 'codex');

        $run = $this->makeRun($user, AgentJobRun::STATUS_SUCCEEDED, [
            'metadata_json' => [
                'source' => 'interrogation_build',
            ],
        ]);

        AgentRunEvent::query()->create([
            'agent_job_run_id' => $run->id,
            'event_type' => 'stdout',
            'sequence' => 1,
            'payload' => '{"type":"item.started","item":{"type":"command_execution","command":"bash -lc \'ls -la\'"}}',
            'event_ts' => now('UTC'),
        ]);

        AgentRunEvent::query()->create([
            'agent_job_run_id' => $run->id,
            'event_type' => 'stdout',
            'sequence' => 2,
            'payload' => '{"type":"item.completed","item":{"type":"agent_message","text":"I reviewed the requirements. Next I will write a plan before coding."}}',
            'event_ts' => now('UTC'),
        ]);

        $task = InterrogationBuildTask::query()->create([
            'interrogation_session_id' => $session->id,
            'sequence' => 1,
            'title' => 'No-op codex task',
            'status' => InterrogationBuildTask::STATUS_IN_PROGRESS,
            'attempt_count' => 1,
            'agent_job_run_id' => $run->id,
        ]);

        $factory = $this->mock(BuildTaskRunFactory::class);
        $factory->shouldReceive('create')->never();

        $job = new ExecuteInterrogationBuildJob((int) $session->id);
        $this->app->call([$job, 'handle']);

        $task->refresh();
        $session->refresh();

        $this->assertSame(InterrogationBuildTask::STATUS_FAILED, (string) $task->status);
        $this->assertSame(
            'Runner exited successfully but did not execute concrete implementation or verification commands.',
            (string) $task->last_error
        );
        $this->assertSame('failed', data_get($session->metadata_json, 'build.status'));
        $this->assertSame(1, (int) data_get($session->metadata_json, 'build.execution_evidence.command_count'));
        $this->assertSame(0, (int) data_get($session->metadata_json, 'build.execution_evidence.mutation_command_count'));
        $this->assertSame(0, (int) data_get($session->metadata_json, 'build.execution_evidence.implementation_mutation_command_count'));
        $this->assertSame(0, (int) data_get($session->metadata_json, 'build.execution_evidence.verification_command_count'));
        $this->assertFalse((bool) data_get($session->metadata_json, 'build.execution_evidence.has_actionable_execution'));

        $errorNotice = InterrogationEvent::query()
            ->where('interrogation_session_id', (int) $session->id)
            ->where('event_type', InterrogationEvent::TYPE_ERROR)
            ->where('payload->code', 'BUILD_TASK_NO_EXECUTION_EVIDENCE')
            ->exists();

        $this->assertTrue($errorNotice);
    }

    public function test_codex_success_with_only_meta_file_mutation_and_no_verification_is_marked_failed(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $session = $this->makeSession($user, [
            'status' => 'running',
        ], 'codex');

        $run = $this->makeRun($user, AgentJobRun::STATUS_SUCCEEDED, [
            'metadata_json' => [
                'source' => 'interrogation_build',
            ],
        ]);

        AgentRunEvent::query()->create([
            'agent_job_run_id' => $run->id,
            'event_type' => 'stdout',
            'sequence' => 1,
            'payload' => '{"type":"item.started","item":{"type":"command_execution","command":"cat > tasks/todo.md"}}',
            'event_ts' => now('UTC'),
        ]);

        $task = InterrogationBuildTask::query()->create([
            'interrogation_session_id' => $session->id,
            'sequence' => 1,
            'title' => 'Checklist-only codex task',
            'status' => InterrogationBuildTask::STATUS_IN_PROGRESS,
            'attempt_count' => 1,
            'agent_job_run_id' => $run->id,
        ]);

        $factory = $this->mock(BuildTaskRunFactory::class);
        $factory->shouldReceive('create')->never();

        $job = new ExecuteInterrogationBuildJob((int) $session->id);
        $this->app->call([$job, 'handle']);

        $task->refresh();
        $session->refresh();

        $this->assertSame(InterrogationBuildTask::STATUS_FAILED, (string) $task->status);
        $this->assertSame(1, (int) data_get($session->metadata_json, 'build.execution_evidence.command_count'));
        $this->assertSame(1, (int) data_get($session->metadata_json, 'build.execution_evidence.mutation_command_count'));
        $this->assertSame(0, (int) data_get($session->metadata_json, 'build.execution_evidence.implementation_mutation_command_count'));
        $this->assertSame(0, (int) data_get($session->metadata_json, 'build.execution_evidence.verification_command_count'));
        $this->assertFalse((bool) data_get($session->metadata_json, 'build.execution_evidence.has_actionable_execution'));
    }

    public function test_codex_success_with_implementation_and_verification_evidence_completes_task(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $session = $this->makeSession($user, [
            'status' => 'running',
        ], 'codex');

        $run = $this->makeRun($user, AgentJobRun::STATUS_SUCCEEDED, [
            'metadata_json' => [
                'source' => 'interrogation_build',
            ],
        ]);

        AgentRunEvent::query()->create([
            'agent_job_run_id' => $run->id,
            'event_type' => 'stdout',
            'sequence' => 1,
            'payload' => '{"type":"item.started","item":{"type":"command_execution","command":"apply_patch <<\\\"PATCH\\\"\\n*** Update File: app/Models/DocumentationEntry.php\\nPATCH"}}',
            'event_ts' => now('UTC'),
        ]);

        AgentRunEvent::query()->create([
            'agent_job_run_id' => $run->id,
            'event_type' => 'stdout',
            'sequence' => 2,
            'payload' => '{"type":"item.started","item":{"type":"command_execution","command":"bash -lc \"php artisan test --filter=DocumentationSchemaTest\""}}',
            'event_ts' => now('UTC'),
        ]);

        $task = InterrogationBuildTask::query()->create([
            'interrogation_session_id' => $session->id,
            'sequence' => 1,
            'title' => 'Implementation codex task',
            'status' => InterrogationBuildTask::STATUS_IN_PROGRESS,
            'attempt_count' => 1,
            'agent_job_run_id' => $run->id,
        ]);

        $factory = $this->mock(BuildTaskRunFactory::class);
        $factory->shouldReceive('create')->never();

        $job = new ExecuteInterrogationBuildJob((int) $session->id);
        $this->app->call([$job, 'handle']);

        $task->refresh();

        $this->assertSame(InterrogationBuildTask::STATUS_COMPLETED, (string) $task->status);
    }

    public function test_codex_success_with_file_change_events_and_verification_evidence_completes_task(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $session = $this->makeSession($user, [
            'status' => 'running',
        ], 'codex');

        $run = $this->makeRun($user, AgentJobRun::STATUS_SUCCEEDED, [
            'metadata_json' => [
                'source' => 'interrogation_build',
            ],
        ]);

        AgentRunEvent::query()->create([
            'agent_job_run_id' => $run->id,
            'event_type' => 'stdout',
            'sequence' => 1,
            'payload' => '{"type":"item.completed","item":{"id":"item_71","type":"file_change","changes":[{"path":"/Users/garethdaine/Code/agent/app/Support/Documentation/Ingestion/DocsContractValidator.php","kind":"add"}],"status":"completed"}}',
            'event_ts' => now('UTC'),
        ]);

        AgentRunEvent::query()->create([
            'agent_job_run_id' => $run->id,
            'event_type' => 'stdout',
            'sequence' => 2,
            'payload' => '{"type":"item.started","item":{"id":"item_111","type":"command_execution","command":"/bin/zsh -lc \'php artisan test --filter=Documentation\'","aggregated_output":"","exit_code":null,"status":"in_progress"}}',
            'event_ts' => now('UTC'),
        ]);

        $task = InterrogationBuildTask::query()->create([
            'interrogation_session_id' => $session->id,
            'sequence' => 1,
            'title' => 'File-change codex task',
            'status' => InterrogationBuildTask::STATUS_IN_PROGRESS,
            'attempt_count' => 1,
            'agent_job_run_id' => $run->id,
        ]);

        $factory = $this->mock(BuildTaskRunFactory::class);
        $factory->shouldReceive('create')->never();

        $job = new ExecuteInterrogationBuildJob((int) $session->id);
        $this->app->call([$job, 'handle']);

        $task->refresh();

        $this->assertSame(InterrogationBuildTask::STATUS_COMPLETED, (string) $task->status);
    }

    public function test_codex_success_with_verification_only_and_already_implemented_signal_completes_task(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $session = $this->makeSession($user, [
            'status' => 'running',
        ], 'codex');

        $run = $this->makeRun($user, AgentJobRun::STATUS_SUCCEEDED, [
            'metadata_json' => [
                'source' => 'interrogation_build',
            ],
        ]);

        AgentRunEvent::query()->create([
            'agent_job_run_id' => $run->id,
            'event_type' => 'stdout',
            'sequence' => 1,
            'payload' => '{"type":"item.completed","item":{"id":"item_1","type":"agent_message","text":"The requested docs contract is already implemented in this workspace; I will verify it via targeted tests and command checks."}}',
            'event_ts' => now('UTC'),
        ]);

        AgentRunEvent::query()->create([
            'agent_job_run_id' => $run->id,
            'event_type' => 'stdout',
            'sequence' => 2,
            'payload' => '{"type":"item.completed","item":{"id":"item_2","type":"command_execution","command":"/bin/zsh -lc \'php artisan test --filter=DocsContractValidationTest\'","aggregated_output":"ok","exit_code":0,"status":"completed"}}',
            'event_ts' => now('UTC'),
        ]);

        $task = InterrogationBuildTask::query()->create([
            'interrogation_session_id' => $session->id,
            'sequence' => 1,
            'title' => 'Verification-only revalidation task',
            'status' => InterrogationBuildTask::STATUS_IN_PROGRESS,
            'attempt_count' => 2,
            'agent_job_run_id' => $run->id,
        ]);

        $factory = $this->mock(BuildTaskRunFactory::class);
        $factory->shouldReceive('create')->never();

        $job = new ExecuteInterrogationBuildJob((int) $session->id);
        $this->app->call([$job, 'handle']);

        $task->refresh();
        $session->refresh();

        $this->assertSame(InterrogationBuildTask::STATUS_COMPLETED, (string) $task->status);
        $this->assertTrue((bool) data_get($session->metadata_json, 'build.execution_evidence.has_revalidation_execution'));
    }

    public function test_codex_success_with_verification_only_without_already_implemented_signal_still_fails(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $session = $this->makeSession($user, [
            'status' => 'running',
        ], 'codex');

        $run = $this->makeRun($user, AgentJobRun::STATUS_SUCCEEDED, [
            'metadata_json' => [
                'source' => 'interrogation_build',
            ],
        ]);

        AgentRunEvent::query()->create([
            'agent_job_run_id' => $run->id,
            'event_type' => 'stdout',
            'sequence' => 1,
            'payload' => '{"type":"item.completed","item":{"id":"item_1","type":"agent_message","text":"I verified some checks and am ready to proceed."}}',
            'event_ts' => now('UTC'),
        ]);

        AgentRunEvent::query()->create([
            'agent_job_run_id' => $run->id,
            'event_type' => 'stdout',
            'sequence' => 2,
            'payload' => '{"type":"item.completed","item":{"id":"item_2","type":"command_execution","command":"/bin/zsh -lc \'php artisan test --filter=DocsContractValidationTest\'","aggregated_output":"ok","exit_code":0,"status":"completed"}}',
            'event_ts' => now('UTC'),
        ]);

        $task = InterrogationBuildTask::query()->create([
            'interrogation_session_id' => $session->id,
            'sequence' => 1,
            'title' => 'Verification-only without signal task',
            'status' => InterrogationBuildTask::STATUS_IN_PROGRESS,
            'attempt_count' => 2,
            'agent_job_run_id' => $run->id,
        ]);

        $factory = $this->mock(BuildTaskRunFactory::class);
        $factory->shouldReceive('create')->never();

        $job = new ExecuteInterrogationBuildJob((int) $session->id);
        $this->app->call([$job, 'handle']);

        $task->refresh();
        $session->refresh();

        $this->assertSame(InterrogationBuildTask::STATUS_FAILED, (string) $task->status);
        $this->assertFalse((bool) data_get($session->metadata_json, 'build.execution_evidence.has_revalidation_execution'));
    }

    public function test_codex_success_with_verification_only_and_already_green_signal_completes_task(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $session = $this->makeSession($user, [
            'status' => 'running',
        ], 'codex');

        $run = $this->makeRun($user, AgentJobRun::STATUS_SUCCEEDED, [
            'metadata_json' => [
                'source' => 'interrogation_build',
            ],
        ]);

        AgentRunEvent::query()->create([
            'agent_job_run_id' => $run->id,
            'event_type' => 'stdout',
            'sequence' => 1,
            'payload' => '{"type":"item.completed","item":{"id":"item_1","type":"agent_message","text":"Both target test suites are already green in this workspace; I will run verification again to confirm determinism."}}',
            'event_ts' => now('UTC'),
        ]);

        AgentRunEvent::query()->create([
            'agent_job_run_id' => $run->id,
            'event_type' => 'stdout',
            'sequence' => 2,
            'payload' => '{"type":"item.completed","item":{"id":"item_2","type":"command_execution","command":"/bin/zsh -lc \'php artisan test --filter=TaskGraphBuilderTest\'","aggregated_output":"ok","exit_code":0,"status":"completed"}}',
            'event_ts' => now('UTC'),
        ]);

        $task = InterrogationBuildTask::query()->create([
            'interrogation_session_id' => $session->id,
            'sequence' => 1,
            'title' => 'Verification-only with already-green signal task',
            'status' => InterrogationBuildTask::STATUS_IN_PROGRESS,
            'attempt_count' => 2,
            'agent_job_run_id' => $run->id,
        ]);

        $factory = $this->mock(BuildTaskRunFactory::class);
        $factory->shouldReceive('create')->never();

        $job = new ExecuteInterrogationBuildJob((int) $session->id);
        $this->app->call([$job, 'handle']);

        $task->refresh();
        $session->refresh();

        $this->assertSame(InterrogationBuildTask::STATUS_COMPLETED, (string) $task->status);
        $this->assertTrue((bool) data_get($session->metadata_json, 'build.execution_evidence.already_implemented_signal'));
        $this->assertTrue((bool) data_get($session->metadata_json, 'build.execution_evidence.has_revalidation_execution'));
    }

    public function test_job_preserves_failed_permission_blocker_context_for_build_retry_and_visibility(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $session = $this->makeSession($user, [
            'status' => 'running',
        ]);

        $run = $this->makeRun($user, AgentJobRun::STATUS_FAILED, [
            'error_code' => 'PERMISSION_REQUIRED',
            'error_summary' => 'Write permissions were denied for this task.',
            'metadata_json' => [
                'permission_blocker_detected' => true,
                'permission_blocker_excerpt' => 'All file write operations are denied by the permission system.',
            ],
        ]);

        $task = InterrogationBuildTask::query()->create([
            'interrogation_session_id' => $session->id,
            'sequence' => 2,
            'title' => 'Blocked task',
            'status' => InterrogationBuildTask::STATUS_IN_PROGRESS,
            'attempt_count' => 1,
            'agent_job_run_id' => $run->id,
        ]);

        $factory = $this->mock(BuildTaskRunFactory::class);
        $factory->shouldReceive('create')->never();

        $job = new ExecuteInterrogationBuildJob((int) $session->id);
        $this->app->call([$job, 'handle']);

        $task->refresh();
        $session->refresh();

        $this->assertSame(InterrogationBuildTask::STATUS_FAILED, (string) $task->status);
        $this->assertSame('Write permissions were denied for this task.', (string) $task->last_error);
        $this->assertSame('failed', data_get($session->metadata_json, 'build.status'));
        $this->assertTrue((bool) data_get($session->metadata_json, 'build.permission_required'));
        $this->assertSame(
            'All file write operations are denied by the permission system.',
            data_get($session->metadata_json, 'build.permission_excerpt')
        );
        $this->assertSame($task->id, data_get($session->metadata_json, 'build.active_task_id'));
        $this->assertSame($run->id, data_get($session->metadata_json, 'build.active_run_id'));
        Queue::assertPushed(SyncInterrogationTaskStatusToTaskProviderJob::class, function (SyncInterrogationTaskStatusToTaskProviderJob $job) use ($session, $task): bool {
            return (int) $job->sessionId === (int) $session->id
                && (int) $job->taskId === (int) $task->id;
        });
    }

    public function test_job_does_not_pause_on_clarification_metadata_when_run_succeeded(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $session = $this->makeSession($user, [
            'status' => 'running',
        ]);

        $run = $this->makeRun($user, AgentJobRun::STATUS_SUCCEEDED, [
            'metadata_json' => [
                'clarification_required' => true,
                'clarification_excerpt' => 'Could you clarify whether we should use existing event names?',
            ],
        ]);

        $task = InterrogationBuildTask::query()->create([
            'interrogation_session_id' => $session->id,
            'sequence' => 3,
            'title' => 'Clarify task',
            'status' => InterrogationBuildTask::STATUS_IN_PROGRESS,
            'attempt_count' => 1,
            'agent_job_run_id' => $run->id,
        ]);

        $factory = $this->mock(BuildTaskRunFactory::class);
        $factory->shouldReceive('create')->never();

        $job = new ExecuteInterrogationBuildJob((int) $session->id);
        $this->app->call([$job, 'handle']);

        $task->refresh();
        $session->refresh();

        $this->assertSame(InterrogationBuildTask::STATUS_COMPLETED, (string) $task->status);
        $this->assertNotSame('paused', data_get($session->metadata_json, 'build.status'));
        $this->assertSame(null, data_get($session->metadata_json, 'build.pause_reason'));
        Queue::assertPushed(SyncInterrogationTaskStatusToTaskProviderJob::class, function (SyncInterrogationTaskStatusToTaskProviderJob $job) use ($session, $task): bool {
            return (int) $job->sessionId === (int) $session->id
                && (int) $job->taskId === (int) $task->id;
        });
    }

    public function test_job_pauses_build_when_failed_run_requests_clarification(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $session = $this->makeSession($user, [
            'status' => 'running',
        ]);

        $run = $this->makeRun($user, AgentJobRun::STATUS_FAILED, [
            'metadata_json' => [
                'clarification_required' => true,
                'clarification_excerpt' => 'Could you clarify whether we should use existing event names?',
            ],
        ]);

        $task = InterrogationBuildTask::query()->create([
            'interrogation_session_id' => $session->id,
            'sequence' => 3,
            'title' => 'Clarify task',
            'status' => InterrogationBuildTask::STATUS_IN_PROGRESS,
            'attempt_count' => 1,
            'agent_job_run_id' => $run->id,
        ]);

        $factory = $this->mock(BuildTaskRunFactory::class);
        $factory->shouldReceive('create')->never();

        $job = new ExecuteInterrogationBuildJob((int) $session->id);
        $this->app->call([$job, 'handle']);

        $task->refresh();
        $session->refresh();

        $this->assertSame(InterrogationBuildTask::STATUS_BLOCKED, (string) $task->status);
        $this->assertSame('paused', data_get($session->metadata_json, 'build.status'));
        $this->assertSame('clarification', data_get($session->metadata_json, 'build.pause_reason'));
        $this->assertTrue((bool) data_get($session->metadata_json, 'build.clarification_required'));
        $this->assertSame(
            'Could you clarify whether we should use existing event names?',
            data_get($session->metadata_json, 'build.clarification_excerpt')
        );
        $this->assertSame($task->id, data_get($session->metadata_json, 'build.active_task_id'));
        $this->assertSame($run->id, data_get($session->metadata_json, 'build.active_run_id'));
        Queue::assertPushed(SyncInterrogationTaskStatusToTaskProviderJob::class, function (SyncInterrogationTaskStatusToTaskProviderJob $job) use ($session, $task): bool {
            return (int) $job->sessionId === (int) $session->id
                && (int) $job->taskId === (int) $task->id;
        });
    }

    public function test_job_does_not_pause_on_rate_limit_metadata_when_run_succeeded(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $session = $this->makeSession($user, [
            'status' => 'running',
        ]);

        $run = $this->makeRun($user, AgentJobRun::STATUS_SUCCEEDED, [
            'metadata_json' => [
                'rate_limit_detected' => true,
                'rate_limit_excerpt' => 'Retrying after 429 response.',
            ],
        ]);

        $task = InterrogationBuildTask::query()->create([
            'interrogation_session_id' => $session->id,
            'sequence' => 4,
            'title' => 'Rate limit recovered task',
            'status' => InterrogationBuildTask::STATUS_IN_PROGRESS,
            'attempt_count' => 1,
            'agent_job_run_id' => $run->id,
        ]);

        $factory = $this->mock(BuildTaskRunFactory::class);
        $factory->shouldReceive('create')->never();

        $job = new ExecuteInterrogationBuildJob((int) $session->id);
        $this->app->call([$job, 'handle']);

        $task->refresh();
        $session->refresh();

        $this->assertSame(InterrogationBuildTask::STATUS_COMPLETED, (string) $task->status);
        $this->assertNotSame('paused', data_get($session->metadata_json, 'build.status'));
        $this->assertSame(null, data_get($session->metadata_json, 'build.pause_reason'));
        Queue::assertPushed(SyncInterrogationTaskStatusToTaskProviderJob::class, function (SyncInterrogationTaskStatusToTaskProviderJob $job) use ($session, $task): bool {
            return (int) $job->sessionId === (int) $session->id
                && (int) $job->taskId === (int) $task->id;
        });
    }

    public function test_job_pauses_when_pre_build_backup_fails_before_starting_task(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $session = $this->makeSession($user, [
            'status' => 'running',
        ]);

        $task = InterrogationBuildTask::query()->create([
            'interrogation_session_id' => $session->id,
            'sequence' => 1,
            'title' => 'Task waiting for backup',
            'status' => InterrogationBuildTask::STATUS_PENDING,
            'attempt_count' => 0,
        ]);

        $factory = $this->mock(BuildTaskRunFactory::class);
        $factory->shouldReceive('create')->never();

        $backupService = $this->mock(BuildExecutionBackupService::class);
        $backupService->shouldReceive('backupBeforeBuildStart')
            ->once()
            ->andReturn([
                'ok' => false,
                'attempted_at' => now('UTC')->toIso8601String(),
                'message' => 'Backup subsystem unavailable.',
                'output' => null,
                'skipped' => false,
            ]);
        $backupService->shouldReceive('backupBeforeTaskStart')->never();

        $job = new ExecuteInterrogationBuildJob((int) $session->id);
        $this->app->call([$job, 'handle']);

        $task->refresh();
        $session->refresh();

        $this->assertSame(InterrogationBuildTask::STATUS_PENDING, (string) $task->status);
        $this->assertNull($task->agent_job_run_id);
        $this->assertSame('paused', data_get($session->metadata_json, 'build.status'));
        $this->assertSame('backup_failed', data_get($session->metadata_json, 'build.pause_reason'));
        $this->assertStringContainsString(
            'Database backup failed before build execution started',
            (string) data_get($session->metadata_json, 'build.error')
        );

        Queue::assertNotPushed(SyncInterrogationTaskStatusToTaskProviderJob::class);
    }

    public function test_job_still_finalizes_task_when_task_provider_sync_dispatch_throws(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $session = $this->makeSession($user, [
            'status' => 'running',
        ]);

        $run = $this->makeRun($user, AgentJobRun::STATUS_FAILED, [
            'error_code' => 'PROCESS_NOT_FOUND',
            'error_summary' => 'Run PID no longer exists during reconciliation.',
            'metadata_json' => [
                'reconcile_reason' => 'process_not_found',
            ],
        ]);

        $task = InterrogationBuildTask::query()->create([
            'interrogation_session_id' => $session->id,
            'sequence' => 8,
            'title' => 'Unblock failed reconciliation',
            'status' => InterrogationBuildTask::STATUS_IN_PROGRESS,
            'attempt_count' => 1,
            'agent_job_run_id' => $run->id,
        ]);

        $factory = $this->mock(BuildTaskRunFactory::class);
        $factory->shouldReceive('create')->never();

        $dispatcher = \Mockery::mock(Dispatcher::class);
        $dispatcher->shouldReceive('dispatch')->andThrow(new \RuntimeException('queue unavailable'));
        $dispatcher->shouldReceive('dispatchSync')->andThrow(new \RuntimeException('queue unavailable'));
        $dispatcher->shouldReceive('dispatchNow')->andThrow(new \RuntimeException('queue unavailable'));
        $this->app->instance(Dispatcher::class, $dispatcher);

        $job = new ExecuteInterrogationBuildJob((int) $session->id);
        $this->app->call([$job, 'handle']);

        $task->refresh();
        $session->refresh();

        $this->assertSame(InterrogationBuildTask::STATUS_FAILED, (string) $task->status);
        $this->assertSame('Run PID no longer exists during reconciliation.', (string) $task->last_error);
        $this->assertSame('failed', data_get($session->metadata_json, 'build.status'));
        $this->assertSame($task->id, data_get($session->metadata_json, 'build.active_task_id'));
        $this->assertSame($run->id, data_get($session->metadata_json, 'build.active_run_id'));

        $queueFailureNotice = InterrogationEvent::query()
            ->where('interrogation_session_id', (int) $session->id)
            ->where('event_type', InterrogationEvent::TYPE_SYSTEM)
            ->where('payload->notice', 'build_task_provider_sync_queue_failed')
            ->exists();

        $this->assertTrue($queueFailureNotice);
    }

    public function test_job_does_not_start_pending_tasks_when_a_previous_task_is_already_failed(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $session = $this->makeSession($user, [
            'status' => 'running',
            'active_task_id' => null,
            'active_run_id' => null,
        ]);

        $failedRun = $this->makeRun($user, AgentJobRun::STATUS_FAILED, [
            'error_code' => 'PROCESS_NOT_FOUND',
            'error_summary' => 'Run PID no longer exists during reconciliation.',
            'metadata_json' => [
                'reconcile_reason' => 'process_not_found',
            ],
        ]);

        InterrogationBuildTask::query()->create([
            'interrogation_session_id' => $session->id,
            'sequence' => 8,
            'title' => 'Failed task',
            'status' => InterrogationBuildTask::STATUS_FAILED,
            'attempt_count' => 1,
            'agent_job_run_id' => $failedRun->id,
            'last_error' => 'Run PID no longer exists during reconciliation.',
            'finished_at' => now('UTC')->subMinute(),
        ]);

        $pendingTask = InterrogationBuildTask::query()->create([
            'interrogation_session_id' => $session->id,
            'sequence' => 9,
            'title' => 'Should not start',
            'status' => InterrogationBuildTask::STATUS_PENDING,
            'attempt_count' => 0,
        ]);

        $factory = $this->mock(BuildTaskRunFactory::class);
        $factory->shouldReceive('create')->never();

        $job = new ExecuteInterrogationBuildJob((int) $session->id);
        $this->app->call([$job, 'handle']);

        $pendingTask->refresh();
        $session->refresh();

        $this->assertSame(InterrogationBuildTask::STATUS_PENDING, (string) $pendingTask->status);
        $this->assertNull($pendingTask->agent_job_run_id);
        $this->assertSame('failed', data_get($session->metadata_json, 'build.status'));
        $this->assertSame(InterrogationSession::STATUS_FAILED, (string) $session->status);
        $this->assertSame('BUILD_EXECUTION_FAILED', (string) $session->error_code);
    }

    public function test_failed_handler_marks_running_build_as_failed_and_surfaces_error(): void
    {
        $user = User::factory()->create();
        $session = $this->makeSession($user, [
            'status' => 'running',
            'active_task_id' => null,
            'active_run_id' => null,
        ]);

        $job = new ExecuteInterrogationBuildJob((int) $session->id);
        $job->failed(new \RuntimeException('Queue worker crashed while reconciling build tick.'));

        $session->refresh();

        $this->assertSame('failed', data_get($session->metadata_json, 'build.status'));
        $this->assertSame(
            'Queue worker crashed while reconciling build tick.',
            data_get($session->metadata_json, 'build.error')
        );
        $this->assertNotNull(data_get($session->metadata_json, 'build.failed_at'));
        $this->assertSame(InterrogationSession::STATUS_FAILED, (string) $session->status);
        $this->assertSame('BUILD_EXECUTION_JOB_FAILED', (string) $session->error_code);
        $this->assertSame('Queue worker crashed while reconciling build tick.', (string) $session->error_summary);

        $failureEventExists = InterrogationEvent::query()
            ->where('interrogation_session_id', (int) $session->id)
            ->where('event_type', InterrogationEvent::TYPE_ERROR)
            ->where('payload->code', 'BUILD_EXECUTION_JOB_FAILED')
            ->exists();

        $this->assertTrue($failureEventExists);
    }

    public function test_failed_handler_ignores_stale_failure_when_build_not_running(): void
    {
        $user = User::factory()->create();
        $session = $this->makeSession($user, [
            'status' => 'completed',
            'active_task_id' => null,
            'active_run_id' => null,
            'completion_summary' => 'Build complete.',
            'error' => null,
        ]);

        $job = new ExecuteInterrogationBuildJob((int) $session->id);
        $job->failed(new \RuntimeException('stale queue failure'));

        $session->refresh();

        $this->assertSame('completed', data_get($session->metadata_json, 'build.status'));
        $this->assertSame(InterrogationSession::STATUS_COMPLETED, (string) $session->status);

        $failureEventExists = InterrogationEvent::query()
            ->where('interrogation_session_id', (int) $session->id)
            ->where('event_type', InterrogationEvent::TYPE_ERROR)
            ->where('payload->code', 'BUILD_EXECUTION_JOB_FAILED')
            ->exists();

        $this->assertFalse($failureEventExists);
    }

    /**
     * @param  array<string, mixed>  $build
     */
    private function makeSession(User $user, array $build, string $runnerType = 'claude'): InterrogationSession
    {
        return InterrogationSession::query()->create([
            'user_id' => $user->id,
            'name' => 'Build session',
            'runner_type' => $runnerType,
            'project_directory' => base_path(),
            'interrogation_type' => InterrogationSession::TYPE_FEATURE,
            'status' => InterrogationSession::STATUS_COMPLETED,
            'phase' => InterrogationSession::PHASE_PLANNING,
            'plan_json' => ['plan_markdown' => 'Plan content'],
            'metadata_json' => ['build' => $build],
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeRun(User $user, string $status, array $overrides = []): AgentJobRun
    {
        $job = AgentJob::query()->create([
            'user_id' => $user->id,
            'name' => 'Synthetic build run job',
            'cron_expression' => '0 0 1 1 0',
            'timezone' => 'UTC',
            'is_enabled' => false,
            'max_runtime_seconds' => 300,
            'cooldown_seconds' => 0,
            'runner_type' => 'claude',
            'command_template' => (string) config('agent.default_templates.claude'),
            'task_markdown_path' => base_path('README.md'),
            'working_directory' => base_path(),
        ]);

        $payload = array_merge([
            'agent_job_id' => $job->id,
            'user_id' => $user->id,
            'initiated_by_user_id' => $user->id,
            'trigger_type' => AgentJobRun::TRIGGER_MANUAL,
            'status' => $status,
            'metadata_json' => [],
        ], $overrides);

        return AgentJobRun::query()->create($payload);
    }
}
