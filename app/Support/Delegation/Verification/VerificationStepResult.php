<?php

namespace App\Support\Delegation\Verification;

/**
 * Data Transfer Object representing the result of a verification step execution.
 *
 * Verification steps return one of three states:
 * - passed: The verification check succeeded
 * - failed: The verification check failed
 * - pending: The verification requires async completion (e.g., AI critic, human approval)
 *
 * Evidence is captured for passed/failed states to provide context about the result.
 */
class VerificationStepResult
{
    private function __construct(
        public readonly string $status,
        public readonly ?array $evidence = null
    ) {}

    /**
     * Create a passed result with optional evidence.
     *
     * @param  array  $evidence  Evidence data captured during verification
     */
    public static function passed(array $evidence = []): self
    {
        return new self('passed', $evidence);
    }

    /**
     * Create a failed result with optional evidence.
     *
     * @param  array  $evidence  Evidence data explaining the failure
     */
    public static function failed(array $evidence = []): self
    {
        return new self('failed', $evidence);
    }

    /**
     * Create a pending result for async verification steps.
     *
     * Pending results indicate that verification requires external completion,
     * such as waiting for an AI critic review or human approval.
     */
    public static function pending(): self
    {
        return new self('pending');
    }

    /**
     * Check if the verification passed.
     */
    public function isPassed(): bool
    {
        return $this->status === 'passed';
    }

    /**
     * Check if the verification failed.
     */
    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    /**
     * Check if the verification is pending (async).
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Check if the result is terminal (passed or failed, not pending).
     */
    public function isTerminal(): bool
    {
        return ! $this->isPending();
    }
}
