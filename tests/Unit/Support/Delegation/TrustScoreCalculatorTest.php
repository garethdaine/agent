<?php

namespace Tests\Unit\Support\Delegation;

use App\Models\AgentJob;
use App\Models\AgentJobRun;
use App\Models\User;
use App\Support\Delegation\TrustScoreCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TrustScoreCalculatorTest extends TestCase
{
    use RefreshDatabase;

    private TrustScoreCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new TrustScoreCalculator;
    }

    public function test_calculate_returns_score_within_bounds(): void
    {
        $score = $this->calculator->calculate('claude');

        $this->assertGreaterThanOrEqual(0.0, $score->score);
        $this->assertLessThanOrEqual(1.0, $score->score);
    }

    public function test_calculate_returns_default_for_cold_start(): void
    {
        $score = $this->calculator->calculate('new_runner_type');

        $this->assertEquals(0.5, $score->score);
        $this->assertEquals('default', $score->source);
    }

    public function test_calculate_uses_job_level_metrics_when_available(): void
    {
        config(['agent.trust.min_job_runs' => 2]);

        $job = AgentJob::factory()->create(['runner_type' => 'claude']);

        // Create runs with STAR data
        for ($i = 0; $i < 3; $i++) {
            AgentJobRun::factory()->create([
                'agent_job_id' => $job->id,
                'user_id' => $job->user_id,
                'status' => AgentJobRun::STATUS_SUCCEEDED,
                'metadata_json' => [
                    'reasoning_summary' => [
                        'all_completed' => true,
                        'steps' => [
                            'task' => ['completed' => true],
                        ],
                    ],
                ],
            ]);
        }

        $score = $this->calculator->calculate('claude', $job->id);

        $this->assertEquals('job', $score->source);
    }

    public function test_calculate_falls_back_to_runner_type_metrics(): void
    {
        config(['agent.trust.min_job_runs' => 10]);

        $job = AgentJob::factory()->create(['runner_type' => 'claude']);

        // Only 2 runs for this job (below threshold)
        for ($i = 0; $i < 2; $i++) {
            AgentJobRun::factory()->create([
                'agent_job_id' => $job->id,
                'user_id' => $job->user_id,
                'status' => AgentJobRun::STATUS_SUCCEEDED,
                'metadata_json' => ['reasoning_summary' => ['all_completed' => true]],
            ]);
        }

        // More runs for runner_type overall
        $otherJob = AgentJob::factory()->create(['runner_type' => 'claude']);
        for ($i = 0; $i < 15; $i++) {
            AgentJobRun::factory()->create([
                'agent_job_id' => $otherJob->id,
                'user_id' => $otherJob->user_id,
                'status' => AgentJobRun::STATUS_SUCCEEDED,
                'metadata_json' => ['reasoning_summary' => ['all_completed' => true]],
            ]);
        }

        $score = $this->calculator->calculate('claude', $job->id);

        $this->assertEquals('runner_type', $score->source);
    }

    public function test_confidence_reflects_sample_size(): void
    {
        $job = AgentJob::factory()->create(['runner_type' => 'claude']);

        // Few runs = low confidence
        for ($i = 0; $i < 5; $i++) {
            AgentJobRun::factory()->create([
                'agent_job_id' => $job->id,
                'user_id' => $job->user_id,
                'status' => AgentJobRun::STATUS_SUCCEEDED,
                'metadata_json' => ['reasoning_summary' => ['all_completed' => true]],
            ]);
        }

        $score = $this->calculator->calculate('claude');

        $this->assertEquals('low', $score->confidence);
    }

    #[Test]
    public function it_calculates_star_metrics_from_run_history(): void
    {
        $user = User::factory()->create();
        $job = AgentJob::factory()->create(['user_id' => $user->id]);

        // 8 successful, 2 failed = 80% success
        AgentJobRun::factory()->count(8)->create([
            'agent_job_id' => $job->id,
            'user_id' => $user->id,
            'status' => AgentJobRun::STATUS_SUCCEEDED,
            'metadata_json' => ['reasoning_summary' => ['all_completed' => true]],
        ]);
        AgentJobRun::factory()->count(2)->create([
            'agent_job_id' => $job->id,
            'user_id' => $user->id,
            'status' => AgentJobRun::STATUS_FAILED,
            'metadata_json' => ['reasoning_summary' => ['all_completed' => false]],
        ]);

        $calculator = app(TrustScoreCalculator::class);
        $metrics = $calculator->calculateForUser($user);

        $this->assertEqualsWithDelta(0.8, $metrics->firstPassSuccessRate, 0.01);
    }

    #[Test]
    public function it_returns_zero_metrics_for_user_with_no_runs(): void
    {
        $user = User::factory()->create();

        $calculator = app(TrustScoreCalculator::class);
        $metrics = $calculator->calculateForUser($user);

        $this->assertEquals(0, $metrics->sampleSize);
        $this->assertEquals(0, $metrics->firstPassSuccessRate);
    }

    #[Test]
    public function it_extracts_star_component_correctness_from_metadata(): void
    {
        $user = User::factory()->create();
        $job = AgentJob::factory()->create(['user_id' => $user->id]);

        AgentJobRun::factory()->create([
            'agent_job_id' => $job->id,
            'user_id' => $user->id,
            'status' => AgentJobRun::STATUS_SUCCEEDED,
            'metadata_json' => [
                'reasoning_summary' => [
                    'all_completed' => true,
                    'situation_correct' => true,
                    'task_correct' => true,
                    'action_correct' => false,
                    'result_correct' => true,
                ],
            ],
        ]);

        $calculator = app(TrustScoreCalculator::class);
        $metrics = $calculator->calculateForUser($user);

        $this->assertEquals(1.0, $metrics->situationCorrectRate);
        $this->assertEquals(0.0, $metrics->actionCorrectRate);
    }
}
