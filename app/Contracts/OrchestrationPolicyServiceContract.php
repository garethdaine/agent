<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Models\AgentJobRun;
use App\Models\InterrogationBuildTask;
use App\Support\Compliance\DTOs\CompletionGateResult;
use App\Support\Compliance\DTOs\PolicyEvaluationResult;

interface OrchestrationPolicyServiceContract
{
    /**
     * Evaluate policy before a run/task starts execution.
     *
     * Returns complexity classification, required gates, and metadata patch.
     * When compliance is disabled, returns a pass result with no requirements.
     *
     * @param  array<string, mixed>  $context  Additional context for evaluation (e.g., task_category override)
     */
    public function evaluatePreRun(AgentJobRun|InterrogationBuildTask $subject, array $context = []): PolicyEvaluationResult;

    /**
     * Evaluate completion gates before a run/task can finish.
     *
     * Returns whether completion is allowed based on verification evidence.
     * In strict mode, missing evidence blocks completion.
     * In advisory mode, missing evidence generates warnings but allows completion.
     *
     * @param  array<string, mixed>  $context  Additional context for evaluation
     */
    public function evaluateCompletion(AgentJobRun|InterrogationBuildTask $subject, array $context = []): CompletionGateResult;

    /**
     * Check if compliance evaluation is enabled.
     */
    public function isEnabled(): bool;

    /**
     * Get the current enforcement mode ('advisory' or 'strict').
     */
    public function getEnforcementMode(): string;
}
