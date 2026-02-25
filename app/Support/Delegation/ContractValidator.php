<?php

namespace App\Support\Delegation;

use App\Models\DelegationCapability;

/**
 * Validates delegation contract configurations against business rules.
 *
 * Validation Rules:
 * - required_capability must reference an active DelegationCapability (by slug)
 * - authority_scope.max_runtime_seconds <= 86400 (24 hours)
 * - criticality must be valid enum: low, medium, high, critical
 * - Either prompt or task_markdown_path must be present (not both)
 * - verification_strategy.automated_check.check_profile must exist in config
 * - verification_strategy.human_approval.timeout_hours <= 4
 *
 * Conditions for Correctness:
 * - DelegationCapability model must have 'slug' column and 'active' scope
 * - config('delegation.check_profiles') must be properly defined
 *
 * Not Handled:
 * - Path validation for task_markdown_path (done separately by PathPolicy)
 * - Time constraint deadline validation (future timestamp check)
 * - Deep validation of verification_strategy.ai_critic config
 */
class ContractValidator
{
    private const MAX_RUNTIME_SECONDS = 86400; // 24 hours

    private const MAX_HUMAN_APPROVAL_TIMEOUT_HOURS = 4;

    private const VALID_CRITICALITY_VALUES = ['low', 'medium', 'high', 'critical'];

    /**
     * Validate a contract configuration.
     *
     * @param  array<string, mixed>  $contractJson
     */
    public function validate(array $contractJson): ValidationResult
    {
        $errors = [];
        $warnings = [];

        $errors = array_merge($errors, $this->validateCapability($contractJson));
        $errors = array_merge($errors, $this->validateMaxRuntime($contractJson));
        $errors = array_merge($errors, $this->validateCriticality($contractJson));
        $errors = array_merge($errors, $this->validatePromptOrPath($contractJson));
        $errors = array_merge($errors, $this->validateVerificationStrategy($contractJson));

        if (count($errors) > 0) {
            return ValidationResult::failed($errors, $warnings);
        }

        return ValidationResult::passed($warnings);
    }

    /**
     * Validate required_capability references an active capability.
     *
     * @param  array<string, mixed>  $contractJson
     * @return array<int, string>
     */
    private function validateCapability(array $contractJson): array
    {
        if (! isset($contractJson['required_capability'])) {
            return [];
        }

        $slug = $contractJson['required_capability'];

        $exists = DelegationCapability::query()
            ->active()
            ->where('slug', $slug)
            ->exists();

        if (! $exists) {
            return ["Capability '{$slug}' not found or inactive"];
        }

        return [];
    }

    /**
     * Validate max_runtime_seconds does not exceed 24 hours.
     *
     * @param  array<string, mixed>  $contractJson
     * @return array<int, string>
     */
    private function validateMaxRuntime(array $contractJson): array
    {
        $maxRuntime = $contractJson['authority_scope']['max_runtime_seconds'] ?? 0;

        if ($maxRuntime > self::MAX_RUNTIME_SECONDS) {
            return ['max_runtime_seconds cannot exceed 86400 (24 hours)'];
        }

        return [];
    }

    /**
     * Validate criticality value is in allowed enum.
     *
     * @param  array<string, mixed>  $contractJson
     * @return array<int, string>
     */
    private function validateCriticality(array $contractJson): array
    {
        if (! isset($contractJson['criticality'])) {
            return [];
        }

        if (! in_array($contractJson['criticality'], self::VALID_CRITICALITY_VALUES, true)) {
            return ['Invalid criticality value'];
        }

        return [];
    }

    /**
     * Validate either prompt or task_markdown_path is present, but not both.
     *
     * @param  array<string, mixed>  $contractJson
     * @return array<int, string>
     */
    private function validatePromptOrPath(array $contractJson): array
    {
        $hasPrompt = ! empty($contractJson['prompt']);
        $hasPath = ! empty($contractJson['task_markdown_path']);

        $errors = [];

        if (! $hasPrompt && ! $hasPath) {
            $errors[] = 'Either prompt or task_markdown_path is required';
        }

        if ($hasPrompt && $hasPath) {
            $errors[] = 'Cannot specify both prompt and task_markdown_path';
        }

        return $errors;
    }

    /**
     * Validate verification_strategy configuration.
     *
     * @param  array<string, mixed>  $contractJson
     * @return array<int, string>
     */
    private function validateVerificationStrategy(array $contractJson): array
    {
        if (! isset($contractJson['verification_strategy'])) {
            return [];
        }

        $strategy = $contractJson['verification_strategy'];
        $errors = [];

        // Validate automated_check.check_profile exists
        if (isset($strategy['automated_check']['check_profile'])) {
            $profile = $strategy['automated_check']['check_profile'];
            $availableProfiles = config('delegation.check_profiles', []);

            if (! array_key_exists($profile, $availableProfiles)) {
                $errors[] = "Check profile '{$profile}' not found in configuration";
            }
        }

        // Validate human_approval.timeout_hours <= 4
        if (isset($strategy['human_approval']['timeout_hours'])) {
            $timeout = $strategy['human_approval']['timeout_hours'];

            if ($timeout > self::MAX_HUMAN_APPROVAL_TIMEOUT_HOURS) {
                $errors[] = 'human_approval timeout_hours cannot exceed 4';
            }
        }

        return $errors;
    }
}
