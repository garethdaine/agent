<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\TaskCategory;
use App\Models\InterrogationBuildTask;
use App\Models\InterrogationSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InterrogationBuildTask>
 */
class InterrogationBuildTaskFactory extends Factory
{
    protected $model = InterrogationBuildTask::class;

    public function definition(): array
    {
        return [
            'interrogation_session_id' => InterrogationSession::factory(),
            'sequence' => $this->faker->numberBetween(1, 20),
            'title' => $this->faker->sentence(4),
            'description' => $this->faker->sentence(),
            'instructions_markdown' => $this->faker->paragraph(),
            'task_category' => TaskCategory::FEATURE,
            'status' => InterrogationBuildTask::STATUS_PENDING,
            'attempt_count' => 0,
            'agent_job_run_id' => null,
            'last_error' => null,
            'metadata_json' => null,
            'started_at' => null,
            'finished_at' => null,
        ];
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => InterrogationBuildTask::STATUS_COMPLETED,
            'started_at' => now()->subMinutes(30),
            'finished_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => InterrogationBuildTask::STATUS_FAILED,
            'started_at' => now()->subMinutes(30),
            'finished_at' => now(),
            'last_error' => 'Execution failed',
        ]);
    }
}
