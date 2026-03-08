<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Security\DetectionPatternType;
use App\Enums\Security\InjectionSeverity;
use App\Models\Security\SecurityDetectionRule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SecurityDetectionRule>
 */
class SecurityDetectionRuleFactory extends Factory
{
    protected $model = SecurityDetectionRule::class;

    public function definition(): array
    {
        return [
            'pattern' => '/'.fake()->word().'/i',
            'pattern_type' => fake()->randomElement(DetectionPatternType::cases()),
            'severity' => fake()->randomElement(InjectionSeverity::cases()),
            'weight' => fake()->randomFloat(2, 0.1, 1.0),
            'enabled' => true,
        ];
    }

    public function disabled(): static
    {
        return $this->state(fn () => [
            'enabled' => false,
        ]);
    }
}
