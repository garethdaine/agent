<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\AgentFeatureSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AgentFeatureSetting>
 */
class AgentFeatureSettingFactory extends Factory
{
    protected $model = AgentFeatureSetting::class;

    public function definition(): array
    {
        return [
            'key' => $this->faker->unique()->slug(2),
            'is_enabled' => true,
            'updated_by_user_id' => null,
        ];
    }

    public function disabled(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_enabled' => false,
        ]);
    }
}
