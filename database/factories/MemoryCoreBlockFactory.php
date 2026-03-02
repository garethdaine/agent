<?php

namespace Database\Factories;

use App\Models\MemoryCoreBlock;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MemoryCoreBlock>
 */
class MemoryCoreBlockFactory extends Factory
{
    protected $model = MemoryCoreBlock::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'job_id' => null,
            'block_type' => MemoryCoreBlock::TYPE_OPERATIONAL,
            'block_key' => $this->faker->randomElement(['task_state', 'tool_results_cache', 'active_goals']),
            'content_text' => null,
            'content_json' => ['data' => $this->faker->sentence()],
            'classification' => MemoryCoreBlock::CLASSIFICATION_INTERNAL,
            'version' => 1,
        ];
    }

    public function identity(): static
    {
        return $this->state(fn (array $attributes) => [
            'block_type' => MemoryCoreBlock::TYPE_IDENTITY,
            'block_key' => $this->faker->randomElement(['agent_persona', 'user_profile']),
            'content_text' => $this->faker->paragraph(),
            'content_json' => null,
        ]);
    }

    public function operational(): static
    {
        return $this->state(fn (array $attributes) => [
            'block_type' => MemoryCoreBlock::TYPE_OPERATIONAL,
            'block_key' => $this->faker->randomElement(['task_state', 'tool_results_cache', 'active_goals']),
            'content_json' => ['data' => $this->faker->sentence()],
            'content_text' => null,
        ]);
    }

    public function public(): static
    {
        return $this->state(fn (array $attributes) => [
            'classification' => MemoryCoreBlock::CLASSIFICATION_PUBLIC,
        ]);
    }

    public function confidential(): static
    {
        return $this->state(fn (array $attributes) => [
            'classification' => MemoryCoreBlock::CLASSIFICATION_CONFIDENTIAL,
        ]);
    }
}
