<?php

namespace App\Support\Compliance;

use App\Contracts\OrchestrationPolicyServiceContract;
use App\Enums\TaskCategory;
use App\Models\AgentJobRun;
use App\Models\InterrogationBuildTask;
use App\Support\Compliance\DTOs\CompletionGateResult;
use App\Support\Compliance\DTOs\GateResult;
use App\Support\Compliance\DTOs\PolicyEvaluationResult;

/**
 * Unified orchestration policy evaluation for jobs and builds.
 *
 * This service orchestrates policy evaluation for both AgentJobRun and InterrogationBuildTask models.
 * It determines complexity classification, required gates, and evaluates completion criteria.
 *
 * Conditions for correctness:
 * - ComplexityClassifier must be properly configured with threshold values
 * - VerificationEvidenceEvaluator must correctly map TaskCategory to evidence requirements
 * - Config keys agent.compliance.* must exist with expected types
 *
 * Known limitations:
 * - Does not evaluate plan evidence content quality (only presence)
 * - Does not evaluate elegance gate (deferred to future implementation)
 * - Does not validate metadata_json schema beyond expected keys
 */
class OrchestrationPolicyService implements OrchestrationPolicyServiceContract
{
    public function __construct(
        private readonly ComplexityClassifier $classifier,
        private readonly VerificationEvidenceEvaluator $verificationEvaluator
    ) {}

    public function isEnabled(): bool
    {
        return (bool) config('agent.compliance.enabled', false);
    }

    public function getEnforcementMode(): string
    {
        return (string) config('agent.compliance.enforcement_mode', 'advisory');
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function evaluatePreRun(AgentJobRun|InterrogationBuildTask $subject, array $context = []): PolicyEvaluationResult
    {
        if (! $this->isEnabled()) {
            return $this->disabledPassResult();
        }

        $metadata = $this->getMetadata($subject);
        $classificationContext = array_merge($metadata, $context);

        $complexityResult = $this->classifier->classify($classificationContext);
        $category = $this->resolveCategory($subject, $context);

        $planGateEnabled = (bool) config('agent.compliance.plan_gate_enabled', true);
        $verificationGateEnabled = (bool) config('agent.compliance.verification_gate_enabled', true);
        $eleganceGateEnabled = (bool) config('agent.compliance.elegance_gate_enabled', false);

        $planRequired = $complexityResult->isNonTrivial() && $planGateEnabled;
        $verificationRequired = $verificationGateEnabled;
        $eleganceRequired = $complexityResult->isNonTrivial() && $eleganceGateEnabled;

        $gates = [];

        // Skill chain complexity gate
        if ($planGateEnabled && $this->skillChainRequiresPlanning($context)) {
            $planRequired = true;
            $gates[] = new GateResult(
                gate: 'skill_chain_complexity',
                status: 'requires_plan',
                reasonCode: 'complex_skill_chain',
                remediation: 'Skill chain with >3 ordered dependencies requires planning step.',
            );
        }

        return new PolicyEvaluationResult(
            status: 'pass',
            complexity: $complexityResult->classification,
            taskCategory: $category,
            planRequired: $planRequired,
            verificationRequired: $verificationRequired,
            eleganceRequired: $eleganceRequired,
            gates: $gates,
            metadataPatch: [
                'workflow_policy_version' => '1.0',
                'complexity_classification' => $complexityResult->classification,
                'task_category' => $category->value,
                'plan_required' => $planRequired,
                'verification_required' => $verificationRequired,
            ]
        );
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function evaluateCompletion(AgentJobRun|InterrogationBuildTask $subject, array $context = []): CompletionGateResult
    {
        if (! $this->isEnabled()) {
            return new CompletionGateResult(
                canComplete: true,
                status: 'pass',
                gates: []
            );
        }

        $metadata = $this->getMetadata($subject);
        $category = $this->resolveCategory($subject, $context);
        $gates = [];
        $blocked = false;
        $blockReason = null;
        $remediation = null;

        // Verification gate evaluation
        if ((bool) config('agent.compliance.verification_gate_enabled', true)) {
            /** @var array<string, mixed> $evidence */
            $evidence = $metadata['verification_evidence'] ?? [];
            $verificationResult = $this->verificationEvaluator->evaluate($category, $evidence);

            $gateStatus = $this->determineGateStatus($verificationResult->satisfied);

            $gates[] = new GateResult(
                gate: 'verification',
                status: $gateStatus,
                reasonCode: $verificationResult->satisfied ? null : 'missing_evidence',
                remediation: $verificationResult->satisfied ? null : 'Provide: '.implode(', ', $verificationResult->missing),
                evidence: ['present' => $verificationResult->present, 'missing' => $verificationResult->missing]
            );

            if (! $verificationResult->satisfied && $this->getEnforcementMode() === 'strict') {
                $blocked = true;
                $blockReason = 'verification_incomplete';
                $remediation = 'Provide: '.implode(', ', $verificationResult->missing);
            }
        }

        return new CompletionGateResult(
            canComplete: ! $blocked,
            status: $blocked ? 'blocked' : 'pass',
            gates: $gates,
            blockReason: $blockReason,
            remediation: $remediation
        );
    }

    /**
     * Resolve the task category from subject metadata or context.
     *
     * Priority: context override > subject model property > subject metadata > default (CUSTOM)
     *
     * @param  array<string, mixed>  $context
     */
    private function resolveCategory(AgentJobRun|InterrogationBuildTask $subject, array $context): TaskCategory
    {
        // Context takes precedence
        if (isset($context['task_category'])) {
            return TaskCategory::fromString((string) $context['task_category']);
        }

        // For InterrogationBuildTask, check the model's task_category property
        if ($subject instanceof InterrogationBuildTask && $subject->task_category instanceof TaskCategory) {
            return $subject->task_category;
        }

        // Fall back to metadata
        $metadata = $this->getMetadata($subject);
        $categoryValue = $metadata['task_category'] ?? 'custom';

        return TaskCategory::fromString((string) $categoryValue);
    }

    /**
     * Get metadata from subject with safe null handling.
     *
     * @return array<string, mixed>
     */
    private function getMetadata(AgentJobRun|InterrogationBuildTask $subject): array
    {
        return $subject->metadata_json ?? [];
    }

    /**
     * Return a pass result when compliance is disabled.
     *
     * All gates are disabled and no requirements are set.
     */
    private function disabledPassResult(): PolicyEvaluationResult
    {
        return new PolicyEvaluationResult(
            status: 'pass',
            complexity: 'simple',
            taskCategory: TaskCategory::CUSTOM,
            planRequired: false,
            verificationRequired: false,
            eleganceRequired: false,
            gates: [],
            metadataPatch: []
        );
    }

    /**
     * Check if the resolved skill chain has >3 skills with run_after dependencies.
     *
     * @param  array<string, mixed>  $context
     */
    private function skillChainRequiresPlanning(array $context): bool
    {
        $resolvedSkills = $context['resolved_skills'] ?? [];
        $skillsWithDeps = array_filter($resolvedSkills, fn ($s) => ! empty($s['run_after']));

        return count($skillsWithDeps) > 3;
    }

    /**
     * Determine gate status based on satisfaction and enforcement mode.
     */
    private function determineGateStatus(bool $satisfied): string
    {
        if ($satisfied) {
            return 'pass';
        }

        return $this->getEnforcementMode() === 'strict' ? 'blocked' : 'advisory';
    }
}
