<?php

namespace App\Support\Delegation\Verification;

use App\Jobs\AiCriticCompletedJob;
use App\Jobs\ExecuteAgentRunJob;
use App\Models\AgentJob;
use App\Models\AgentJobRun;
use App\Models\DelegationAttempt;
use App\Models\DelegationTask;
use App\Models\DelegationVerificationResult;
use Illuminate\Support\Facades\Bus;
use JsonException;

/**
 * Spawns an AI agent to review task output and provide verification.
 *
 * The AI critic step is asynchronous - it creates an AgentJobRun to execute
 * the review and returns 'pending' immediately. When the review completes,
 * AiCriticCompletedJob processes the result and fires DelegationTaskVerified.
 *
 * Prompt layering:
 * - Base prompt: config('delegation.ai_critic_default_prompt_template')
 * - Override: stepConfig['prompt_template'] if provided
 *
 * Evidence parsing (hybrid format):
 * - Attempts to parse output as JSON with 'verdict' and 'issues' fields
 * - Falls back to raw text if JSON parsing fails or fields are missing
 */
class AiCriticStep
{
    /**
     * Execute the AI critic step.
     *
     * Creates an AgentJobRun for the AI review and dispatches the execution.
     * Returns pending immediately since review is asynchronous.
     *
     * @param  DelegationTask  $task  The task being verified
     * @param  DelegationAttempt  $attempt  The attempt being verified
     * @param  array  $stepConfig  Step configuration including optional 'prompt_template'
     * @return VerificationStepResult Always returns pending
     */
    public function execute(
        DelegationTask $task,
        DelegationAttempt $attempt,
        array $stepConfig
    ): VerificationStepResult {
        $stepOrder = $stepConfig['step_order'] ?? 0;

        // Build the review prompt using layered configuration
        $promptTemplate = $stepConfig['prompt_template']
            ?? config('delegation.ai_critic_default_prompt_template')
            ?? 'Review the following task output and determine if it meets the requirements.';

        // Substitute task context into the prompt
        $prompt = $this->buildPrompt($promptTemplate, $task, $attempt);

        // Create the pending verification result first
        $verificationResult = DelegationVerificationResult::create([
            'delegation_task_id' => $task->id,
            'delegation_attempt_id' => $attempt->id,
            'step_type' => DelegationVerificationResult::STEP_TYPE_AI_CRITIC,
            'step_order' => $stepOrder,
            'verdict' => DelegationVerificationResult::VERDICT_PENDING,
            'evidence_json' => null,
            'started_at' => now(),
        ]);

        // Create transient AgentJob for the review
        $job = AgentJob::create([
            'user_id' => $task->graph->user_id,
            'name' => "AI Critic Review: {$task->name}",
            'description' => 'Automated AI review for delegation verification',
            'runner_type' => 'claude', // Use Claude for AI critic reviews
            'command_template' => '/usr/local/bin/claude --task {{task}}',
            'working_directory' => $attempt->profile->working_directory ?? base_path(),
            'env_json' => null,
            'cron_expression' => '@once', // Placeholder for non-scheduled job
            'timezone' => config('app.timezone', 'UTC'),
            'max_runtime_seconds' => config('delegation.verification_timeout_seconds', 300),
            'task_markdown_path' => $prompt, // Store prompt in task_markdown_path for reference
            'is_enabled' => false, // Transient job, not scheduled
        ]);

        // Create AgentJobRun with ai_critic source in metadata
        $run = AgentJobRun::create([
            'agent_job_id' => $job->id,
            'user_id' => $task->graph->user_id,
            'trigger_type' => AgentJobRun::TRIGGER_MANUAL,
            'status' => AgentJobRun::STATUS_QUEUED,
            'metadata_json' => [
                'source' => 'ai_critic',
                'task_id' => $task->id,
                'graph_id' => $task->graph->id,
                'attempt_id' => $attempt->id,
                'verification_result_id' => $verificationResult->id,
            ],
        ]);

        // Dispatch job chain: ExecuteAgentRunJob -> AiCriticCompletedJob
        Bus::chain([
            new ExecuteAgentRunJob($run->id),
            new AiCriticCompletedJob($verificationResult->id),
        ])->onQueue('delegation')->dispatch();

        return VerificationStepResult::pending();
    }

    /**
     * Parse the AI critic output into structured evidence.
     *
     * Uses hybrid format: attempts JSON parsing with expected fields,
     * falls back to raw text if parsing fails.
     *
     * @param  string  $output  The raw output from the AI critic
     * @return array Parsed evidence array
     */
    public function parseEvidence(string $output): array
    {
        try {
            $parsed = json_decode($output, true, 512, JSON_THROW_ON_ERROR);

            // Validate expected fields are present
            if (isset($parsed['verdict']) && isset($parsed['issues'])) {
                return $parsed;
            }
        } catch (JsonException) {
            // Fall through to raw text
        }

        return ['raw_output' => $output];
    }

    /**
     * Build the review prompt by substituting task context.
     */
    private function buildPrompt(
        string $template,
        DelegationTask $task,
        DelegationAttempt $attempt
    ): string {
        // Build context from task and attempt
        $context = [
            'task_name' => $task->name,
            'task_prompt' => $task->contract_json['prompt'] ?? '',
            'task_capability' => $task->contract_json['required_capability'] ?? '',
            'attempt_number' => $attempt->attempt_number,
            'attempt_status' => $attempt->status,
        ];

        // Simple placeholder substitution
        $prompt = $template;
        foreach ($context as $key => $value) {
            $prompt = str_replace("{{{$key}}}", (string) $value, $prompt);
        }

        return $prompt;
    }
}
