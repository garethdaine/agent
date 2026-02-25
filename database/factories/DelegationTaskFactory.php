<?php

namespace Database\Factories;

use App\Models\DelegationGraph;
use App\Models\DelegationTask;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DelegationTask>
 */
class DelegationTaskFactory extends Factory
{
    protected $model = DelegationTask::class;

    public function definition(): array
    {
        return [
            'delegation_graph_id' => DelegationGraph::factory(),
            'name' => fake()->words(2, true),
            'status' => DelegationTask::STATUS_PENDING,
            'sequence_order' => 0,
            'contract_json' => [
                'required_capability' => 'code_execution',
                'prompt' => fake()->paragraph(),
            ],
            'assigned_delegatee_profile_id' => null,
            'assignment_reason_json' => null,
            'metadata_json' => null,
            'error_code' => null,
            'error_summary' => null,
            'started_at' => null,
            'finished_at' => null,
        ];
    }

    public function ready(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DelegationTask::STATUS_READY,
        ]);
    }

    public function running(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DelegationTask::STATUS_RUNNING,
            'started_at' => now(),
        ]);
    }

    public function succeeded(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DelegationTask::STATUS_SUCCEEDED,
            'started_at' => now()->subMinutes(30),
            'finished_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DelegationTask::STATUS_FAILED,
            'started_at' => now()->subMinutes(30),
            'finished_at' => now(),
            'error_code' => 'EXECUTION_FAILED',
            'error_summary' => 'Task execution failed.',
        ]);
    }

    public function blocked(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DelegationTask::STATUS_BLOCKED,
        ]);
    }

    public function withSequenceOrder(int $order): static
    {
        return $this->state(fn (array $attributes) => [
            'sequence_order' => $order,
        ]);
    }
}
