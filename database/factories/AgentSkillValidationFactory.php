<?php

namespace Database\Factories;

use App\Models\AgentSkillValidation;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AgentSkillValidation>
 */
class AgentSkillValidationFactory extends Factory
{
    protected $model = AgentSkillValidation::class;

    public function definition(): array
    {
        return [
            'skill_name' => fake()->slug(2),
            'team_id' => Team::factory(),
            'validation_result' => [
                'stages' => [],
                'warnings' => [],
                'blocking_errors' => [],
            ],
            'risk_score' => fake()->randomFloat(3, 0, 1),
            'overall_pass' => true,
            'source' => fake()->randomElement(['upload', 'url', 'library']),
            'validated_by' => null,
            'created_at' => now(),
        ];
    }

    public function failed(): static
    {
        return $this->state(fn () => [
            'overall_pass' => false,
            'risk_score' => fake()->randomFloat(3, 0.8, 1.0),
        ]);
    }
}
