<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\DelegationCapability;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DelegationCapability>
 */
class DelegationCapabilityFactory extends Factory
{
    protected $model = DelegationCapability::class;

    public function definition(): array
    {
        return [
            'slug' => fake()->unique()->slug(2),
            'name' => fake()->words(2, true),
            'description' => fake()->sentence(),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
