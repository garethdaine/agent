<?php

namespace Tests\Unit\Support\Agent;

use App\Jobs\ExecuteAgentRunJob;
use App\Models\AgentJob;
use App\Models\AgentJobRun;
use App\Models\InterrogationBuildTask;
use App\Models\InterrogationSession;
use App\Models\User;
use App\Support\Agent\TargetedRetryService;
use App\Support\Agent\UsageLimitState;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class TargetedRetryServiceTest extends TestCase
{
    use RefreshDatabase;

    private TargetedRetryService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(TargetedRetryService::class);
    }

    public function test_should_retry_returns_true_for_star_structured_failure(): void
    {
        config(['agent.targeted_retry.enabled' => true]);
        config(['agent.targeted_retry.max_retries' => 3]);

        $job = AgentJob::factory()->create(['targeted_retry_enabled' => true]);
        $run = AgentJobRun::factory()->for($job, 'job')->create([
            'status' => 'failed',
            'metadata_json' => [
                'reasoning_summary' => [
                    'steps' => [
                        'situation' => ['completed' => true, 'content' => 'State'],
                        'task' => ['completed' => true, 'content' => 'Goal'],
                    ],
                ],
            ],
        ]);

        $this->assertTrue($this->service->shouldRetry($run));
    }

    public function test_should_retry_returns_false_for_unstructured_failure(): void
    {
        config(['agent.targeted_retry.enabled' => true]);

        $job = AgentJob::factory()->create();
        $run = AgentJobRun::factory()->for($job, 'job')->create([
            'status' => 'failed',
            'metadata_json' => [], // No reasoning_summary
        ]);

        $this->assertFalse($this->service->shouldRetry($run));
    }

    public function test_should_retry_respects_max_retry_count(): void
    {
        config(['agent.targeted_retry.enabled' => true]);
        config(['agent.targeted_retry.max_retries' => 1]);

        $job = AgentJob::factory()->create();
        $run = AgentJobRun::factory()->for($job, 'job')->create([
            'status' => 'failed',
            'metadata_json' => [
                'reasoning_summary' => ['steps' => ['task' => ['completed' => true]]],
                'retry_count' => 1, // Already retried once
            ],
        ]);

        $this->assertFalse($this->service->shouldRetry($run));
    }

    public function test_should_retry_returns_false_when_globally_disabled(): void
    {
        config(['agent.targeted_retry.enabled' => false]);

        $job = AgentJob::factory()->create(['targeted_retry_enabled' => null]);
        $run = AgentJobRun::factory()->for($job, 'job')->create([
            'status' => 'failed',
            'metadata_json' => [
                'reasoning_summary' => [
                    'steps' => [
                        'task' => ['completed' => true, 'content' => 'Goal'],
                    ],
                ],
            ],
        ]);

        $this->assertFalse($this->service->shouldRetry($run));
    }

    public function test_should_retry_per_job_override_takes_precedence(): void
    {
        config(['agent.targeted_retry.enabled' => false]);
        config(['agent.targeted_retry.max_retries' => 3]);

        $job = AgentJob::factory()->create(['targeted_retry_enabled' => true]);
        $run = AgentJobRun::factory()->for($job, 'job')->create([
            'status' => 'failed',
            'metadata_json' => [
                'reasoning_summary' => [
                    'steps' => [
                        'task' => ['completed' => true, 'content' => 'Goal'],
                    ],
                ],
            ],
        ]);

        $this->assertTrue($this->service->shouldRetry($run));
    }

    public function test_should_retry_respects_rate_limit_hold(): void
    {
        config(['agent.targeted_retry.enabled' => true]);
        config(['agent.targeted_retry.max_retries' => 3]);

        $job = AgentJob::factory()->create(['targeted_retry_enabled' => true]);
        $run = AgentJobRun::factory()->for($job, 'job')->create([
            'status' => 'failed',
            'metadata_json' => [
                'reasoning_summary' => [
                    'steps' => [
                        'task' => ['completed' => true, 'content' => 'Goal'],
                    ],
                ],
            ],
        ]);

        // Set a rate-limit hold
        $usageLimitState = app(UsageLimitState::class);
        $usageLimitState->upsertHold((int) $job->id, CarbonImmutable::now('UTC')->addMinutes(15));

        $this->assertFalse($this->service->shouldRetry($run));
    }

    public function test_should_retry_returns_false_for_non_failed_status(): void
    {
        config(['agent.targeted_retry.enabled' => true]);

        $job = AgentJob::factory()->create(['targeted_retry_enabled' => true]);
        $run = AgentJobRun::factory()->for($job, 'job')->create([
            'status' => 'succeeded',
            'metadata_json' => [
                'reasoning_summary' => [
                    'steps' => [
                        'task' => ['completed' => true, 'content' => 'Goal'],
                    ],
                ],
            ],
        ]);

        $this->assertFalse($this->service->shouldRetry($run));
    }

    public function test_build_retry_prompt_includes_original_situation(): void
    {
        $job = AgentJob::factory()->create();
        $run = AgentJobRun::factory()->for($job, 'job')->create([
            'metadata_json' => [
                'reasoning_summary' => [
                    'steps' => [
                        'situation' => ['content' => 'The codebase has existing tests'],
                        'task' => ['content' => 'Optimize performance'],
                    ],
                ],
                'failure_mode_hint' => [
                    'type' => 1,
                    'description' => 'TASK missing subject',
                ],
            ],
        ]);

        $prompt = $this->service->buildRetryPrompt($run);

        $this->assertStringContainsString('The codebase has existing tests', $prompt);
        $this->assertStringContainsString('Retry: Correcting Previous Attempt', $prompt);
    }

    public function test_build_retry_prompt_references_failed_task_step(): void
    {
        $job = AgentJob::factory()->create();
        $run = AgentJobRun::factory()->for($job, 'job')->create([
            'metadata_json' => [
                'reasoning_summary' => [
                    'steps' => [
                        'situation' => ['content' => 'Context here'],
                        'task' => ['content' => 'Optimize the API endpoint'],
                    ],
                ],
                'failure_mode_hint' => [
                    'type' => 2,
                    'description' => 'ACTION contradicts TASK',
                ],
            ],
        ]);

        $prompt = $this->service->buildRetryPrompt($run);

        $this->assertStringContainsString('Optimize the API endpoint', $prompt);
        $this->assertStringContainsString('ACTION contradicts TASK', $prompt);
    }

    public function test_build_retry_prompt_uses_custom_prompt_when_provided(): void
    {
        $job = AgentJob::factory()->create();
        $run = AgentJobRun::factory()->for($job, 'job')->create([
            'metadata_json' => [
                'reasoning_summary' => [
                    'steps' => [
                        'task' => ['content' => 'Original task'],
                    ],
                ],
            ],
        ]);

        $customPrompt = 'Custom retry instructions here.';
        $prompt = $this->service->buildRetryPrompt($run, $customPrompt);

        $this->assertSame($customPrompt, $prompt);
    }

    public function test_build_retry_prompt_handles_missing_steps_gracefully(): void
    {
        $job = AgentJob::factory()->create();
        $run = AgentJobRun::factory()->for($job, 'job')->create([
            'metadata_json' => [
                'reasoning_summary' => [
                    'steps' => [],
                ],
            ],
        ]);

        $prompt = $this->service->buildRetryPrompt($run);

        $this->assertStringContainsString('Not captured', $prompt);
        $this->assertStringContainsString('Retry: Correcting Previous Attempt', $prompt);
    }

    public function test_dispatch_retry_creates_new_run_with_correct_metadata(): void
    {
        Queue::fake();
        config(['agent.targeted_retry.enabled' => true]);

        $job = AgentJob::factory()->create(['targeted_retry_enabled' => true]);
        $failedRun = AgentJobRun::factory()->for($job, 'job')->create([
            'status' => 'failed',
            'metadata_json' => [
                'reasoning_summary' => [
                    'steps' => [
                        'situation' => ['content' => 'State info'],
                        'task' => ['content' => 'Task goal'],
                    ],
                ],
                'retry_count' => 0,
            ],
        ]);

        $retryRun = $this->service->dispatchRetry($failedRun);

        $this->assertNotEquals($failedRun->id, $retryRun->id);
        $this->assertEquals($job->id, $retryRun->agent_job_id);
        $this->assertEquals('queued', $retryRun->status);

        $metadata = $retryRun->metadata_json;
        $this->assertEquals($failedRun->id, $metadata['retry_of_run_id']);
        $this->assertEquals(1, $metadata['retry_count']);
        $this->assertArrayHasKey('retry_prompt', $metadata);

        Queue::assertPushed(ExecuteAgentRunJob::class, function ($job) use ($retryRun) {
            return $job->runId === $retryRun->id;
        });
    }

    public function test_dispatch_retry_increments_retry_count(): void
    {
        Queue::fake();
        config(['agent.targeted_retry.enabled' => true]);

        $job = AgentJob::factory()->create(['targeted_retry_enabled' => true]);
        $failedRun = AgentJobRun::factory()->for($job, 'job')->create([
            'status' => 'failed',
            'metadata_json' => [
                'reasoning_summary' => [
                    'steps' => [
                        'task' => ['content' => 'Goal'],
                    ],
                ],
                'retry_count' => 2,
            ],
        ]);

        $retryRun = $this->service->dispatchRetry($failedRun);

        $this->assertEquals(3, $retryRun->metadata_json['retry_count']);
    }

    public function test_dispatch_retry_uses_custom_prompt_when_provided(): void
    {
        Queue::fake();
        config(['agent.targeted_retry.enabled' => true]);

        $job = AgentJob::factory()->create(['targeted_retry_enabled' => true]);
        $failedRun = AgentJobRun::factory()->for($job, 'job')->create([
            'status' => 'failed',
            'metadata_json' => [
                'reasoning_summary' => [
                    'steps' => [
                        'task' => ['content' => 'Goal'],
                    ],
                ],
            ],
        ]);

        $customPrompt = 'My custom retry prompt';
        $retryRun = $this->service->dispatchRetry($failedRun, $customPrompt);

        $this->assertEquals($customPrompt, $retryRun->metadata_json['retry_prompt']);
    }

    public function test_should_retry_uses_per_job_max_retries_when_set(): void
    {
        config(['agent.targeted_retry.enabled' => true]);
        config(['agent.targeted_retry.max_retries' => 5]);

        $job = AgentJob::factory()->create([
            'targeted_retry_enabled' => true,
            'max_retries' => 2,
        ]);
        $run = AgentJobRun::factory()->for($job, 'job')->create([
            'status' => 'failed',
            'metadata_json' => [
                'reasoning_summary' => [
                    'steps' => ['task' => ['completed' => true]],
                ],
                'retry_count' => 2, // At job-level max
            ],
        ]);

        $this->assertFalse($this->service->shouldRetry($run));
    }

    public function test_correction_guidance_varies_by_failure_type(): void
    {
        $job = AgentJob::factory()->create();

        // Type 1 failure
        $run1 = AgentJobRun::factory()->for($job, 'job')->create([
            'metadata_json' => [
                'reasoning_summary' => [
                    'steps' => [
                        'situation' => ['content' => 'Context'],
                        'task' => ['content' => 'Task'],
                    ],
                ],
                'failure_mode_hint' => ['type' => 1, 'description' => 'Type 1 issue'],
            ],
        ]);

        $prompt1 = $this->service->buildRetryPrompt($run1);
        $this->assertStringContainsString('Focus on the actual subject', $prompt1);

        // Type 2 failure
        $run2 = AgentJobRun::factory()->for($job, 'job')->create([
            'metadata_json' => [
                'reasoning_summary' => [
                    'steps' => [
                        'situation' => ['content' => 'Context'],
                        'task' => ['content' => 'Task'],
                    ],
                ],
                'failure_mode_hint' => ['type' => 2, 'description' => 'Type 2 issue'],
            ],
        ]);

        $prompt2 = $this->service->buildRetryPrompt($run2);
        $this->assertStringContainsString('Keep ACTION aligned with TASK', $prompt2);

        // Type 3 failure
        $run3 = AgentJobRun::factory()->for($job, 'job')->create([
            'metadata_json' => [
                'reasoning_summary' => [
                    'steps' => [
                        'situation' => ['content' => 'Context'],
                        'task' => ['content' => 'Task'],
                    ],
                ],
                'failure_mode_hint' => ['type' => 3, 'description' => 'Type 3 issue'],
            ],
        ]);

        $prompt3 = $this->service->buildRetryPrompt($run3);
        $this->assertStringContainsString('Ensure each ACTION step directly advances', $prompt3);
    }

    public function test_dispatch_retry_carries_forward_build_metadata_and_updates_task_pointer(): void
    {
        Queue::fake();
        config(['agent.targeted_retry.enabled' => true]);

        $user = User::factory()->withPersonalTeam()->create();
        $session = InterrogationSession::query()->create([
            'user_id' => $user->id,
            'name' => 'Build session',
            'runner_type' => 'claude',
            'project_directory' => base_path(),
            'interrogation_type' => InterrogationSession::TYPE_FEATURE,
            'status' => InterrogationSession::STATUS_COMPLETED,
            'phase' => InterrogationSession::PHASE_BUILD_EXECUTION,
            'metadata_json' => ['build' => ['status' => 'running']],
        ]);

        $job = AgentJob::factory()->create(['targeted_retry_enabled' => true]);
        $failedRun = AgentJobRun::factory()->for($job, 'job')->create([
            'status' => 'failed',
            'metadata_json' => [
                'reasoning_summary' => [
                    'steps' => [
                        'situation' => ['content' => 'Context'],
                        'task' => ['content' => 'Task goal'],
                    ],
                ],
                'retry_count' => 0,
                'interrogation_session_id' => $session->id,
                'interrogation_build_task_id' => 0, // placeholder, updated below
                'source' => 'interrogation_build',
            ],
        ]);

        $buildTask = InterrogationBuildTask::query()->create([
            'interrogation_session_id' => $session->id,
            'sequence' => 1,
            'title' => 'Task under retry',
            'status' => InterrogationBuildTask::STATUS_IN_PROGRESS,
            'attempt_count' => 1,
            'agent_job_run_id' => $failedRun->id,
        ]);

        // Update the failed run metadata with the real build task ID
        $meta = $failedRun->metadata_json;
        $meta['interrogation_build_task_id'] = $buildTask->id;
        $failedRun->metadata_json = $meta;
        $failedRun->save();

        $retryRun = $this->service->dispatchRetry($failedRun);

        // Build-context keys must be carried forward
        $retryMeta = $retryRun->metadata_json;
        $this->assertEquals($session->id, $retryMeta['interrogation_session_id']);
        $this->assertEquals($buildTask->id, $retryMeta['interrogation_build_task_id']);
        $this->assertEquals('interrogation_build', $retryMeta['source']);

        // Build task pointer must point to the new retry run
        $buildTask->refresh();
        $this->assertEquals($retryRun->id, $buildTask->agent_job_run_id);
    }
}
