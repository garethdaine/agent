<?php

declare(strict_types=1);

namespace Tests\Feature\Support\Delegation;

use App\Events\DelegationTaskVerified;
use App\Models\DelegateeProfile;
use App\Models\DelegationAttempt;
use App\Models\DelegationGraph;
use App\Models\DelegationTask;
use App\Models\DelegationVerificationResult;
use App\Models\User;
use App\Support\Delegation\VerificationPipeline;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class VerificationPipelineTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private DelegationGraph $graph;

    private DelegateeProfile $profile;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'delegation.human_approval_timeout_hours' => 4,
            'delegation.ai_critic_default_prompt_template' => 'Review the output.',
            'delegation.check_profiles' => [
                'laravel_standard' => ['php artisan test', './vendor/bin/pint --test'],
                'test_only' => ['php artisan test'],
            ],
        ]);

        $this->user = User::factory()->create();
        $this->graph = DelegationGraph::factory()->create(['user_id' => $this->user->id]);
        $this->profile = DelegateeProfile::factory()->create(['user_id' => $this->user->id]);
    }

    public function test_passes_immediately_when_no_strategy(): void
    {
        Event::fake([DelegationTaskVerified::class]);

        $task = DelegationTask::factory()->create([
            'delegation_graph_id' => $this->graph->id,
            'status' => DelegationTask::STATUS_VERIFYING,
            'contract_json' => [
                'required_capability' => 'code_execution',
                'prompt' => 'Test task',
                // No verification_strategy
            ],
        ]);
        $attempt = DelegationAttempt::factory()->succeeded()->create([
            'delegation_task_id' => $task->id,
            'delegatee_profile_id' => $this->profile->id,
        ]);

        $pipeline = new VerificationPipeline;
        $pipeline->execute($task, $attempt);

        Event::assertDispatched(DelegationTaskVerified::class, function ($event) use ($task) {
            return $event->task->id === $task->id && $event->passed === true;
        });
    }

    public function test_executes_steps_in_order(): void
    {
        Process::fake([
            'php artisan test' => Process::result(output: 'OK', exitCode: 0),
        ]);
        Event::fake([DelegationTaskVerified::class]);

        $task = DelegationTask::factory()->create([
            'delegation_graph_id' => $this->graph->id,
            'status' => DelegationTask::STATUS_VERIFYING,
            'contract_json' => [
                'required_capability' => 'code_execution',
                'prompt' => 'Test task',
                'verification_strategy' => [
                    'steps' => [
                        [
                            'type' => 'automated_check',
                            'step_order' => 0,
                            'check_profile' => 'test_only',
                        ],
                    ],
                ],
            ],
        ]);
        $attempt = DelegationAttempt::factory()->succeeded()->create([
            'delegation_task_id' => $task->id,
            'delegatee_profile_id' => $this->profile->id,
        ]);

        $pipeline = new VerificationPipeline;
        $pipeline->execute($task, $attempt);

        // First step should have run
        Process::assertRan('php artisan test');

        // Verification result should be created
        $this->assertDatabaseHas('delegation_verification_results', [
            'delegation_task_id' => $task->id,
            'step_type' => DelegationVerificationResult::STEP_TYPE_AUTOMATED_CHECK,
            'verdict' => DelegationVerificationResult::VERDICT_PASSED,
        ]);

        // Task should be verified
        Event::assertDispatched(DelegationTaskVerified::class);
    }

    public function test_short_circuits_on_first_failure(): void
    {
        Process::fake([
            'php artisan test' => Process::result(output: '', errorOutput: 'Failed', exitCode: 1),
        ]);
        Event::fake([DelegationTaskVerified::class]);

        $task = DelegationTask::factory()->create([
            'delegation_graph_id' => $this->graph->id,
            'status' => DelegationTask::STATUS_VERIFYING,
            'contract_json' => [
                'required_capability' => 'code_execution',
                'prompt' => 'Test task',
                'verification_strategy' => [
                    'steps' => [
                        [
                            'type' => 'automated_check',
                            'step_order' => 0,
                            'check_profile' => 'test_only',
                        ],
                        [
                            'type' => 'human_approval',
                            'step_order' => 1,
                        ],
                    ],
                ],
            ],
        ]);
        $attempt = DelegationAttempt::factory()->succeeded()->create([
            'delegation_task_id' => $task->id,
            'delegatee_profile_id' => $this->profile->id,
        ]);

        $pipeline = new VerificationPipeline;
        $pipeline->execute($task, $attempt);

        // First step should fail
        $this->assertDatabaseHas('delegation_verification_results', [
            'delegation_task_id' => $task->id,
            'step_type' => DelegationVerificationResult::STEP_TYPE_AUTOMATED_CHECK,
            'verdict' => DelegationVerificationResult::VERDICT_FAILED,
        ]);

        // Second step should NOT be created (short-circuit)
        $this->assertDatabaseMissing('delegation_verification_results', [
            'delegation_task_id' => $task->id,
            'step_type' => DelegationVerificationResult::STEP_TYPE_HUMAN_APPROVAL,
        ]);

        // Should fire failed event
        Event::assertDispatched(DelegationTaskVerified::class, function ($event) {
            return $event->passed === false;
        });
    }

    public function test_fires_task_verified_passed_event(): void
    {
        Process::fake([
            'php artisan test' => Process::result(output: 'OK', exitCode: 0),
        ]);
        Event::fake([DelegationTaskVerified::class]);

        $task = DelegationTask::factory()->create([
            'delegation_graph_id' => $this->graph->id,
            'status' => DelegationTask::STATUS_VERIFYING,
            'contract_json' => [
                'required_capability' => 'code_execution',
                'prompt' => 'Test task',
                'verification_strategy' => [
                    'steps' => [
                        [
                            'type' => 'automated_check',
                            'step_order' => 0,
                            'check_profile' => 'test_only',
                        ],
                    ],
                ],
            ],
        ]);
        $attempt = DelegationAttempt::factory()->succeeded()->create([
            'delegation_task_id' => $task->id,
            'delegatee_profile_id' => $this->profile->id,
        ]);

        $pipeline = new VerificationPipeline;
        $pipeline->execute($task, $attempt);

        Event::assertDispatched(DelegationTaskVerified::class, function ($event) use ($task, $attempt) {
            return $event->task->id === $task->id
                && $event->attempt->id === $attempt->id
                && $event->passed === true;
        });
    }

    public function test_fires_task_verified_failed_event(): void
    {
        Process::fake([
            'php artisan test' => Process::result(output: '', errorOutput: 'Failed', exitCode: 1),
        ]);
        Event::fake([DelegationTaskVerified::class]);

        $task = DelegationTask::factory()->create([
            'delegation_graph_id' => $this->graph->id,
            'status' => DelegationTask::STATUS_VERIFYING,
            'contract_json' => [
                'required_capability' => 'code_execution',
                'prompt' => 'Test task',
                'verification_strategy' => [
                    'steps' => [
                        [
                            'type' => 'automated_check',
                            'step_order' => 0,
                            'check_profile' => 'test_only',
                        ],
                    ],
                ],
            ],
        ]);
        $attempt = DelegationAttempt::factory()->succeeded()->create([
            'delegation_task_id' => $task->id,
            'delegatee_profile_id' => $this->profile->id,
        ]);

        $pipeline = new VerificationPipeline;
        $pipeline->execute($task, $attempt);

        Event::assertDispatched(DelegationTaskVerified::class, function ($event) use ($task) {
            return $event->task->id === $task->id && $event->passed === false;
        });
    }

    public function test_resumes_from_current_step(): void
    {
        Process::fake([
            'php artisan test' => Process::result(output: 'OK', exitCode: 0),
        ]);
        Event::fake([DelegationTaskVerified::class]);

        $task = DelegationTask::factory()->create([
            'delegation_graph_id' => $this->graph->id,
            'status' => DelegationTask::STATUS_VERIFYING,
            'contract_json' => [
                'required_capability' => 'code_execution',
                'prompt' => 'Test task',
                'verification_strategy' => [
                    'steps' => [
                        [
                            'type' => 'human_approval',
                            'step_order' => 0,
                        ],
                        [
                            'type' => 'automated_check',
                            'step_order' => 1,
                            'check_profile' => 'test_only',
                        ],
                    ],
                ],
            ],
            'metadata_json' => [
                'verification_current_step' => 1, // Resume from step 1
            ],
        ]);
        $attempt = DelegationAttempt::factory()->succeeded()->create([
            'delegation_task_id' => $task->id,
            'delegatee_profile_id' => $this->profile->id,
        ]);

        // Create passed result for step 0 (human approval already completed)
        DelegationVerificationResult::factory()->passed()->humanApproval()->create([
            'delegation_task_id' => $task->id,
            'delegation_attempt_id' => $attempt->id,
            'step_order' => 0,
        ]);

        $pipeline = new VerificationPipeline;
        $pipeline->resume($task, $attempt);

        // Only the second step should run
        Process::assertRan('php artisan test');

        // Second step result should be created
        $this->assertDatabaseHas('delegation_verification_results', [
            'delegation_task_id' => $task->id,
            'step_type' => DelegationVerificationResult::STEP_TYPE_AUTOMATED_CHECK,
            'step_order' => 1,
            'verdict' => DelegationVerificationResult::VERDICT_PASSED,
        ]);

        Event::assertDispatched(DelegationTaskVerified::class, function ($event) {
            return $event->passed === true;
        });
    }

    public function test_pauses_on_pending_step(): void
    {
        Process::fake([
            'php artisan test' => Process::result(output: 'OK', exitCode: 0),
        ]);
        Event::fake([DelegationTaskVerified::class]);

        $task = DelegationTask::factory()->create([
            'delegation_graph_id' => $this->graph->id,
            'status' => DelegationTask::STATUS_VERIFYING,
            'contract_json' => [
                'required_capability' => 'code_execution',
                'prompt' => 'Test task',
                'verification_strategy' => [
                    'steps' => [
                        [
                            'type' => 'automated_check',
                            'step_order' => 0,
                            'check_profile' => 'test_only',
                        ],
                        [
                            'type' => 'human_approval',
                            'step_order' => 1,
                        ],
                    ],
                ],
            ],
        ]);
        $attempt = DelegationAttempt::factory()->succeeded()->create([
            'delegation_task_id' => $task->id,
            'delegatee_profile_id' => $this->profile->id,
        ]);

        $pipeline = new VerificationPipeline;
        $pipeline->execute($task, $attempt);

        // Automated check should pass
        $this->assertDatabaseHas('delegation_verification_results', [
            'delegation_task_id' => $task->id,
            'step_type' => DelegationVerificationResult::STEP_TYPE_AUTOMATED_CHECK,
            'verdict' => DelegationVerificationResult::VERDICT_PASSED,
        ]);

        // Human approval should be pending
        $this->assertDatabaseHas('delegation_verification_results', [
            'delegation_task_id' => $task->id,
            'step_type' => DelegationVerificationResult::STEP_TYPE_HUMAN_APPROVAL,
            'verdict' => DelegationVerificationResult::VERDICT_PENDING,
        ]);

        // Should NOT fire verified event yet (waiting for human approval)
        Event::assertNotDispatched(DelegationTaskVerified::class);

        // Current step should be saved in metadata
        $task->refresh();
        $this->assertEquals(1, $task->metadata_json['verification_current_step'] ?? null);
    }

    public function test_handles_multiple_step_types_in_sequence(): void
    {
        Process::fake([
            'php artisan test' => Process::result(output: 'OK', exitCode: 0),
            './vendor/bin/pint --test' => Process::result(output: 'Passed', exitCode: 0),
        ]);
        Event::fake([DelegationTaskVerified::class]);

        $task = DelegationTask::factory()->create([
            'delegation_graph_id' => $this->graph->id,
            'status' => DelegationTask::STATUS_VERIFYING,
            'contract_json' => [
                'required_capability' => 'code_execution',
                'prompt' => 'Test task',
                'verification_strategy' => [
                    'steps' => [
                        [
                            'type' => 'automated_check',
                            'step_order' => 0,
                            'check_profile' => 'laravel_standard',
                        ],
                    ],
                ],
            ],
        ]);
        $attempt = DelegationAttempt::factory()->succeeded()->create([
            'delegation_task_id' => $task->id,
            'delegatee_profile_id' => $this->profile->id,
        ]);

        $pipeline = new VerificationPipeline;
        $pipeline->execute($task, $attempt);

        Process::assertRan('php artisan test');
        Process::assertRan('./vendor/bin/pint --test');

        Event::assertDispatched(DelegationTaskVerified::class, function ($event) {
            return $event->passed === true;
        });
    }
}
