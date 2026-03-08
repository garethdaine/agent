<?php

declare(strict_types=1);

namespace App\Support\Delegation;

use App\Events\DelegationTaskVerified;
use App\Models\DelegationAttempt;
use App\Models\DelegationTask;
use App\Support\Delegation\Verification\AiCriticStep;
use App\Support\Delegation\Verification\AutomatedCheckStep;
use App\Support\Delegation\Verification\HumanApprovalStep;
use App\Support\Delegation\Verification\VerificationStepResult;

/**
 * Orchestrates the execution of verification steps for a delegation task.
 *
 * The pipeline executes ordered verification steps from the task's verification_strategy.
 * Steps are executed in order until:
 * - All steps pass → fires DelegationTaskVerified(passed=true)
 * - A step fails → short-circuits, fires DelegationTaskVerified(passed=false)
 * - A step returns pending → pauses, stores current step for resumption
 *
 * Resumption:
 * When an async step (AI critic, human approval) completes, resume() is called
 * to continue from the next step.
 */
class VerificationPipeline
{
    private AutomatedCheckStep $automatedCheckStep;

    private AiCriticStep $aiCriticStep;

    private HumanApprovalStep $humanApprovalStep;

    public function __construct(
        ?AutomatedCheckStep $automatedCheckStep = null,
        ?AiCriticStep $aiCriticStep = null,
        ?HumanApprovalStep $humanApprovalStep = null
    ) {
        $this->automatedCheckStep = $automatedCheckStep ?? new AutomatedCheckStep;
        $this->aiCriticStep = $aiCriticStep ?? new AiCriticStep;
        $this->humanApprovalStep = $humanApprovalStep ?? new HumanApprovalStep;
    }

    /**
     * Execute the verification pipeline for a task/attempt.
     *
     * @param  DelegationTask  $task  The task to verify
     * @param  DelegationAttempt  $attempt  The attempt to verify
     */
    public function execute(DelegationTask $task, DelegationAttempt $attempt): void
    {
        $this->runPipeline($task, $attempt, startFromStep: 0);
    }

    /**
     * Resume the verification pipeline from the current step.
     *
     * Called when an async verification step completes.
     *
     * @param  DelegationTask  $task  The task to verify
     * @param  DelegationAttempt  $attempt  The attempt to verify
     */
    public function resume(DelegationTask $task, DelegationAttempt $attempt): void
    {
        $currentStep = $task->metadata_json['verification_current_step'] ?? 0;
        $this->runPipeline($task, $attempt, startFromStep: $currentStep);
    }

    /**
     * Run the pipeline starting from a specific step.
     */
    private function runPipeline(
        DelegationTask $task,
        DelegationAttempt $attempt,
        int $startFromStep
    ): void {
        $steps = $this->getOrderedSteps($task);

        // No verification strategy → pass immediately
        if (empty($steps)) {
            event(new DelegationTaskVerified($task, $attempt, passed: true));

            return;
        }

        // Execute steps starting from the specified step
        foreach ($steps as $stepConfig) {
            $stepOrder = $stepConfig['step_order'] ?? 0;

            // Skip steps we've already completed
            if ($stepOrder < $startFromStep) {
                continue;
            }

            $result = $this->executeStep($task, $attempt, $stepConfig);

            if ($result->isFailed()) {
                // Short-circuit on failure
                event(new DelegationTaskVerified(
                    $task,
                    $attempt,
                    passed: false,
                    failedStepOrder: $stepOrder
                ));

                return;
            }

            if ($result->isPending()) {
                // Save current step for resumption
                $this->saveCurrentStep($task, $stepOrder);

                // Don't fire event yet - wait for async completion
                return;
            }

            // Step passed, continue to next
        }

        // All steps passed
        event(new DelegationTaskVerified($task, $attempt, passed: true));
    }

    /**
     * Execute a single verification step.
     */
    private function executeStep(
        DelegationTask $task,
        DelegationAttempt $attempt,
        array $stepConfig
    ): VerificationStepResult {
        $type = $stepConfig['type'] ?? '';

        return match ($type) {
            'automated_check' => $this->automatedCheckStep->execute($task, $attempt, $stepConfig),
            'ai_critic' => $this->aiCriticStep->execute($task, $attempt, $stepConfig),
            'human_approval' => $this->humanApprovalStep->execute($task, $attempt, $stepConfig),
            default => VerificationStepResult::failed([
                'error' => "Unknown step type: {$type}",
            ]),
        };
    }

    /**
     * Get ordered verification steps from the task's contract.
     *
     * @return array<array> Ordered step configurations
     */
    private function getOrderedSteps(DelegationTask $task): array
    {
        $strategy = $task->contract_json['verification_strategy'] ?? null;
        if ($strategy === null) {
            return [];
        }

        $steps = $strategy['steps'] ?? [];

        // Sort by step_order
        usort($steps, fn ($a, $b) => ($a['step_order'] ?? 0) <=> ($b['step_order'] ?? 0));

        return $steps;
    }

    /**
     * Save the current step index for pipeline resumption.
     */
    private function saveCurrentStep(DelegationTask $task, int $stepOrder): void
    {
        $task->update([
            'metadata_json' => array_merge($task->metadata_json ?? [], [
                'verification_current_step' => $stepOrder,
            ]),
        ]);
    }
}
