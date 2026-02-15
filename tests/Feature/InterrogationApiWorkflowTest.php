<?php

namespace Tests\Feature;

use App\Jobs\ExecuteInterrogationDiscoveryJob;
use App\Jobs\ExecuteInterrogationPlanJob;
use App\Jobs\ExecuteInterrogationRoundJob;
use App\Models\InterrogationEvent;
use App\Models\InterrogationSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class InterrogationApiWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_interrogation_session_lifecycle_endpoints_work(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $this->actingAs($user);

        $create = $this->postJson('/agent/api/v1/interrogation/sessions', [
            'name' => 'Discovery Session',
            'runner_type' => 'claude',
            'project_directory' => base_path(),
            'interrogation_type' => 'feature',
            'feature_brief' => 'Add requirements discovery wizard UI.',
        ])->assertStatus(202);

        $sessionId = $create->json('data.id');

        $this->assertNotNull($sessionId);

        Queue::assertPushed(ExecuteInterrogationDiscoveryJob::class, function (ExecuteInterrogationDiscoveryJob $job) use ($sessionId) {
            return (int) $job->sessionId === (int) $sessionId;
        });

        $this->getJson('/agent/api/v1/interrogation/sessions/'.$sessionId)
            ->assertOk()
            ->assertJsonPath('data.id', $sessionId);

        $this->postJson('/agent/api/v1/interrogation/sessions/'.$sessionId.'/answer', [
            'question_id' => 'q-1',
            'answer_type' => 'freetext',
            'answer_text' => 'Initial answer text.',
        ])->assertStatus(202);

        Queue::assertPushed(ExecuteInterrogationRoundJob::class, function (ExecuteInterrogationRoundJob $job) use ($sessionId) {
            return (int) $job->sessionId === (int) $sessionId;
        });

        $this->postJson('/agent/api/v1/interrogation/sessions/'.$sessionId.'/confirm-summary')
            ->assertStatus(409);

        $session = InterrogationSession::query()->findOrFail($sessionId);
        $session->update([
            'phase' => InterrogationSession::PHASE_SUMMARY,
            'status' => InterrogationSession::STATUS_SUMMARIZING,
            'summary_json' => [
                'summary_markdown' => 'Summary ready',
            ],
        ]);

        $this->postJson('/agent/api/v1/interrogation/sessions/'.$sessionId.'/confirm-summary')
            ->assertOk();

        $this->postJson('/agent/api/v1/interrogation/sessions/'.$sessionId.'/generate-plan')
            ->assertStatus(202);

        Queue::assertPushed(ExecuteInterrogationPlanJob::class, function (ExecuteInterrogationPlanJob $job) use ($sessionId) {
            return (int) $job->sessionId === (int) $sessionId;
        });

        $this->patchJson('/agent/api/v1/interrogation/sessions/'.$sessionId.'/annotations', [
            'key' => 'priority',
            'value' => 'high',
        ])->assertOk();

        $this->postJson('/agent/api/v1/interrogation/sessions/'.$sessionId.'/pause')
            ->assertOk();

        $this->postJson('/agent/api/v1/interrogation/sessions/'.$sessionId.'/resume')
            ->assertOk();

        $this->deleteJson('/agent/api/v1/interrogation/sessions/'.$sessionId)
            ->assertOk();

        $this->postJson('/agent/api/v1/interrogation/sessions/'.$sessionId.'/restore')
            ->assertOk();
    }

    public function test_interrogation_events_and_settings_endpoints_work(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $session = InterrogationSession::query()->create([
            'user_id' => $user->id,
            'name' => 'Test',
            'runner_type' => 'codex',
            'project_directory' => base_path(),
            'interrogation_type' => InterrogationSession::TYPE_GENERAL,
            'status' => InterrogationSession::STATUS_INTERROGATING,
            'phase' => InterrogationSession::PHASE_INTERROGATION,
        ]);

        InterrogationEvent::query()->create([
            'interrogation_session_id' => $session->id,
            'event_type' => InterrogationEvent::TYPE_QUESTION,
            'sequence' => 1,
            'payload' => ['question_text' => 'What should this do?', 'question_id' => 'q-1'],
            'event_ts' => now('UTC'),
        ]);

        $this->getJson('/agent/api/v1/interrogation/sessions/'.$session->id.'/events?after_sequence=0&limit=10')
            ->assertOk()
            ->assertJsonPath('meta.returned', 1)
            ->assertJsonPath('data.0.sequence', 1);

        $this->putJson('/agent/api/v1/interrogation/settings/interrogation.system_prompt', [
            'value' => 'System prompt text',
        ])->assertOk();

        $this->getJson('/agent/api/v1/interrogation/settings/interrogation.system_prompt')
            ->assertOk()
            ->assertJsonPath('data.value', 'System prompt text');

        $this->getJson('/agent/api/v1/interrogation/settings')
            ->assertOk()
            ->assertJsonPath('data.0.key', 'interrogation.system_prompt');
    }

    public function test_retry_endpoint_requeues_failed_session_for_current_phase(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $this->actingAs($user);

        $session = InterrogationSession::query()->create([
            'user_id' => $user->id,
            'name' => 'Retry session',
            'runner_type' => 'claude',
            'project_directory' => base_path(),
            'interrogation_type' => InterrogationSession::TYPE_FEATURE,
            'status' => InterrogationSession::STATUS_FAILED,
            'phase' => InterrogationSession::PHASE_DISCOVERY,
            'error_code' => 'DISCOVERY_COMMAND_FAILED',
            'error_summary' => 'failed once',
            'finished_at' => now('UTC'),
        ]);

        $this->postJson('/agent/api/v1/interrogation/sessions/'.$session->id.'/retry')
            ->assertStatus(202)
            ->assertJsonPath('data.queued', true)
            ->assertJsonPath('data.session_id', $session->id);

        $session->refresh();

        $this->assertSame(InterrogationSession::STATUS_DISCOVERING, $session->status);
        $this->assertNull($session->error_code);
        $this->assertNull($session->error_summary);
        $this->assertNull($session->finished_at);

        Queue::assertPushed(ExecuteInterrogationDiscoveryJob::class, function (ExecuteInterrogationDiscoveryJob $job) use ($session) {
            return (int) $job->sessionId === (int) $session->id;
        });
    }
}
