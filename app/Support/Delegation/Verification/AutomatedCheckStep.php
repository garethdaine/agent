<?php

declare(strict_types=1);

namespace App\Support\Delegation\Verification;

use App\Models\DelegationAttempt;
use App\Models\DelegationTask;
use App\Models\DelegationVerificationResult;
use Illuminate\Support\Facades\Process;

/**
 * Executes automated verification checks by running commands from a check profile.
 *
 * Check profiles are defined in config/delegation.php under 'check_profiles' and
 * contain arrays of shell commands to execute. All commands must pass (exit code 0)
 * for the step to pass.
 *
 * Example check profile:
 * ```php
 * 'laravel_standard' => ['php artisan test', './vendor/bin/pint --test'],
 * ```
 */
class AutomatedCheckStep
{
    /**
     * Execute the automated check step.
     *
     * @param  DelegationTask  $task  The task being verified
     * @param  DelegationAttempt  $attempt  The attempt being verified
     * @param  array  $stepConfig  Step configuration including 'check_profile'
     * @return VerificationStepResult The result of executing the check
     */
    public function execute(
        DelegationTask $task,
        DelegationAttempt $attempt,
        array $stepConfig
    ): VerificationStepResult {
        $stepOrder = $stepConfig['step_order'] ?? 0;

        // Determine working directory
        $workingDirectory = $task->metadata_json['working_directory']
            ?? $attempt->profile->working_directory
            ?? base_path();

        // 1. Run acceptance criteria first if defined in contract
        $criteria = $task->contract_json['verification_strategy']['acceptance_criteria'] ?? [];
        if (! empty($criteria)) {
            $criteriaResult = app(AcceptanceCriteriaValidator::class)->validate($criteria, $workingDirectory);
            if (! $criteriaResult->allPassed) {
                return $this->recordResult(
                    $task,
                    $attempt,
                    $stepOrder,
                    VerificationStepResult::failed([
                        'acceptance_criteria' => $criteriaResult->toArray(),
                    ])
                );
            }
        }

        // 2. Then run check profile commands
        $checkProfile = $stepConfig['check_profile'] ?? null;

        // Get commands from the check profile
        $commands = config("delegation.check_profiles.{$checkProfile}");
        if ($commands === null) {
            // If no check profile but acceptance criteria passed, that's a pass
            if (! empty($criteria)) {
                return $this->recordResult(
                    $task,
                    $attempt,
                    $stepOrder,
                    VerificationStepResult::passed([
                        'acceptance_criteria' => $criteriaResult->toArray(),
                    ])
                );
            }

            return $this->recordResult(
                $task,
                $attempt,
                $stepOrder,
                VerificationStepResult::failed([
                    'error' => "Unknown check profile: {$checkProfile}",
                ])
            );
        }

        // Execute commands sequentially
        $commandResults = [];
        $allPassed = true;

        foreach ($commands as $command) {
            $result = Process::path($workingDirectory)
                ->timeout(config('delegation.verification_timeout_seconds', 300))
                ->run($command);

            $commandResults[] = [
                'command' => $command,
                'stdout' => $result->output(),
                'stderr' => $result->errorOutput(),
                'exit_code' => $result->exitCode(),
            ];

            // Short-circuit on first failure
            if ($result->exitCode() !== 0) {
                $allPassed = false;
                break;
            }
        }

        $evidence = ['commands' => $commandResults];
        if (! empty($criteria)) {
            $evidence['acceptance_criteria'] = $criteriaResult->toArray();
        }

        if ($allPassed) {
            return $this->recordResult(
                $task,
                $attempt,
                $stepOrder,
                VerificationStepResult::passed($evidence)
            );
        }

        return $this->recordResult(
            $task,
            $attempt,
            $stepOrder,
            VerificationStepResult::failed($evidence)
        );
    }

    /**
     * Record the verification result in the database and return the result.
     */
    private function recordResult(
        DelegationTask $task,
        DelegationAttempt $attempt,
        int $stepOrder,
        VerificationStepResult $result
    ): VerificationStepResult {
        $verdict = match (true) {
            $result->isPassed() => DelegationVerificationResult::VERDICT_PASSED,
            $result->isFailed() => DelegationVerificationResult::VERDICT_FAILED,
            default => DelegationVerificationResult::VERDICT_PENDING,
        };

        DelegationVerificationResult::create([
            'delegation_task_id' => $task->id,
            'delegation_attempt_id' => $attempt->id,
            'step_type' => DelegationVerificationResult::STEP_TYPE_AUTOMATED_CHECK,
            'step_order' => $stepOrder,
            'verdict' => $verdict,
            'evidence_json' => $result->evidence,
            'started_at' => now(),
            'finished_at' => now(),
        ]);

        return $result;
    }
}
