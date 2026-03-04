<?php

declare(strict_types=1);

namespace Tests\Feature\Governance;

use App\Models\AgentJob;
use App\Models\AgentJobRun;
use App\Models\User;
use App\Support\Agent\DispatchDueService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PauseResumeAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_pause_and_resume_are_rbac_authorized(): void
    {
        $owner = User::factory()->create();
        $onCall = User::factory()->create();
        $delegate = User::factory()->create();
        $outsider = User::factory()->create();

        config()->set('agent.roles.central_on_call_user_ids', [$onCall->id]);
        config()->set('agent.roles.workflow_delegate_user_ids', [$delegate->id]);

        $job = AgentJob::factory()->create([
            'user_id' => $owner->id,
            'workflow_key' => 'eng.repo-analysis.v1',
        ]);

        Sanctum::actingAs($owner);
        $this->postJson('/agent/api/v1/workflows/'.$job->workflow_key.'/pause', [
            'reason' => 'Investigating hard fail burst',
            'run_id' => 'run-001',
        ])->assertStatus(403);

        Sanctum::actingAs($onCall);
        $this->postJson('/agent/api/v1/workflows/'.$job->workflow_key.'/pause', [
            'reason' => 'Investigating hard fail burst',
            'run_id' => 'run-001',
        ])
            ->assertOk()
            ->assertJsonPath('data.workflow_key', $job->workflow_key)
            ->assertJsonPath('data.governance_paused', true);

        $job->refresh();
        $this->assertNotNull($job->governance_paused_at);

        Sanctum::actingAs($owner);
        $this->postJson('/agent/api/v1/workflows/'.$job->workflow_key.'/resume', [
            'reason' => 'Owner verified mitigation',
            'run_id' => 'run-002',
        ])
            ->assertOk()
            ->assertJsonPath('data.governance_paused', false);

        $job->refresh();
        $this->assertNull($job->governance_paused_at);

        Sanctum::actingAs($onCall);
        $this->postJson('/agent/api/v1/workflows/'.$job->workflow_key.'/pause', [
            'reason' => 'Second incident triage',
            'run_id' => 'run-003',
        ])->assertOk();

        Sanctum::actingAs($delegate);
        $this->postJson('/agent/api/v1/workflows/'.$job->workflow_key.'/resume', [
            'reason' => 'Delegate approved resume',
            'run_id' => 'run-004',
        ])->assertOk();

        Sanctum::actingAs($outsider);
        $this->postJson('/agent/api/v1/workflows/'.$job->workflow_key.'/resume', [
            'reason' => 'Unauthorized resume',
            'run_id' => 'run-005',
        ])->assertStatus(403);
    }

    public function test_pause_blocks_new_scheduled_runs_only_and_persists_skip_reason(): void
    {
        Queue::fake();

        $owner = User::factory()->create();
        $onCall = User::factory()->create();

        config()->set('agent.roles.central_on_call_user_ids', [$onCall->id]);

        $now = CarbonImmutable::now('UTC')->startOfMinute();

        $job = AgentJob::factory()->create([
            'user_id' => $owner->id,
            'workflow_key' => 'eng.code-implementation.v1',
            'cron_expression' => sprintf('%d %d %d %d %d', $now->minute, $now->hour, $now->day, $now->month, $now->dayOfWeek),
            'timezone' => 'UTC',
            'is_enabled' => true,
            'cooldown_seconds' => 0,
        ]);

        $running = AgentJobRun::factory()->running()->create([
            'agent_job_id' => $job->id,
            'user_id' => $owner->id,
            'trigger_type' => AgentJobRun::TRIGGER_MANUAL,
        ]);

        Sanctum::actingAs($onCall);
        $this->postJson('/agent/api/v1/workflows/'.$job->workflow_key.'/pause', [
            'reason' => 'Reliability gate active pause',
            'run_id' => 'run-010',
        ])->assertOk();

        $running->refresh();
        $this->assertSame(AgentJobRun::STATUS_RUNNING, $running->status, 'Pause action should not mutate in-flight run state.');

        app(DispatchDueService::class)->dispatch($now);

        $skipped = AgentJobRun::query()
            ->where('agent_job_id', $job->id)
            ->where('trigger_type', AgentJobRun::TRIGGER_SCHEDULE)
            ->where('status', AgentJobRun::STATUS_SKIPPED)
            ->latest('id')
            ->first();

        $this->assertNotNull($skipped);
        $this->assertSame('governance_paused', data_get($skipped?->metadata_json, 'skip_reason'));
        $this->assertSame('Reliability gate active pause', data_get($skipped?->metadata_json, 'governance_pause_reason'));
    }
}
