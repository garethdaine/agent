<?php

declare(strict_types=1);

namespace App\Support\Delegation\Verification;

use App\Models\DelegationAttempt;
use App\Models\DelegationTask;
use App\Models\DelegationVerificationResult;

/**
 * Creates a pending human approval request for task verification.
 *
 * Human approval is asynchronous - it creates a pending verification result
 * with an expiration time and returns immediately. The approval is resolved
 * via API endpoint, which fires DelegationTaskVerified.
 *
 * Expiration:
 * - Default: config('delegation.human_approval_timeout_hours') = 4 hours
 * - Override: stepConfig['timeout_hours'] if provided
 *
 * The reconciler will mark expired approvals as failed.
 */
class HumanApprovalStep
{
    /**
     * Execute the human approval step.
     *
     * Creates a pending verification result with expiration time.
     * Returns pending immediately since approval is manual.
     *
     * @param  DelegationTask  $task  The task being verified
     * @param  DelegationAttempt  $attempt  The attempt being verified
     * @param  array  $stepConfig  Step configuration including optional 'timeout_hours' and 'instructions'
     * @return VerificationStepResult Always returns pending
     */
    public function execute(
        DelegationTask $task,
        DelegationAttempt $attempt,
        array $stepConfig
    ): VerificationStepResult {
        // Pre-execution trust gate: block if delegatee trust is low or task is irreversible
        if ($this->requiresPreApproval($task)) {
            $task->update(['status' => DelegationTask::STATUS_PENDING_APPROVAL]);

            DelegationVerificationResult::create([
                'delegation_task_id' => $task->id,
                'delegation_attempt_id' => $attempt->id,
                'step_type' => DelegationVerificationResult::STEP_TYPE_HUMAN_APPROVAL,
                'step_order' => $stepConfig['step_order'] ?? 0,
                'verdict' => DelegationVerificationResult::VERDICT_PENDING,
                'evidence_json' => [
                    'gate_reason' => 'trust_or_reversibility',
                    'task_context' => [
                        'task_name' => $task->name,
                        'task_prompt' => $task->contract_json['prompt'] ?? '',
                        'attempt_number' => $attempt->attempt_number,
                    ],
                ],
                'started_at' => now(),
                'expires_at' => now()->addHours(
                    $stepConfig['timeout_hours'] ?? config('delegation.human_approval_timeout_hours', 4)
                ),
            ]);

            return VerificationStepResult::pending();
        }

        $stepOrder = $stepConfig['step_order'] ?? 0;

        // Calculate expiration time
        $timeoutHours = $stepConfig['timeout_hours']
            ?? config('delegation.human_approval_timeout_hours', 4);
        $expiresAt = now()->addHours($timeoutHours);

        // Build initial evidence with context for the reviewer
        $evidence = [];
        if (isset($stepConfig['instructions'])) {
            $evidence['instructions'] = $stepConfig['instructions'];
        }

        // Include task context for the reviewer
        $evidence['task_context'] = [
            'task_name' => $task->name,
            'task_prompt' => $task->contract_json['prompt'] ?? '',
            'attempt_number' => $attempt->attempt_number,
        ];

        // Create the pending verification result
        DelegationVerificationResult::create([
            'delegation_task_id' => $task->id,
            'delegation_attempt_id' => $attempt->id,
            'step_type' => DelegationVerificationResult::STEP_TYPE_HUMAN_APPROVAL,
            'step_order' => $stepOrder,
            'verdict' => DelegationVerificationResult::VERDICT_PENDING,
            'evidence_json' => $evidence,
            'started_at' => now(),
            'expires_at' => $expiresAt,
        ]);

        return VerificationStepResult::pending();
    }

    /**
     * Determine if the task requires pre-execution approval.
     *
     * Gated if delegatee trust_score < threshold OR task reversibility is false.
     */
    private function requiresPreApproval(DelegationTask $task): bool
    {
        $threshold = (float) config('agent.delegation.approval_trust_threshold', 0.7);

        $profile = $task->assignedProfile;
        if ($profile !== null && (float) $profile->trust_score < $threshold) {
            return true;
        }

        if (($task->contract_json['reversibility'] ?? null) === false) {
            return true;
        }

        return false;
    }
}
