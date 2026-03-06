<?php

namespace Tests\Feature\Listeners\Org;

use App\Events\DelegationTaskVerified;
use App\Listeners\Org\RitualPhaseOutputCaptureListener;
use App\Models\AgentRunEvent;
use App\Models\DelegateeProfile;
use App\Models\DelegationAttempt;
use App\Models\DelegationGraph;
use App\Models\DelegationTask;
use App\Models\OrgRitualRun;
use App\Models\OrgRitualTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RitualPhaseOutputCaptureListenerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private OrgRitualTemplate $template;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->template = OrgRitualTemplate::factory()->create([
            'user_id' => $this->user->id,
        ]);
    }

    public function test_captures_output_on_verified_task_with_ritual_context(): void
    {
        $graph = DelegationGraph::factory()->running()->create([
            'user_id' => $this->user->id,
        ]);

        $run = OrgRitualRun::factory()->running()->create([
            'ritual_template_id' => $this->template->id,
            'user_id' => $this->user->id,
            'delegation_graph_id' => $graph->id,
            'phase_outputs' => [],
        ]);

        $task = DelegationTask::factory()->running()->create([
            'delegation_graph_id' => $graph->id,
            'contract_json' => [
                'ritual_run_id' => $run->id,
                'phase_id' => 'analysis_phase',
                'required_capability' => 'review',
            ],
        ]);

        $profile = DelegateeProfile::factory()->create([
            'user_id' => $this->user->id,
        ]);

        $attempt = DelegationAttempt::factory()->succeeded()->create([
            'delegation_task_id' => $task->id,
            'delegatee_profile_id' => $profile->id,
            'metadata_json' => ['tokens_used' => 1500],
        ]);

        $event = new DelegationTaskVerified($task, $attempt, true);

        $listener = app(RitualPhaseOutputCaptureListener::class);
        $listener->handle($event);

        $run->refresh();
        $outputs = $run->phase_outputs;

        $this->assertArrayHasKey('analysis_phase', $outputs);
        $this->assertEquals('succeeded', $outputs['analysis_phase']['status']);
        $this->assertEquals($task->name, $outputs['analysis_phase']['task_name']);
        $this->assertArrayHasKey('duration_ms', $outputs['analysis_phase']);
        $this->assertArrayHasKey('captured_at', $outputs['analysis_phase']);
        $this->assertEquals(['tokens_used' => 1500], $outputs['analysis_phase']['attempt_metadata']);
    }

    public function test_skips_tasks_without_ritual_run_id_in_contract(): void
    {
        $graph = DelegationGraph::factory()->running()->create([
            'user_id' => $this->user->id,
        ]);

        $task = DelegationTask::factory()->running()->create([
            'delegation_graph_id' => $graph->id,
            'contract_json' => ['required_capability' => 'code_generation'],
        ]);

        $profile = DelegateeProfile::factory()->create([
            'user_id' => $this->user->id,
        ]);

        $attempt = DelegationAttempt::factory()->succeeded()->create([
            'delegation_task_id' => $task->id,
            'delegatee_profile_id' => $profile->id,
        ]);

        $event = new DelegationTaskVerified($task, $attempt, true);

        $listener = app(RitualPhaseOutputCaptureListener::class);
        $listener->handle($event);

        // No ritual run should be affected (nothing to assert on since no run exists)
        $this->assertTrue(true);
    }

    public function test_skips_when_ritual_run_not_found(): void
    {
        $graph = DelegationGraph::factory()->running()->create([
            'user_id' => $this->user->id,
        ]);

        $task = DelegationTask::factory()->running()->create([
            'delegation_graph_id' => $graph->id,
            'contract_json' => [
                'ritual_run_id' => '00000000-0000-0000-0000-000000000000',
                'phase_id' => 'missing_phase',
            ],
        ]);

        $profile = DelegateeProfile::factory()->create([
            'user_id' => $this->user->id,
        ]);

        $attempt = DelegationAttempt::factory()->succeeded()->create([
            'delegation_task_id' => $task->id,
            'delegatee_profile_id' => $profile->id,
        ]);

        $event = new DelegationTaskVerified($task, $attempt, true);

        $listener = app(RitualPhaseOutputCaptureListener::class);
        $listener->handle($event);

        $this->assertTrue(true);
    }

    public function test_skips_when_ritual_run_not_in_active_state(): void
    {
        $graph = DelegationGraph::factory()->running()->create([
            'user_id' => $this->user->id,
        ]);

        $run = OrgRitualRun::factory()->succeeded()->create([
            'ritual_template_id' => $this->template->id,
            'user_id' => $this->user->id,
            'delegation_graph_id' => $graph->id,
            'phase_outputs' => [],
        ]);

        $task = DelegationTask::factory()->running()->create([
            'delegation_graph_id' => $graph->id,
            'contract_json' => [
                'ritual_run_id' => $run->id,
                'phase_id' => 'analysis_phase',
            ],
        ]);

        $profile = DelegateeProfile::factory()->create([
            'user_id' => $this->user->id,
        ]);

        $attempt = DelegationAttempt::factory()->succeeded()->create([
            'delegation_task_id' => $task->id,
            'delegatee_profile_id' => $profile->id,
        ]);

        $event = new DelegationTaskVerified($task, $attempt, true);

        $listener = app(RitualPhaseOutputCaptureListener::class);
        $listener->handle($event);

        $run->refresh();
        $this->assertEmpty($run->phase_outputs);
    }

    public function test_captures_agent_output_from_run_events(): void
    {
        $graph = DelegationGraph::factory()->running()->create([
            'user_id' => $this->user->id,
        ]);

        $run = OrgRitualRun::factory()->running()->create([
            'ritual_template_id' => $this->template->id,
            'user_id' => $this->user->id,
            'delegation_graph_id' => $graph->id,
            'phase_outputs' => [],
        ]);

        $agentJobRun = \App\Models\AgentJobRun::factory()->create([
            'user_id' => $this->user->id,
            'exit_code' => 0,
        ]);

        $task = DelegationTask::factory()->running()->create([
            'delegation_graph_id' => $graph->id,
            'contract_json' => [
                'ritual_run_id' => $run->id,
                'phase_id' => 'review_phase',
            ],
        ]);

        $profile = DelegateeProfile::factory()->create([
            'user_id' => $this->user->id,
        ]);

        $attempt = DelegationAttempt::factory()->succeeded()->create([
            'delegation_task_id' => $task->id,
            'delegatee_profile_id' => $profile->id,
            'agent_job_run_id' => $agentJobRun->id,
        ]);

        // Create stdout events
        AgentRunEvent::create([
            'agent_job_run_id' => $agentJobRun->id,
            'event_type' => 'stdout',
            'sequence' => 1,
            'payload' => '# Analysis Report',
            'event_ts' => now(),
        ]);
        AgentRunEvent::create([
            'agent_job_run_id' => $agentJobRun->id,
            'event_type' => 'stdout',
            'sequence' => 2,
            'payload' => "\nAll tests pass.",
            'event_ts' => now(),
        ]);

        $event = new DelegationTaskVerified($task, $attempt, true);

        $listener = app(RitualPhaseOutputCaptureListener::class);
        $listener->handle($event);

        $run->refresh();
        $output = $run->phase_outputs['review_phase'];

        $this->assertEquals("# Analysis Report\nAll tests pass.", $output['agent_output']);
        $this->assertEquals(0, $output['exit_code']);
    }

    public function test_truncates_agent_output_exceeding_limit(): void
    {
        $graph = DelegationGraph::factory()->running()->create([
            'user_id' => $this->user->id,
        ]);

        $run = OrgRitualRun::factory()->running()->create([
            'ritual_template_id' => $this->template->id,
            'user_id' => $this->user->id,
            'delegation_graph_id' => $graph->id,
            'phase_outputs' => [],
        ]);

        $agentJobRun = \App\Models\AgentJobRun::factory()->create([
            'user_id' => $this->user->id,
        ]);

        $task = DelegationTask::factory()->running()->create([
            'delegation_graph_id' => $graph->id,
            'contract_json' => [
                'ritual_run_id' => $run->id,
                'phase_id' => 'big_phase',
            ],
        ]);

        $profile = DelegateeProfile::factory()->create([
            'user_id' => $this->user->id,
        ]);

        $attempt = DelegationAttempt::factory()->succeeded()->create([
            'delegation_task_id' => $task->id,
            'delegatee_profile_id' => $profile->id,
            'agent_job_run_id' => $agentJobRun->id,
        ]);

        // Create a large stdout payload (>64KB)
        $largePayload = str_repeat('x', 70000);
        AgentRunEvent::create([
            'agent_job_run_id' => $agentJobRun->id,
            'event_type' => 'stdout',
            'sequence' => 1,
            'payload' => $largePayload,
            'event_ts' => now(),
        ]);

        $event = new DelegationTaskVerified($task, $attempt, true);

        $listener = app(RitualPhaseOutputCaptureListener::class);
        $listener->handle($event);

        $run->refresh();
        $output = $run->phase_outputs['big_phase'];

        $this->assertEquals(65536, strlen($output['agent_output']));
    }

    public function test_handles_missing_agent_job_run_gracefully(): void
    {
        $graph = DelegationGraph::factory()->running()->create([
            'user_id' => $this->user->id,
        ]);

        $run = OrgRitualRun::factory()->running()->create([
            'ritual_template_id' => $this->template->id,
            'user_id' => $this->user->id,
            'delegation_graph_id' => $graph->id,
            'phase_outputs' => [],
        ]);

        $task = DelegationTask::factory()->running()->create([
            'delegation_graph_id' => $graph->id,
            'contract_json' => [
                'ritual_run_id' => $run->id,
                'phase_id' => 'no_run_phase',
            ],
        ]);

        $profile = DelegateeProfile::factory()->create([
            'user_id' => $this->user->id,
        ]);

        // Attempt without agent_job_run_id
        $attempt = DelegationAttempt::factory()->succeeded()->create([
            'delegation_task_id' => $task->id,
            'delegatee_profile_id' => $profile->id,
            'agent_job_run_id' => null,
        ]);

        $event = new DelegationTaskVerified($task, $attempt, true);

        $listener = app(RitualPhaseOutputCaptureListener::class);
        $listener->handle($event);

        $run->refresh();
        $output = $run->phase_outputs['no_run_phase'];

        $this->assertEquals('succeeded', $output['status']);
        $this->assertEquals('', $output['agent_output']);
        $this->assertNull($output['exit_code']);
    }

    public function test_captures_failed_verification_outcome(): void
    {
        $graph = DelegationGraph::factory()->running()->create([
            'user_id' => $this->user->id,
        ]);

        $run = OrgRitualRun::factory()->running()->create([
            'ritual_template_id' => $this->template->id,
            'user_id' => $this->user->id,
            'delegation_graph_id' => $graph->id,
            'phase_outputs' => [],
        ]);

        $task = DelegationTask::factory()->running()->create([
            'delegation_graph_id' => $graph->id,
            'contract_json' => [
                'ritual_run_id' => $run->id,
                'phase_id' => 'failing_phase',
            ],
        ]);

        $profile = DelegateeProfile::factory()->create([
            'user_id' => $this->user->id,
        ]);

        $attempt = DelegationAttempt::factory()->succeeded()->create([
            'delegation_task_id' => $task->id,
            'delegatee_profile_id' => $profile->id,
        ]);

        $event = new DelegationTaskVerified($task, $attempt, false, 2);

        $listener = app(RitualPhaseOutputCaptureListener::class);
        $listener->handle($event);

        $run->refresh();
        $output = $run->phase_outputs['failing_phase'];

        $this->assertEquals('failed', $output['status']);
        $this->assertEquals(2, $output['verification_failed_at_step']);
    }

    public function test_does_not_overwrite_existing_phase_output(): void
    {
        $graph = DelegationGraph::factory()->running()->create([
            'user_id' => $this->user->id,
        ]);

        $run = OrgRitualRun::factory()->running()->create([
            'ritual_template_id' => $this->template->id,
            'user_id' => $this->user->id,
            'delegation_graph_id' => $graph->id,
            'phase_outputs' => [
                'existing_phase' => ['status' => 'succeeded', 'council_result' => 'approved'],
            ],
        ]);

        $task = DelegationTask::factory()->running()->create([
            'delegation_graph_id' => $graph->id,
            'contract_json' => [
                'ritual_run_id' => $run->id,
                'phase_id' => 'existing_phase',
            ],
        ]);

        $profile = DelegateeProfile::factory()->create([
            'user_id' => $this->user->id,
        ]);

        $attempt = DelegationAttempt::factory()->succeeded()->create([
            'delegation_task_id' => $task->id,
            'delegatee_profile_id' => $profile->id,
        ]);

        $event = new DelegationTaskVerified($task, $attempt, true);

        $listener = app(RitualPhaseOutputCaptureListener::class);
        $listener->handle($event);

        $run->refresh();
        $output = $run->phase_outputs['existing_phase'];

        // Should still have the original data, not overwritten
        $this->assertEquals('approved', $output['council_result']);
        $this->assertArrayNotHasKey('task_name', $output);
    }
}
