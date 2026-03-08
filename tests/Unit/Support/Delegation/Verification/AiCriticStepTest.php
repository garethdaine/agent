<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Delegation\Verification;

use App\Jobs\AiCriticCompletedJob;
use App\Models\AgentJob;
use App\Models\AgentJobRun;
use App\Models\DelegateeProfile;
use App\Models\DelegationAttempt;
use App\Models\DelegationGraph;
use App\Models\DelegationTask;
use App\Models\DelegationVerificationResult;
use App\Models\User;
use App\Support\Delegation\Verification\AiCriticStep;
use App\Support\Delegation\Verification\VerificationStepResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class AiCriticStepTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private DelegationGraph $graph;

    private DelegationTask $task;

    private DelegationAttempt $attempt;

    protected function setUp(): void
    {
        parent::setUp();

        config(['delegation.ai_critic_default_prompt_template' => 'Review the following output and determine if it meets requirements.']);

        $this->user = User::factory()->create();
        $this->graph = DelegationGraph::factory()->create(['user_id' => $this->user->id]);
        $this->task = DelegationTask::factory()->create([
            'delegation_graph_id' => $this->graph->id,
            'status' => DelegationTask::STATUS_VERIFYING,
            'contract_json' => [
                'required_capability' => 'code_execution',
                'prompt' => 'Test task',
            ],
        ]);
        $profile = DelegateeProfile::factory()->create(['user_id' => $this->user->id]);
        $this->attempt = DelegationAttempt::factory()->succeeded()->create([
            'delegation_task_id' => $this->task->id,
            'delegatee_profile_id' => $profile->id,
        ]);
    }

    public function test_uses_default_prompt_template(): void
    {
        Bus::fake();

        $step = new AiCriticStep;
        $stepConfig = [
            'type' => 'ai_critic',
            'step_order' => 1,
        ];

        $step->execute($this->task, $this->attempt, $stepConfig);

        // Verify an AgentJob was created with the default prompt
        $job = AgentJob::where('name', 'like', '%AI Critic%')->first();
        $this->assertNotNull($job);
        $this->assertStringContainsString(
            'Review the following output',
            $job->task_markdown_path ?: ''
        );
    }

    public function test_uses_per_task_prompt_override(): void
    {
        Bus::fake();

        $step = new AiCriticStep;
        $stepConfig = [
            'type' => 'ai_critic',
            'step_order' => 1,
            'prompt_template' => 'Custom review prompt: Check for security issues.',
        ];

        $step->execute($this->task, $this->attempt, $stepConfig);

        // Verify the custom prompt was used
        $job = AgentJob::where('name', 'like', '%AI Critic%')->first();
        $this->assertNotNull($job);
    }

    public function test_returns_pending_immediately(): void
    {
        Bus::fake();

        $step = new AiCriticStep;
        $stepConfig = [
            'type' => 'ai_critic',
            'step_order' => 1,
        ];

        $result = $step->execute($this->task, $this->attempt, $stepConfig);

        $this->assertInstanceOf(VerificationStepResult::class, $result);
        $this->assertTrue($result->isPending());
    }

    public function test_spawns_review_agent_job_run(): void
    {
        Bus::fake();

        $step = new AiCriticStep;
        $stepConfig = [
            'type' => 'ai_critic',
            'step_order' => 1,
        ];

        $step->execute($this->task, $this->attempt, $stepConfig);

        // Verify AgentJob and AgentJobRun were created
        $this->assertDatabaseHas('agent_jobs', [
            'user_id' => $this->user->id,
        ]);
        $this->assertDatabaseHas('agent_job_runs', [
            'user_id' => $this->user->id,
            'trigger_type' => AgentJobRun::TRIGGER_MANUAL,
            'status' => AgentJobRun::STATUS_QUEUED,
        ]);

        // Verify job chain was dispatched
        Bus::assertChained([
            \App\Jobs\ExecuteAgentRunJob::class,
            AiCriticCompletedJob::class,
        ]);
    }

    public function test_callback_parses_json_evidence(): void
    {
        // Create a pending verification result
        $verificationResult = DelegationVerificationResult::factory()->pending()->aiCritic()->create([
            'delegation_task_id' => $this->task->id,
            'delegation_attempt_id' => $this->attempt->id,
            'step_order' => 1,
        ]);

        // Create the AgentJobRun that the callback will process
        $job = AgentJob::factory()->create(['user_id' => $this->user->id]);
        $run = AgentJobRun::factory()->create([
            'agent_job_id' => $job->id,
            'user_id' => $this->user->id,
            'status' => AgentJobRun::STATUS_SUCCEEDED,
            'metadata_json' => [
                'source' => 'ai_critic',
                'verification_result_id' => $verificationResult->id,
            ],
        ]);

        // Simulate the run output as JSON with expected fields
        $jsonOutput = json_encode([
            'verdict' => 'passed',
            'issues' => [],
            'confidence' => 0.95,
            'reasoning' => 'Code meets all requirements.',
        ]);

        $step = new AiCriticStep;
        $parsedEvidence = $step->parseEvidence($jsonOutput);

        $this->assertArrayHasKey('verdict', $parsedEvidence);
        $this->assertEquals('passed', $parsedEvidence['verdict']);
        $this->assertArrayHasKey('issues', $parsedEvidence);
        $this->assertArrayHasKey('confidence', $parsedEvidence);
    }

    public function test_callback_falls_back_to_raw_text(): void
    {
        $step = new AiCriticStep;

        // Non-JSON output
        $rawOutput = 'The code looks good. VERDICT: PASS. No issues found.';
        $parsedEvidence = $step->parseEvidence($rawOutput);

        $this->assertArrayHasKey('raw_output', $parsedEvidence);
        $this->assertEquals($rawOutput, $parsedEvidence['raw_output']);
    }

    public function test_callback_falls_back_when_json_missing_required_fields(): void
    {
        $step = new AiCriticStep;

        // JSON that doesn't have expected verdict/issues fields
        $jsonOutput = json_encode(['status' => 'ok', 'message' => 'Done']);
        $parsedEvidence = $step->parseEvidence($jsonOutput);

        $this->assertArrayHasKey('raw_output', $parsedEvidence);
    }

    public function test_creates_pending_verification_result(): void
    {
        Bus::fake();

        $step = new AiCriticStep;
        $stepConfig = [
            'type' => 'ai_critic',
            'step_order' => 1,
        ];

        $step->execute($this->task, $this->attempt, $stepConfig);

        $this->assertDatabaseHas('delegation_verification_results', [
            'delegation_task_id' => $this->task->id,
            'delegation_attempt_id' => $this->attempt->id,
            'step_type' => DelegationVerificationResult::STEP_TYPE_AI_CRITIC,
            'step_order' => 1,
            'verdict' => DelegationVerificationResult::VERDICT_PENDING,
        ]);
    }

    public function test_links_agent_job_run_to_verification_result(): void
    {
        Bus::fake();

        $step = new AiCriticStep;
        $stepConfig = [
            'type' => 'ai_critic',
            'step_order' => 1,
        ];

        $step->execute($this->task, $this->attempt, $stepConfig);

        $run = AgentJobRun::latest()->first();
        $this->assertNotNull($run);
        $this->assertEquals('ai_critic', $run->metadata_json['source']);
        $this->assertArrayHasKey('verification_result_id', $run->metadata_json);
    }
}
