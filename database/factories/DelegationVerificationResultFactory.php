<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\DelegationAttempt;
use App\Models\DelegationTask;
use App\Models\DelegationVerificationResult;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DelegationVerificationResult>
 */
class DelegationVerificationResultFactory extends Factory
{
    protected $model = DelegationVerificationResult::class;

    public function definition(): array
    {
        return [
            'delegation_task_id' => DelegationTask::factory(),
            'delegation_attempt_id' => DelegationAttempt::factory(),
            'step_type' => DelegationVerificationResult::STEP_TYPE_AUTOMATED_CHECK,
            'step_order' => 0,
            'verdict' => DelegationVerificationResult::VERDICT_PENDING,
            'evidence_json' => null,
            'started_at' => now(),
            'finished_at' => null,
        ];
    }

    public function automatedCheck(): static
    {
        return $this->state(fn (array $attributes) => [
            'step_type' => DelegationVerificationResult::STEP_TYPE_AUTOMATED_CHECK,
        ]);
    }

    public function aiCritic(): static
    {
        return $this->state(fn (array $attributes) => [
            'step_type' => DelegationVerificationResult::STEP_TYPE_AI_CRITIC,
        ]);
    }

    public function humanApproval(): static
    {
        return $this->state(fn (array $attributes) => [
            'step_type' => DelegationVerificationResult::STEP_TYPE_HUMAN_APPROVAL,
        ]);
    }

    public function passed(): static
    {
        return $this->state(fn (array $attributes) => [
            'verdict' => DelegationVerificationResult::VERDICT_PASSED,
            'finished_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'verdict' => DelegationVerificationResult::VERDICT_FAILED,
            'finished_at' => now(),
        ]);
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'verdict' => DelegationVerificationResult::VERDICT_PENDING,
            'finished_at' => null,
        ]);
    }

    public function withStepOrder(int $order): static
    {
        return $this->state(fn (array $attributes) => [
            'step_order' => $order,
        ]);
    }

    public function withEvidence(array $evidence): static
    {
        return $this->state(fn (array $attributes) => [
            'evidence_json' => $evidence,
        ]);
    }

    public function withExpiresAt(\DateTimeInterface $expiresAt): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => $expiresAt,
        ]);
    }
}
