<?php

namespace Tests\Unit\Support\Compliance;

use App\Contracts\OrchestrationPolicyServiceContract;
use App\Enums\TaskCategory;
use App\Models\AgentJobRun;
use App\Models\InterrogationBuildTask;
use App\Support\Compliance\ComplexityClassifier;
use App\Support\Compliance\DTOs\CompletionGateResult;
use App\Support\Compliance\DTOs\PolicyEvaluationResult;
use App\Support\Compliance\OrchestrationPolicyService;
use App\Support\Compliance\VerificationEvidenceEvaluator;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OrchestrationPolicyServiceTest extends TestCase
{
    private ComplexityClassifier $classifier;

    private VerificationEvidenceEvaluator $verificationEvaluator;

    private OrchestrationPolicyService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->classifier = new ComplexityClassifier([
            'file_count' => 3,
            'loc_count' => 50,
            'directory_count' => 2,
        ]);

        $this->verificationEvaluator = new VerificationEvidenceEvaluator;
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function makeService(bool $enabled = true, string $mode = 'strict'): OrchestrationPolicyService
    {
        config()->set('agent.compliance.enabled', $enabled);
        config()->set('agent.compliance.enforcement_mode', $mode);
        config()->set('agent.compliance.plan_gate_enabled', true);
        config()->set('agent.compliance.verification_gate_enabled', true);
        config()->set('agent.compliance.elegance_gate_enabled', false);

        return new OrchestrationPolicyService(
            $this->classifier,
            $this->verificationEvaluator
        );
    }

    private function makeJobRun(array $metadata = []): AgentJobRun
    {
        $run = new AgentJobRun;
        $run->metadata_json = $metadata;

        return $run;
    }

    private function makeBuildTask(array $metadata = []): InterrogationBuildTask
    {
        $task = new InterrogationBuildTask;
        $task->metadata_json = $metadata;

        return $task;
    }

    // ========================================================================
    // evaluatePreRun() Tests
    // ========================================================================

    #[Test]
    public function evaluate_pre_run_returns_correct_complexity_classification_for_simple(): void
    {
        $service = $this->makeService();
        $run = $this->makeJobRun([
            'file_count' => 2,
            'loc_count' => 30,
            'directory_count' => 1,
        ]);

        $result = $service->evaluatePreRun($run);

        $this->assertInstanceOf(PolicyEvaluationResult::class, $result);
        $this->assertEquals('simple', $result->complexity);
        $this->assertEquals('pass', $result->status);
    }

    #[Test]
    public function evaluate_pre_run_returns_correct_complexity_classification_for_non_trivial(): void
    {
        $service = $this->makeService();
        $run = $this->makeJobRun([
            'file_count' => 5,
            'loc_count' => 100,
            'directory_count' => 3,
        ]);

        $result = $service->evaluatePreRun($run);

        $this->assertInstanceOf(PolicyEvaluationResult::class, $result);
        $this->assertEquals('non_trivial', $result->complexity);
    }

    #[Test]
    public function evaluate_pre_run_sets_plan_required_true_for_non_trivial(): void
    {
        $service = $this->makeService();
        $run = $this->makeJobRun([
            'file_count' => 5,
            'loc_count' => 100,
            'directory_count' => 3,
        ]);

        $result = $service->evaluatePreRun($run);

        $this->assertTrue($result->planRequired);
        $this->assertArrayHasKey('plan_required', $result->metadataPatch);
        $this->assertTrue($result->metadataPatch['plan_required']);
    }

    #[Test]
    public function evaluate_pre_run_sets_plan_required_false_for_simple(): void
    {
        $service = $this->makeService();
        $run = $this->makeJobRun([
            'file_count' => 2,
            'loc_count' => 30,
            'directory_count' => 1,
        ]);

        $result = $service->evaluatePreRun($run);

        $this->assertFalse($result->planRequired);
        $this->assertArrayHasKey('plan_required', $result->metadataPatch);
        $this->assertFalse($result->metadataPatch['plan_required']);
    }

    #[Test]
    public function evaluate_pre_run_returns_advisory_status_when_enforcement_disabled(): void
    {
        $service = $this->makeService(enabled: false);
        $run = $this->makeJobRun([
            'file_count' => 5,
            'loc_count' => 100,
        ]);

        $result = $service->evaluatePreRun($run);

        $this->assertEquals('pass', $result->status);
        $this->assertFalse($result->planRequired);
        $this->assertFalse($result->verificationRequired);
    }

    #[Test]
    public function evaluate_pre_run_works_with_build_task(): void
    {
        $service = $this->makeService();
        $task = $this->makeBuildTask([
            'file_count' => 5,
            'task_category' => 'feature',
        ]);

        $result = $service->evaluatePreRun($task);

        $this->assertInstanceOf(PolicyEvaluationResult::class, $result);
        $this->assertEquals(TaskCategory::FEATURE, $result->taskCategory);
    }

    #[Test]
    public function evaluate_pre_run_uses_context_override_for_category(): void
    {
        $service = $this->makeService();
        $run = $this->makeJobRun();

        $result = $service->evaluatePreRun($run, ['task_category' => 'bugfix']);

        $this->assertEquals(TaskCategory::BUGFIX, $result->taskCategory);
    }

    // ========================================================================
    // evaluateCompletion() Tests
    // ========================================================================

    #[Test]
    public function evaluate_completion_checks_verification_evidence(): void
    {
        $service = $this->makeService();
        $run = $this->makeJobRun([
            'task_category' => 'feature',
            'verification_evidence' => [
                'automated_check' => true,
                'ai_critic' => true,
            ],
        ]);

        $result = $service->evaluateCompletion($run);

        $this->assertInstanceOf(CompletionGateResult::class, $result);
        $this->assertNotEmpty($result->gates);
    }

    #[Test]
    public function evaluate_completion_returns_blocked_when_verification_missing_strict_mode(): void
    {
        $service = $this->makeService(enabled: true, mode: 'strict');
        $run = $this->makeJobRun([
            'task_category' => 'feature',
            'verification_evidence' => [],
        ]);

        $result = $service->evaluateCompletion($run);

        $this->assertFalse($result->canComplete);
        $this->assertEquals('blocked', $result->status);
        $this->assertEquals('verification_incomplete', $result->blockReason);
        $this->assertNotNull($result->remediation);
    }

    #[Test]
    public function evaluate_completion_returns_advisory_when_verification_missing_advisory_mode(): void
    {
        $service = $this->makeService(enabled: true, mode: 'advisory');
        $run = $this->makeJobRun([
            'task_category' => 'feature',
            'verification_evidence' => [],
        ]);

        $result = $service->evaluateCompletion($run);

        $this->assertTrue($result->canComplete);
        $this->assertEquals('pass', $result->status);
        // Gate should show advisory status
        $verificationGate = collect($result->gates)->firstWhere('gate', 'verification');
        $this->assertNotNull($verificationGate);
        $this->assertEquals('advisory', $verificationGate->status);
    }

    #[Test]
    public function evaluate_completion_returns_pass_when_all_gates_satisfied(): void
    {
        $service = $this->makeService();
        $run = $this->makeJobRun([
            'task_category' => 'feature',
            'verification_evidence' => [
                'automated_check' => true,
                'ai_critic' => true,
            ],
        ]);

        $result = $service->evaluateCompletion($run);

        $this->assertTrue($result->canComplete);
        $this->assertEquals('pass', $result->status);
        $this->assertNull($result->blockReason);
    }

    #[Test]
    public function evaluate_completion_returns_pass_when_compliance_disabled(): void
    {
        $service = $this->makeService(enabled: false);
        $run = $this->makeJobRun([
            'task_category' => 'feature',
            'verification_evidence' => [],
        ]);

        $result = $service->evaluateCompletion($run);

        $this->assertTrue($result->canComplete);
        $this->assertEquals('pass', $result->status);
        $this->assertEmpty($result->gates);
    }

    #[Test]
    public function evaluate_completion_works_with_build_task(): void
    {
        $service = $this->makeService();
        $task = $this->makeBuildTask([
            'task_category' => 'docs',
            'verification_evidence' => [],
        ]);

        $result = $service->evaluateCompletion($task);

        // docs category requires no verification
        $this->assertTrue($result->canComplete);
        $this->assertEquals('pass', $result->status);
    }

    // ========================================================================
    // isEnabled() / getEnforcementMode() Tests
    // ========================================================================

    #[Test]
    public function is_enabled_returns_config_value(): void
    {
        $service = $this->makeService(enabled: true);
        $this->assertTrue($service->isEnabled());

        $service = $this->makeService(enabled: false);
        $this->assertFalse($service->isEnabled());
    }

    #[Test]
    public function get_enforcement_mode_returns_config_value(): void
    {
        $service = $this->makeService(enabled: true, mode: 'strict');
        $this->assertEquals('strict', $service->getEnforcementMode());

        $service = $this->makeService(enabled: true, mode: 'advisory');
        $this->assertEquals('advisory', $service->getEnforcementMode());
    }

    // ========================================================================
    // Metadata Patch Tests
    // ========================================================================

    #[Test]
    public function evaluate_pre_run_includes_metadata_patch_with_policy_version(): void
    {
        $service = $this->makeService();
        $run = $this->makeJobRun();

        $result = $service->evaluatePreRun($run);

        $this->assertArrayHasKey('workflow_policy_version', $result->metadataPatch);
        $this->assertEquals('1.0', $result->metadataPatch['workflow_policy_version']);
    }

    #[Test]
    public function evaluate_pre_run_includes_complexity_classification_in_metadata(): void
    {
        $service = $this->makeService();
        $run = $this->makeJobRun([
            'file_count' => 5,
        ]);

        $result = $service->evaluatePreRun($run);

        $this->assertArrayHasKey('complexity_classification', $result->metadataPatch);
        $this->assertEquals('non_trivial', $result->metadataPatch['complexity_classification']);
    }

    #[Test]
    public function evaluate_pre_run_includes_task_category_in_metadata(): void
    {
        $service = $this->makeService();
        $run = $this->makeJobRun([
            'task_category' => 'bugfix',
        ]);

        $result = $service->evaluatePreRun($run);

        $this->assertArrayHasKey('task_category', $result->metadataPatch);
        $this->assertEquals('bugfix', $result->metadataPatch['task_category']);
    }

    #[Test]
    public function evaluate_pre_run_includes_verification_required_in_metadata(): void
    {
        $service = $this->makeService();
        $run = $this->makeJobRun();

        $result = $service->evaluatePreRun($run);

        $this->assertArrayHasKey('verification_required', $result->metadataPatch);
    }
}
