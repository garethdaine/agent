<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\AgentJob;
use App\Models\AgentJobRun;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AgentJobRun>
 */
class AgentJobRunFactory extends Factory
{
    protected $model = AgentJobRun::class;

    public function definition(): array
    {
        $user = User::factory()->create();

        return [
            'agent_job_id' => AgentJob::factory()->state(['user_id' => $user->id]),
            'user_id' => $user->id,
            'initiated_by_user_id' => null,
            'trigger_type' => AgentJobRun::TRIGGER_MANUAL,
            'due_window_utc_minute' => null,
            'status' => AgentJobRun::STATUS_QUEUED,
            'pid' => null,
            'resolved_executable_path' => null,
            'started_at' => null,
            'finished_at' => null,
            'exit_code' => null,
            'signal' => null,
            'duration_ms' => 0,
            'stdout_bytes_pre' => 0,
            'stdout_bytes_post' => 0,
            'stderr_bytes_pre' => 0,
            'stderr_bytes_post' => 0,
            'error_summary' => null,
            'error_code' => null,
            'metadata_json' => null,
        ];
    }

    public function running(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AgentJobRun::STATUS_RUNNING,
            'started_at' => now(),
            'pid' => fake()->numberBetween(1000, 99999),
        ]);
    }

    public function succeeded(): static
    {
        $startedAt = now()->subMinutes(10);
        $finishedAt = now();

        return $this->state(fn (array $attributes) => [
            'status' => AgentJobRun::STATUS_SUCCEEDED,
            'started_at' => $startedAt,
            'finished_at' => $finishedAt,
            'exit_code' => 0,
            'duration_ms' => (int) $startedAt->diffInMilliseconds($finishedAt),
        ]);
    }

    public function failed(): static
    {
        $startedAt = now()->subMinutes(10);
        $finishedAt = now();

        return $this->state(fn (array $attributes) => [
            'status' => AgentJobRun::STATUS_FAILED,
            'started_at' => $startedAt,
            'finished_at' => $finishedAt,
            'exit_code' => 1,
            'duration_ms' => (int) $startedAt->diffInMilliseconds($finishedAt),
            'error_code' => 'EXECUTION_FAILED',
            'error_summary' => 'Run failed during execution.',
        ]);
    }
}
