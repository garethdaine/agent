<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\DelegateeProfile;
use App\Models\DelegationAttempt;
use App\Models\DelegationTask;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DelegationAttempt>
 */
class DelegationAttemptFactory extends Factory
{
    protected $model = DelegationAttempt::class;

    public function definition(): array
    {
        return [
            'delegation_task_id' => DelegationTask::factory(),
            'delegatee_profile_id' => DelegateeProfile::factory(),
            'agent_job_run_id' => null,
            'attempt_number' => 1,
            'status' => DelegationAttempt::STATUS_RUNNING,
            'started_at' => now(),
            'finished_at' => null,
            'duration_ms' => 0,
            'error_code' => null,
            'error_summary' => null,
            'metadata_json' => null,
        ];
    }

    public function running(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DelegationAttempt::STATUS_RUNNING,
            'started_at' => now(),
            'finished_at' => null,
            'duration_ms' => 0,
        ]);
    }

    public function succeeded(): static
    {
        $startedAt = now()->subMinutes(10);
        $finishedAt = now();
        $durationMs = (int) $startedAt->diffInMilliseconds($finishedAt);

        return $this->state(fn (array $attributes) => [
            'status' => DelegationAttempt::STATUS_SUCCEEDED,
            'started_at' => $startedAt,
            'finished_at' => $finishedAt,
            'duration_ms' => $durationMs,
        ]);
    }

    public function failed(): static
    {
        $startedAt = now()->subMinutes(10);
        $finishedAt = now();
        $durationMs = (int) $startedAt->diffInMilliseconds($finishedAt);

        return $this->state(fn (array $attributes) => [
            'status' => DelegationAttempt::STATUS_FAILED,
            'started_at' => $startedAt,
            'finished_at' => $finishedAt,
            'duration_ms' => $durationMs,
            'error_code' => 'EXECUTION_ERROR',
            'error_summary' => 'Attempt failed during execution.',
        ]);
    }

    public function withAttemptNumber(int $number): static
    {
        return $this->state(fn (array $attributes) => [
            'attempt_number' => $number,
        ]);
    }
}
