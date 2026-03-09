<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\AgentSystemState;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AgentSystemState>
 */
class AgentSystemStateFactory extends Factory
{
    protected $model = AgentSystemState::class;

    public function definition(): array
    {
        return [
            'key' => $this->faker->unique()->slug(2),
            'value' => $this->faker->word(),
            'updated_at' => now(),
        ];
    }
}
