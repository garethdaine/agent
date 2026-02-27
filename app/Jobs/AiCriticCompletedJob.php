<?php

namespace App\Jobs;

use App\Events\DelegationTaskVerified;
use App\Models\AgentJobRun;
use App\Models\DelegationVerificationResult;
use App\Support\Delegation\Verification\AiCriticStep;
use App\Support\Delegation\VerificationPipeline;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Processes the completion of an AI critic review.
 *
 * This job is dispatched as part of a chain after ExecuteAgentRunJob completes.
 * It:
 * 1. Fetches the AgentJobRun output
 * 2. Parses the evidence using hybrid format
 * 3. Updates the DelegationVerificationResult with verdict and evidence
 * 4. Resumes the verification pipeline if there are more steps
 */
class AiCriticCompletedJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @param  int  $verificationResultId  The ID of the verification result to update
     */
    public function __construct(
        public readonly int $verificationResultId
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $verificationResult = DelegationVerificationResult::find($this->verificationResultId);
        if ($verificationResult === null) {
            Log::warning('AiCriticCompletedJob: Verification result not found', [
                'verification_result_id' => $this->verificationResultId,
            ]);

            return;
        }

        // Find the associated AgentJobRun
        $run = AgentJobRun::where('metadata_json->verification_result_id', $this->verificationResultId)
            ->where('metadata_json->source', 'ai_critic')
            ->first();

        if ($run === null) {
            Log::warning('AiCriticCompletedJob: AgentJobRun not found', [
                'verification_result_id' => $this->verificationResultId,
            ]);
            $this->markFailed($verificationResult, 'AgentJobRun not found');

            return;
        }

        // Get the run output
        $output = $this->getRunOutput($run);

        // Parse evidence using hybrid format
        $aiCriticStep = new AiCriticStep;
        $evidence = $aiCriticStep->parseEvidence($output);

        // Determine verdict from evidence
        $verdict = $this->determineVerdict($evidence, $run);

        // Update the verification result
        $verificationResult->update([
            'verdict' => $verdict,
            'evidence_json' => $evidence,
            'finished_at' => now(),
        ]);

        // Load relationships needed for event
        $task = $verificationResult->task;
        $attempt = $verificationResult->attempt;

        if ($task === null || $attempt === null) {
            Log::warning('AiCriticCompletedJob: Task or attempt not found', [
                'verification_result_id' => $this->verificationResultId,
            ]);

            return;
        }

        // If failed, fire event immediately
        if ($verdict === DelegationVerificationResult::VERDICT_FAILED) {
            event(new DelegationTaskVerified(
                $task,
                $attempt,
                passed: false,
                failedStepOrder: $verificationResult->step_order
            ));

            return;
        }

        // If passed, check if there are more steps and resume pipeline
        $pipeline = new VerificationPipeline;
        $nextStep = $verificationResult->step_order + 1;

        // Update current step to continue from next
        $task->update([
            'metadata_json' => array_merge($task->metadata_json ?? [], [
                'verification_current_step' => $nextStep,
            ]),
        ]);

        // Resume the pipeline from the next step
        $pipeline->resume($task, $attempt);
    }

    /**
     * Get the output from the AgentJobRun.
     */
    private function getRunOutput(AgentJobRun $run): string
    {
        // In a real implementation, this would fetch the actual output
        // from the run's stdout or output files. For now, return from metadata.
        return $run->metadata_json['output'] ?? '';
    }

    /**
     * Determine the verdict from parsed evidence.
     */
    private function determineVerdict(array $evidence, AgentJobRun $run): string
    {
        // If the run failed, the review failed
        if ($run->status === AgentJobRun::STATUS_FAILED) {
            return DelegationVerificationResult::VERDICT_FAILED;
        }

        // Check for structured verdict in evidence
        if (isset($evidence['verdict'])) {
            $verdict = strtolower((string) $evidence['verdict']);
            if (in_array($verdict, ['passed', 'pass', 'approved', 'success'])) {
                return DelegationVerificationResult::VERDICT_PASSED;
            }
            if (in_array($verdict, ['failed', 'fail', 'rejected', 'error'])) {
                return DelegationVerificationResult::VERDICT_FAILED;
            }
        }

        // Default to passed if run succeeded and no explicit failure
        if ($run->status === AgentJobRun::STATUS_SUCCEEDED) {
            return DelegationVerificationResult::VERDICT_PASSED;
        }

        // Uncertain - treat as failed
        return DelegationVerificationResult::VERDICT_FAILED;
    }

    /**
     * Mark the verification result as failed with an error.
     */
    private function markFailed(DelegationVerificationResult $verificationResult, string $error): void
    {
        $verificationResult->update([
            'verdict' => DelegationVerificationResult::VERDICT_FAILED,
            'evidence_json' => ['error' => $error],
            'finished_at' => now(),
        ]);

        // Fire failed event
        $task = $verificationResult->task;
        $attempt = $verificationResult->attempt;

        if ($task !== null && $attempt !== null) {
            event(new DelegationTaskVerified(
                $task,
                $attempt,
                passed: false,
                failedStepOrder: $verificationResult->step_order
            ));
        }
    }
}
