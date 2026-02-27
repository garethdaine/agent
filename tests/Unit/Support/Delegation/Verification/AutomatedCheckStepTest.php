<?php

namespace Tests\Unit\Support\Delegation\Verification;

use App\Models\DelegateeProfile;
use App\Models\DelegationAttempt;
use App\Models\DelegationGraph;
use App\Models\DelegationTask;
use App\Models\DelegationVerificationResult;
use App\Models\User;
use App\Support\Delegation\Verification\AutomatedCheckStep;
use App\Support\Delegation\Verification\VerificationStepResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class AutomatedCheckStepTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private DelegationGraph $graph;

    private DelegationTask $task;

    private DelegationAttempt $attempt;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->graph = DelegationGraph::factory()->create(['user_id' => $this->user->id]);
        $this->task = DelegationTask::factory()->create([
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
        $profile = DelegateeProfile::factory()->create(['user_id' => $this->user->id]);
        $this->attempt = DelegationAttempt::factory()->succeeded()->create([
            'delegation_task_id' => $this->task->id,
            'delegatee_profile_id' => $profile->id,
        ]);
    }

    public function test_executes_commands_from_check_profile(): void
    {
        Process::fake([
            'php artisan test' => Process::result(output: 'All tests passed.', exitCode: 0),
        ]);

        $step = new AutomatedCheckStep;
        $stepConfig = [
            'type' => 'automated_check',
            'step_order' => 0,
            'check_profile' => 'test_only',
        ];

        $result = $step->execute($this->task, $this->attempt, $stepConfig);

        Process::assertRan('php artisan test');
        $this->assertInstanceOf(VerificationStepResult::class, $result);
    }

    public function test_captures_stdout_stderr_as_evidence(): void
    {
        Process::fake([
            'php artisan test' => Process::result(
                output: 'Running tests...',
                errorOutput: 'Deprecation warning',
                exitCode: 0
            ),
        ]);

        $step = new AutomatedCheckStep;
        $stepConfig = [
            'type' => 'automated_check',
            'step_order' => 0,
            'check_profile' => 'test_only',
        ];

        $result = $step->execute($this->task, $this->attempt, $stepConfig);

        $this->assertTrue($result->isPassed());
        $this->assertArrayHasKey('commands', $result->evidence);
        $this->assertStringContainsString('Running tests...', $result->evidence['commands'][0]['stdout']);
        $this->assertStringContainsString('Deprecation warning', $result->evidence['commands'][0]['stderr']);
    }

    public function test_returns_failed_on_nonzero_exit(): void
    {
        Process::fake([
            'php artisan test' => Process::result(
                output: '',
                errorOutput: 'Tests failed!',
                exitCode: 1
            ),
        ]);

        $step = new AutomatedCheckStep;
        $stepConfig = [
            'type' => 'automated_check',
            'step_order' => 0,
            'check_profile' => 'test_only',
        ];

        $result = $step->execute($this->task, $this->attempt, $stepConfig);

        $this->assertTrue($result->isFailed());
        $this->assertEquals(1, $result->evidence['commands'][0]['exit_code']);
    }

    public function test_returns_passed_when_all_commands_succeed(): void
    {
        Process::fake([
            'php artisan test' => Process::result(output: 'OK', exitCode: 0),
            './vendor/bin/pint --test' => Process::result(output: 'All files passed.', exitCode: 0),
        ]);

        $step = new AutomatedCheckStep;
        $stepConfig = [
            'type' => 'automated_check',
            'step_order' => 0,
            'check_profile' => 'laravel_standard',
        ];

        $result = $step->execute($this->task, $this->attempt, $stepConfig);

        $this->assertTrue($result->isPassed());
        $this->assertCount(2, $result->evidence['commands']);
    }

    public function test_creates_verification_result_record(): void
    {
        Process::fake([
            'php artisan test' => Process::result(output: 'OK', exitCode: 0),
        ]);

        $step = new AutomatedCheckStep;
        $stepConfig = [
            'type' => 'automated_check',
            'step_order' => 0,
            'check_profile' => 'test_only',
        ];

        $step->execute($this->task, $this->attempt, $stepConfig);

        $this->assertDatabaseHas('delegation_verification_results', [
            'delegation_task_id' => $this->task->id,
            'delegation_attempt_id' => $this->attempt->id,
            'step_type' => DelegationVerificationResult::STEP_TYPE_AUTOMATED_CHECK,
            'step_order' => 0,
            'verdict' => DelegationVerificationResult::VERDICT_PASSED,
        ]);
    }

    public function test_returns_failed_for_unknown_check_profile(): void
    {
        $step = new AutomatedCheckStep;
        $stepConfig = [
            'type' => 'automated_check',
            'step_order' => 0,
            'check_profile' => 'nonexistent_profile',
        ];

        $result = $step->execute($this->task, $this->attempt, $stepConfig);

        $this->assertTrue($result->isFailed());
        $this->assertStringContainsString('Unknown check profile', $result->evidence['error']);
    }

    public function test_short_circuits_on_first_command_failure(): void
    {
        Process::fake([
            'php artisan test' => Process::result(output: '', errorOutput: 'Failed', exitCode: 1),
            './vendor/bin/pint --test' => Process::result(output: 'Passed', exitCode: 0),
        ]);

        $step = new AutomatedCheckStep;
        $stepConfig = [
            'type' => 'automated_check',
            'step_order' => 0,
            'check_profile' => 'laravel_standard',
        ];

        $result = $step->execute($this->task, $this->attempt, $stepConfig);

        $this->assertTrue($result->isFailed());
        // Only the first command should have been executed
        Process::assertRan('php artisan test');
        Process::assertNotRan('./vendor/bin/pint --test');
    }

    public function test_uses_task_working_directory(): void
    {
        $this->task->update([
            'metadata_json' => ['working_directory' => '/custom/path'],
        ]);

        Process::fake([
            '*' => Process::result(output: 'OK', exitCode: 0),
        ]);

        $step = new AutomatedCheckStep;
        $stepConfig = [
            'type' => 'automated_check',
            'step_order' => 0,
            'check_profile' => 'test_only',
        ];

        $step->execute($this->task, $this->attempt, $stepConfig);

        Process::assertRan(function ($process) {
            return $process->command === 'php artisan test'
                && $process->path === '/custom/path';
        });
    }
}
