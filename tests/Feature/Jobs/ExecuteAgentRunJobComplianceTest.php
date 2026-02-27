<?php

namespace Tests\Feature\Jobs;

use App\Contracts\OrchestrationPolicyServiceContract;
use App\Enums\TaskCategory;
use App\Jobs\ExecuteAgentRunJob;
use App\Models\AgentJob;
use App\Models\AgentJobRun;
use App\Models\User;
use App\Support\Compliance\DTOs\CompletionGateResult;
use App\Support\Compliance\DTOs\GateResult;
use App\Support\Compliance\DTOs\PolicyEvaluationResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

/**
 * Tests for compliance policy integration in ExecuteAgentRunJob.
 *
 * Conditions for correctness:
 * - OrchestrationPolicyServiceContract must be properly bound in the container
 * - The job must support dependency injection of the policy service
 * - Compliance metadata must be merged correctly without overwriting existing fields
 *
 * Known limitations:
 * - These tests use mocked policy service; integration tests with real service are separate
 * - Process execution is simulated with shell scripts
 */
class ExecuteAgentRunJobComplianceTest extends TestCase
{
    use RefreshDatabase;

    private string $sandboxBase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sandboxBase = storage_path('framework/testing/agent-runner-compliance');
        @mkdir($this->sandboxBase.'/bin', 0777, true);
        @mkdir($this->sandboxBase.'/tasks', 0777, true);
        @mkdir($this->sandboxBase.'/work', 0777, true);

        $exec = $this->sandboxBase.'/bin/runner';
        file_put_contents($exec, "#!/bin/sh\nexit 0\n");
        chmod($exec, 0755);

        config()->set('agent.allowed_task_markdown_bases', [$this->sandboxBase.'/tasks']);
        config()->set('agent.allowed_working_directory_bases', [$this->sandboxBase.'/work']);
        config()->set('agent.runner_executables', [
            'claude' => $exec,
            'codex' => $exec,
            'custom' => $exec,
        ]);
        config()->set('agent.default_templates', [
            'claude' => $exec.' -p {{task_markdown_path}}',
            'codex' => $exec.' exec {{task_markdown_path}}',
        ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_run_proceeds_normally_when_compliance_disabled(): void
    {
        config()->set('agent.compliance.enabled', false);

        $user = User::factory()->create();
        $taskFile = $this->sandboxBase.'/tasks/compliance-disabled.md';
        file_put_contents($taskFile, "# Task\n");

        $job = $this->createAgentJob($user, $taskFile);
        $run = $this->createAgentJobRun($job, $user);

        $this->runExecuteAgentRunJob($run->id);

        $run->refresh();

        $this->assertSame(AgentJobRun::STATUS_SUCCEEDED, $run->status);
        // When compliance is disabled, no compliance metadata should be added
        $metadata = (array) ($run->metadata_json ?? []);
        $this->assertArrayNotHasKey('workflow_policy_version', $metadata);
    }

    public function test_compliance_metadata_stored_in_run_when_enabled(): void
    {
        config()->set('agent.compliance.enabled', true);
        config()->set('agent.compliance.enforcement_mode', 'advisory');

        $user = User::factory()->create();
        $taskFile = $this->sandboxBase.'/tasks/compliance-metadata.md';
        file_put_contents($taskFile, "# Task\n");

        $job = $this->createAgentJob($user, $taskFile);
        $run = $this->createAgentJobRun($job, $user, [
            'task_category' => 'feature',
        ]);

        $this->runExecuteAgentRunJob($run->id);

        $run->refresh();
        $metadata = (array) ($run->metadata_json ?? []);

        $this->assertSame(AgentJobRun::STATUS_SUCCEEDED, $run->status);
        $this->assertArrayHasKey('workflow_policy_version', $metadata);
        $this->assertArrayHasKey('complexity_classification', $metadata);
        $this->assertArrayHasKey('task_category', $metadata);
        $this->assertArrayHasKey('plan_required', $metadata);
        $this->assertArrayHasKey('verification_required', $metadata);
    }

    public function test_run_fails_with_compliance_blocked_when_strict_and_blocked(): void
    {
        config()->set('agent.compliance.enabled', true);
        config()->set('agent.compliance.enforcement_mode', 'strict');

        $mockPolicyService = Mockery::mock(OrchestrationPolicyServiceContract::class);
        $mockPolicyService->shouldReceive('isEnabled')->andReturn(true);
        $mockPolicyService->shouldReceive('getEnforcementMode')->andReturn('strict');
        $mockPolicyService->shouldReceive('evaluatePreRun')->andReturn(
            new PolicyEvaluationResult(
                status: 'blocked',
                complexity: 'non_trivial',
                taskCategory: TaskCategory::FEATURE,
                planRequired: true,
                verificationRequired: true,
                eleganceRequired: false,
                gates: [
                    new GateResult(
                        gate: 'plan',
                        status: 'blocked',
                        reasonCode: 'missing_plan_evidence',
                        remediation: 'Provide plan evidence before execution.'
                    ),
                ],
                metadataPatch: [
                    'workflow_policy_version' => '1.0',
                    'complexity_classification' => 'non_trivial',
                    'task_category' => 'feature',
                    'plan_required' => true,
                    'verification_required' => true,
                ]
            )
        );

        $this->app->instance(OrchestrationPolicyServiceContract::class, $mockPolicyService);

        $user = User::factory()->create();
        $taskFile = $this->sandboxBase.'/tasks/compliance-blocked.md';
        file_put_contents($taskFile, "# Task\n");

        $job = $this->createAgentJob($user, $taskFile);
        $run = $this->createAgentJobRun($job, $user);

        $this->runExecuteAgentRunJob($run->id);

        $run->refresh();
        $metadata = (array) ($run->metadata_json ?? []);

        $this->assertSame(AgentJobRun::STATUS_FAILED, $run->status);
        $this->assertSame('COMPLIANCE_BLOCKED', $run->error_code);
        $this->assertStringContainsString('Pre-run compliance check failed', $run->error_summary);
        $this->assertSame('missing_plan_evidence', $metadata['compliance_block_reason'] ?? null);
    }

    public function test_run_proceeds_with_advisory_logged_when_advisory_mode(): void
    {
        config()->set('agent.compliance.enabled', true);
        config()->set('agent.compliance.enforcement_mode', 'advisory');

        $mockPolicyService = Mockery::mock(OrchestrationPolicyServiceContract::class);
        $mockPolicyService->shouldReceive('isEnabled')->andReturn(true);
        $mockPolicyService->shouldReceive('getEnforcementMode')->andReturn('advisory');
        $mockPolicyService->shouldReceive('evaluatePreRun')->andReturn(
            new PolicyEvaluationResult(
                status: 'advisory',
                complexity: 'non_trivial',
                taskCategory: TaskCategory::FEATURE,
                planRequired: true,
                verificationRequired: true,
                eleganceRequired: false,
                gates: [
                    new GateResult(
                        gate: 'plan',
                        status: 'advisory',
                        reasonCode: 'missing_plan_evidence',
                        remediation: 'Consider providing plan evidence.'
                    ),
                ],
                metadataPatch: [
                    'workflow_policy_version' => '1.0',
                    'complexity_classification' => 'non_trivial',
                    'task_category' => 'feature',
                    'plan_required' => true,
                    'verification_required' => true,
                ]
            )
        );
        $mockPolicyService->shouldReceive('evaluateCompletion')->andReturn(
            new CompletionGateResult(
                canComplete: true,
                status: 'pass',
                gates: []
            )
        );

        $this->app->instance(OrchestrationPolicyServiceContract::class, $mockPolicyService);

        $user = User::factory()->create();
        $taskFile = $this->sandboxBase.'/tasks/compliance-advisory.md';
        file_put_contents($taskFile, "# Task\n");

        $job = $this->createAgentJob($user, $taskFile);
        $run = $this->createAgentJobRun($job, $user);

        $this->runExecuteAgentRunJob($run->id);

        $run->refresh();
        $metadata = (array) ($run->metadata_json ?? []);

        // Run should succeed despite advisory warning
        $this->assertSame(AgentJobRun::STATUS_SUCCEEDED, $run->status);
        $this->assertNull($run->error_code);
        // Compliance metadata should still be recorded
        $this->assertSame('1.0', $metadata['workflow_policy_version'] ?? null);
        $this->assertSame('non_trivial', $metadata['complexity_classification'] ?? null);
    }

    public function test_completion_gate_blocks_success_when_verification_missing_in_strict_mode(): void
    {
        config()->set('agent.compliance.enabled', true);
        config()->set('agent.compliance.enforcement_mode', 'strict');

        $mockPolicyService = Mockery::mock(OrchestrationPolicyServiceContract::class);
        $mockPolicyService->shouldReceive('isEnabled')->andReturn(true);
        $mockPolicyService->shouldReceive('getEnforcementMode')->andReturn('strict');
        $mockPolicyService->shouldReceive('evaluatePreRun')->andReturn(
            new PolicyEvaluationResult(
                status: 'pass',
                complexity: 'non_trivial',
                taskCategory: TaskCategory::FEATURE,
                planRequired: true,
                verificationRequired: true,
                eleganceRequired: false,
                gates: [],
                metadataPatch: [
                    'workflow_policy_version' => '1.0',
                    'complexity_classification' => 'non_trivial',
                    'task_category' => 'feature',
                    'plan_required' => true,
                    'verification_required' => true,
                ]
            )
        );
        $mockPolicyService->shouldReceive('evaluateCompletion')->andReturn(
            new CompletionGateResult(
                canComplete: false,
                status: 'blocked',
                gates: [
                    new GateResult(
                        gate: 'verification',
                        status: 'blocked',
                        reasonCode: 'missing_automated_check',
                        remediation: 'Run tests and capture output as verification evidence.'
                    ),
                ],
                blockReason: 'verification_incomplete',
                remediation: 'Run tests and capture output as verification evidence.'
            )
        );

        $this->app->instance(OrchestrationPolicyServiceContract::class, $mockPolicyService);

        $user = User::factory()->create();
        $taskFile = $this->sandboxBase.'/tasks/compliance-completion-blocked.md';
        file_put_contents($taskFile, "# Task\n");

        $job = $this->createAgentJob($user, $taskFile);
        $run = $this->createAgentJobRun($job, $user);

        $this->runExecuteAgentRunJob($run->id);

        $run->refresh();
        $metadata = (array) ($run->metadata_json ?? []);

        $this->assertSame(AgentJobRun::STATUS_FAILED, $run->status);
        $this->assertSame('COMPLIANCE_BLOCKED', $run->error_code);
        // When remediation is provided, it's used as the error summary
        $this->assertStringContainsString('verification evidence', $run->error_summary);
        $this->assertSame('verification_incomplete', $metadata['compliance_block_reason'] ?? null);
    }

    public function test_existing_metadata_preserved_when_compliance_metadata_added(): void
    {
        config()->set('agent.compliance.enabled', true);
        config()->set('agent.compliance.enforcement_mode', 'advisory');

        $user = User::factory()->create();
        $taskFile = $this->sandboxBase.'/tasks/compliance-preserve-metadata.md';
        file_put_contents($taskFile, "# Task\n");

        $job = $this->createAgentJob($user, $taskFile);
        $run = $this->createAgentJobRun($job, $user, [
            'custom_field' => 'custom_value',
            'output_truncated' => false,
        ]);

        $this->runExecuteAgentRunJob($run->id);

        $run->refresh();
        $metadata = (array) ($run->metadata_json ?? []);

        $this->assertSame(AgentJobRun::STATUS_SUCCEEDED, $run->status);
        // Original metadata should be preserved
        $this->assertSame('custom_value', $metadata['custom_field'] ?? null);
        $this->assertFalse($metadata['output_truncated'] ?? true);
        // Compliance metadata should be added
        $this->assertArrayHasKey('workflow_policy_version', $metadata);
    }

    private function createAgentJob(User $user, string $taskFile): AgentJob
    {
        return AgentJob::query()->create([
            'user_id' => $user->id,
            'name' => 'Compliance Test Job',
            'description' => null,
            'cron_expression' => '0 0 1 1 1',
            'timezone' => 'UTC',
            'is_enabled' => true,
            'max_runtime_seconds' => 60,
            'cooldown_seconds' => 0,
            'runner_type' => 'claude',
            'command_template' => config('agent.default_templates.claude'),
            'task_markdown_path' => $taskFile,
            'working_directory' => $this->sandboxBase.'/work',
        ]);
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function createAgentJobRun(AgentJob $job, User $user, array $metadata = []): AgentJobRun
    {
        return AgentJobRun::query()->create([
            'agent_job_id' => $job->id,
            'user_id' => $user->id,
            'initiated_by_user_id' => $user->id,
            'trigger_type' => AgentJobRun::TRIGGER_MANUAL,
            'status' => AgentJobRun::STATUS_QUEUED,
            'duration_ms' => 0,
            'stdout_bytes_pre' => 0,
            'stdout_bytes_post' => 0,
            'stderr_bytes_pre' => 0,
            'stderr_bytes_post' => 0,
            'metadata_json' => array_merge([
                'output_truncated' => false,
                'redaction_count' => 0,
            ], $metadata),
        ]);
    }

    private function runExecuteAgentRunJob(int $runId): void
    {
        $job = new ExecuteAgentRunJob($runId);
        app()->call([$job, 'handle']);
    }
}
