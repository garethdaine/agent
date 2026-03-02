<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\RepoAnalysis;

use App\Jobs\RepoAnalysis\ExecuteRepoAnalysisTaskJob;
use App\Jobs\RepoAnalysis\GenerateRepoSnapshotJob;
use App\Models\RepoAnalysisArtifact;
use App\Models\RepoAnalysisEvent;
use App\Models\RepoAnalysisReport;
use App\Models\RepoAnalysisSession;
use App\Models\RepoAnalysisTask;
use App\Models\User;
use App\Support\RepoAnalysis\SessionStateTransitionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class RepoAnalysisApiLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private string $repoRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repoRoot = storage_path('framework/testing/repo-analysis-api-'.(string) str()->uuid());
        File::deleteDirectory($this->repoRoot);
        File::ensureDirectoryExists($this->repoRoot);
        File::put($this->repoRoot.'/composer.json', '{"name":"acme/repo-analysis-api"}');

        config()->set('repo_analysis.enabled', true);
        config()->set('repo_analysis.user.max_active_sessions_per_user', 2);
        config()->set('agent.allowed_working_directory_bases', [dirname($this->repoRoot)]);
    }

    public function test_repo_analysis_crud_endpoints_validate_and_persist_sessions(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner);

        $this->postJson('/agent/api/v1/repo-analysis/sessions', [
            'name' => 'Invalid Session',
            'project_directory' => 'relative/path',
        ])->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');

        $create = $this->postJson('/agent/api/v1/repo-analysis/sessions', [
            'name' => 'API Session',
            'project_directory' => $this->repoRoot,
            'analyzer_profile' => 'default',
        ])->assertStatus(202)
            ->assertJsonPath('data.name', 'API Session')
            ->assertJsonPath('data.phase', 0)
            ->assertJsonPath('data.status', SessionStateTransitionService::STATUS_SETUP);

        $sessionId = (int) $create->json('data.id');

        $this->getJson('/agent/api/v1/repo-analysis/sessions')
            ->assertOk()
            ->assertJsonPath('data.0.id', $sessionId);

        $this->patchJson('/agent/api/v1/repo-analysis/sessions/'.$sessionId, [
            'name' => 'API Session Updated',
        ])->assertOk()
            ->assertJsonPath('data.name', 'API Session Updated');

        $this->getJson('/agent/api/v1/repo-analysis/sessions/'.$sessionId)
            ->assertOk()
            ->assertJsonPath('data.id', $sessionId);

        $this->deleteJson('/agent/api/v1/repo-analysis/sessions/'.$sessionId)
            ->assertOk()
            ->assertJsonPath('data.deleted', true);

        $this->postJson('/agent/api/v1/repo-analysis/sessions/'.$sessionId.'/restore')
            ->assertOk()
            ->assertJsonPath('data.id', $sessionId);
    }

    public function test_start_snapshot_enforces_active_session_cap_and_dispatches_job(): void
    {
        Queue::fake();

        $owner = User::factory()->create();
        $this->actingAs($owner);

        $this->createSession($owner, ['status' => 'snapshotting', 'phase' => 1]);
        $this->createSession($owner, ['status' => 'planning', 'phase' => 2]);
        $target = $this->createSession($owner, ['status' => 'setup', 'phase' => 0]);

        $this->postJson('/agent/api/v1/repo-analysis/sessions/'.$target->id.'/start-snapshot')
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'ACTIVE_SESSION_LIMIT_REACHED');

        RepoAnalysisSession::query()
            ->where('user_id', $owner->id)
            ->where('id', '!=', $target->id)
            ->firstOrFail()
            ->update([
                'status' => SessionStateTransitionService::STATUS_COMPLETED,
                'phase' => 6,
            ]);

        $this->postJson('/agent/api/v1/repo-analysis/sessions/'.$target->id.'/start-snapshot')
            ->assertStatus(202)
            ->assertJsonPath('data.session_id', $target->id)
            ->assertJsonPath('data.queued', true);

        Queue::assertPushed(GenerateRepoSnapshotJob::class, fn (GenerateRepoSnapshotJob $job): bool => $job->sessionId === $target->id);
    }

    public function test_invalid_transition_codes_are_returned_for_resume_and_restart_while_running(): void
    {
        Queue::fake();

        $owner = User::factory()->create();
        $this->actingAs($owner);

        $session = $this->createSession($owner, [
            'phase' => 3,
            'status' => SessionStateTransitionService::STATUS_EXECUTING,
        ]);

        $this->postJson('/agent/api/v1/repo-analysis/sessions/'.$session->id.'/resume')
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'RUN_TRANSITION_CONFLICT');

        $this->postJson('/agent/api/v1/repo-analysis/sessions/'.$session->id.'/restart-from-beginning')
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'RUN_TRANSITION_CONFLICT');
    }

    public function test_retry_task_endpoint_validates_task_identifier_and_requeues_failed_task(): void
    {
        Queue::fake();

        $owner = User::factory()->create();
        $this->actingAs($owner);

        $session = $this->createSession($owner, [
            'phase' => 3,
            'status' => SessionStateTransitionService::STATUS_PAUSED,
        ]);

        $task = RepoAnalysisTask::query()->create([
            'repo_analysis_session_id' => $session->id,
            'task_key' => 'filesystem_manifest',
            'task_type' => 'analyzer',
            'status' => 'pending',
            'phase' => 3,
            'analyzer_name' => 'filesystem_manifest',
            'analyzer_version' => '1.0.0',
        ]);

        $this->postJson('/agent/api/v1/repo-analysis/sessions/'.$session->id.'/retry-task', [])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');

        $otherSession = $this->createSession($owner);
        $otherTask = RepoAnalysisTask::query()->create([
            'repo_analysis_session_id' => $otherSession->id,
            'task_key' => 'dependency_manifest',
            'task_type' => 'analyzer',
            'status' => 'failed',
            'phase' => 3,
            'analyzer_name' => 'dependency_manifest',
            'analyzer_version' => '1.0.0',
        ]);

        $this->postJson('/agent/api/v1/repo-analysis/sessions/'.$session->id.'/retry-task', [
            'task_id' => $otherTask->id,
        ])->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');

        $this->postJson('/agent/api/v1/repo-analysis/sessions/'.$session->id.'/retry-task', [
            'task_id' => $task->id,
        ])->assertStatus(409)
            ->assertJsonPath('error.code', 'RUN_TRANSITION_CONFLICT');

        $task->update([
            'status' => 'failed',
            'attempt_count' => 2,
            'error_code' => 'retryable_failure',
            'error_summary' => 'failed',
        ]);

        $this->postJson('/agent/api/v1/repo-analysis/sessions/'.$session->id.'/retry-task', [
            'task_id' => $task->id,
        ])->assertStatus(202)
            ->assertJsonPath('data.session_id', $session->id)
            ->assertJsonPath('data.task_id', $task->id)
            ->assertJsonPath('data.queued', true);

        Queue::assertPushed(ExecuteRepoAnalysisTaskJob::class, fn (ExecuteRepoAnalysisTaskJob $job): bool => $job->sessionId === $session->id);
    }

    public function test_events_since_sequence_and_read_endpoints_are_consistent(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner);

        $session = $this->createSession($owner);

        RepoAnalysisEvent::query()->create([
            'repo_analysis_session_id' => $session->id,
            'sequence' => 1,
            'event_type' => 'phase_transition',
            'payload_json' => ['from' => 0, 'to' => 1],
            'phase' => 1,
            'status' => 'snapshotting',
            'event_ts' => now('UTC'),
        ]);

        RepoAnalysisEvent::query()->create([
            'repo_analysis_session_id' => $session->id,
            'sequence' => 2,
            'event_type' => 'task_started',
            'payload_json' => ['task_key' => 'filesystem_manifest'],
            'phase' => 3,
            'status' => 'executing',
            'event_ts' => now('UTC'),
        ]);

        RepoAnalysisEvent::query()->create([
            'repo_analysis_session_id' => $session->id,
            'sequence' => 3,
            'event_type' => 'task_completed',
            'payload_json' => ['task_key' => 'filesystem_manifest'],
            'phase' => 3,
            'status' => 'executing',
            'event_ts' => now('UTC'),
        ]);

        $task = RepoAnalysisTask::query()->create([
            'repo_analysis_session_id' => $session->id,
            'task_key' => 'filesystem_manifest',
            'task_type' => 'analyzer',
            'status' => 'completed',
            'phase' => 3,
            'analyzer_name' => 'filesystem_manifest',
            'analyzer_version' => '1.0.0',
            'output_hash' => hash('sha256', 'output'),
        ]);

        RepoAnalysisArtifact::query()->create([
            'repo_analysis_session_id' => $session->id,
            'repo_analysis_task_id' => $task->id,
            'artifact_type' => 'filesystem_manifest',
            'artifact_key' => 'filesystem_manifest.tree',
            'content_hash' => hash('sha256', 'artifact'),
        ]);

        RepoAnalysisReport::query()->create([
            'repo_analysis_session_id' => $session->id,
            'report_version' => '1.0.0',
            'report_hash' => hash('sha256', 'report'),
            'status' => 'generated',
            'payload_json' => ['summary' => 'ok'],
            'generated_at' => now('UTC'),
        ]);

        $this->getJson('/agent/api/v1/repo-analysis/sessions/'.$session->id.'/events?since_sequence=1&limit=10')
            ->assertOk()
            ->assertJsonPath('meta.returned', 2)
            ->assertJsonPath('meta.latest_sequence', 3)
            ->assertJsonPath('data.0.sequence', 2)
            ->assertJsonPath('data.1.sequence', 3);

        $this->getJson('/agent/api/v1/repo-analysis/sessions/'.$session->id.'/tasks')
            ->assertOk()
            ->assertJsonPath('meta.returned', 1)
            ->assertJsonPath('data.0.id', $task->id);

        $this->getJson('/agent/api/v1/repo-analysis/sessions/'.$session->id.'/artifacts')
            ->assertOk()
            ->assertJsonPath('meta.returned', 1)
            ->assertJsonPath('data.0.repo_analysis_task_id', $task->id);

        $this->getJson('/agent/api/v1/repo-analysis/sessions/'.$session->id.'/reports')
            ->assertOk()
            ->assertJsonPath('meta.returned', 1)
            ->assertJsonPath('data.0.report_version', '1.0.0');
    }

    public function test_owner_only_policy_with_admin_override_for_retry_and_restart_mutations(): void
    {
        Queue::fake();

        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $adminUser = User::factory()->create();

        config()->set('agent.roles.admin_user_ids', [$adminUser->id]);

        $retrySession = $this->createSession($owner, [
            'phase' => 3,
            'status' => SessionStateTransitionService::STATUS_FAILED,
            'error_code' => 'EXECUTE_TASK_NON_RETRYABLE',
            'error_summary' => 'failed',
        ]);

        $restartSession = $this->createSession($owner, [
            'phase' => 3,
            'status' => SessionStateTransitionService::STATUS_PAUSED,
        ]);

        $this->actingAs($otherUser);
        $this->postJson('/agent/api/v1/repo-analysis/sessions/'.$retrySession->id.'/retry')
            ->assertStatus(403);
        $this->postJson('/agent/api/v1/repo-analysis/sessions/'.$restartSession->id.'/restart-from-beginning')
            ->assertStatus(403);

        $this->actingAs($adminUser);
        $this->postJson('/agent/api/v1/repo-analysis/sessions/'.$retrySession->id.'/retry')
            ->assertStatus(202)
            ->assertJsonPath('data.queued', true);

        $this->postJson('/agent/api/v1/repo-analysis/sessions/'.$restartSession->id.'/restart-from-beginning')
            ->assertStatus(202)
            ->assertJsonPath('data.queued', true);
    }

    public function test_lifecycle_mutations_write_audit_log_entries(): void
    {
        Queue::fake();

        $owner = User::factory()->create();
        $this->actingAs($owner);

        $session = $this->createSession($owner, [
            'phase' => 0,
            'status' => SessionStateTransitionService::STATUS_SETUP,
        ]);

        $this->postJson('/agent/api/v1/repo-analysis/sessions/'.$session->id.'/start-snapshot')
            ->assertStatus(202)
            ->assertJsonPath('data.queued', true);

        $session->update([
            'phase' => 3,
            'status' => SessionStateTransitionService::STATUS_PAUSED,
        ]);

        $this->postJson('/agent/api/v1/repo-analysis/sessions/'.$session->id.'/restart-from-beginning')
            ->assertStatus(202)
            ->assertJsonPath('data.queued', true);

        $this->assertDatabaseHas('agent_audit_logs', [
            'action' => 'repo_analysis.session.start_snapshot',
            'target_type' => 'repo_analysis_session',
            'target_id' => (string) $session->id,
            'user_id' => $owner->id,
            'outcome' => 'success',
        ]);

        $this->assertDatabaseHas('agent_audit_logs', [
            'action' => 'repo_analysis.session.restart',
            'target_type' => 'repo_analysis_session',
            'target_id' => (string) $session->id,
            'user_id' => $owner->id,
            'outcome' => 'success',
        ]);
    }

    private function createSession(User $owner, array $overrides = []): RepoAnalysisSession
    {
        return RepoAnalysisSession::query()->create(array_merge([
            'user_id' => $owner->id,
            'name' => 'Repo Analysis Session',
            'project_directory' => $this->repoRoot,
            'analyzer_profile' => 'default',
            'phase' => 0,
            'status' => SessionStateTransitionService::STATUS_SETUP,
            'metadata_json' => [],
        ], $overrides));
    }
}
