<?php

namespace App\Support\Agent;

use App\Jobs\ExecuteAgentRunJob;
use App\Models\AgentJob;
use App\Models\AgentJobRun;
use Illuminate\Support\Facades\Log;

class TargetedRetryService
{
    private const RETRY_TEMPLATE = <<<'MD'
## Retry: Correcting Previous Attempt

Your previous attempt (Run #{{original_run_id}}) failed verification.

### What Went Wrong
Your TASK formulation was: "{{original_task_step}}"

This led to {{failure_mode_description}}.

### Corrective Reframe
Re-examine the SITUATION and reformulate your TASK step. Specifically:
- {{targeted_correction_guidance}}

### Complete STAR Framework (Revised)

#### SITUATION
{{original_situation_step}}

#### TASK
[Reformulate this step, addressing the issue above]

#### ACTION
[Derive new actions from the corrected TASK]

#### RESULT
[Update verification criteria accordingly]

---

Now proceed with the corrected approach.
MD;

    public function __construct(
        private readonly UsageLimitState $usageLimitState,
    ) {}

    public function shouldRetry(AgentJobRun $failedRun): bool
    {
        // Must be in failed state
        if ($failedRun->status !== AgentJobRun::STATUS_FAILED) {
            return false;
        }

        // Must have STAR reasoning summary
        $metadata = $failedRun->metadata_json ?? [];
        if (empty($metadata['reasoning_summary']['steps'])) {
            return false;
        }

        // Ensure job is loaded
        $failedRun->loadMissing('job');
        if ($failedRun->job === null) {
            return false;
        }

        // Check if retry is enabled
        if (! $this->isRetryEnabled($failedRun->job)) {
            return false;
        }

        // Check retry count
        $retryCount = $metadata['retry_count'] ?? 0;
        $maxRetries = $this->getMaxRetries($failedRun->job);
        if ($retryCount >= $maxRetries) {
            return false;
        }

        // Check rate-limit hold (integrate with existing UsageLimitState)
        if ($this->isOnRateLimitHold($failedRun->job)) {
            return false;
        }

        return true;
    }

    public function buildRetryPrompt(AgentJobRun $failedRun, ?string $customPrompt = null): string
    {
        if ($customPrompt !== null) {
            return $customPrompt;
        }

        $metadata = $failedRun->metadata_json ?? [];
        $summary = $metadata['reasoning_summary']['steps'] ?? [];
        $hint = $metadata['failure_mode_hint'] ?? [];

        $replacements = [
            '{{original_run_id}}' => (string) $failedRun->id,
            '{{original_task_step}}' => $summary['task']['content'] ?? 'Not captured',
            '{{failure_mode_description}}' => $hint['description'] ?? 'verification failure',
            '{{targeted_correction_guidance}}' => $this->getCorrectionGuidance($hint),
            '{{original_situation_step}}' => $summary['situation']['content'] ?? 'Not captured',
        ];

        return str_replace(
            array_keys($replacements),
            array_values($replacements),
            self::RETRY_TEMPLATE
        );
    }

    public function dispatchRetry(AgentJobRun $failedRun, ?string $customPrompt = null): AgentJobRun
    {
        $retryPrompt = $this->buildRetryPrompt($failedRun, $customPrompt);
        $originalMetadata = $failedRun->metadata_json ?? [];

        // Ensure job is loaded for user_id
        $failedRun->loadMissing('job');

        // Create new run
        $retryRun = AgentJobRun::create([
            'agent_job_id' => $failedRun->agent_job_id,
            'user_id' => $failedRun->user_id,
            'initiated_by_user_id' => $failedRun->initiated_by_user_id,
            'trigger_type' => AgentJobRun::TRIGGER_MANUAL,
            'status' => AgentJobRun::STATUS_QUEUED,
            'metadata_json' => [
                'retry_of_run_id' => $failedRun->id,
                'retry_count' => ($originalMetadata['retry_count'] ?? 0) + 1,
                'retry_prompt' => $retryPrompt,
            ],
        ]);

        // Dispatch job
        ExecuteAgentRunJob::dispatch($retryRun->id);

        Log::info('Targeted retry dispatched', [
            'original_run_id' => $failedRun->id,
            'retry_run_id' => $retryRun->id,
            'retry_count' => $retryRun->metadata_json['retry_count'],
        ]);

        return $retryRun;
    }

    private function isRetryEnabled(AgentJob $job): bool
    {
        // Per-job setting takes precedence
        if ($job->targeted_retry_enabled !== null) {
            return (bool) $job->targeted_retry_enabled;
        }

        // Fall back to global config
        return (bool) config('agent.targeted_retry.enabled', false);
    }

    private function getMaxRetries(AgentJob $job): int
    {
        // Per-job setting takes precedence
        if ($job->max_retries !== null) {
            return (int) $job->max_retries;
        }

        // Fall back to global config
        return (int) config('agent.targeted_retry.max_retries', 1);
    }

    private function isOnRateLimitHold(AgentJob $job): bool
    {
        $hold = $this->usageLimitState->getActiveHold((int) $job->id);

        return $hold !== null;
    }

    /**
     * @param  array<string, mixed>  $hint
     */
    private function getCorrectionGuidance(array $hint): string
    {
        return match ($hint['type'] ?? 0) {
            1 => 'Focus on the actual subject of the job, not tangential concerns',
            2 => 'Keep ACTION aligned with TASK without scope expansion',
            3 => 'Ensure each ACTION step directly advances the TASK goal',
            default => 'Review your approach and identify the misalignment',
        };
    }
}
