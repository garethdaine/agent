<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Team>
 */
class TeamFactory extends Factory
{
    protected $model = Team::class;

    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'user_id' => User::factory(),
            'personal_team' => true,
        ];
    }

    public function personal(): static
    {
        return $this->state(['personal_team' => true]);
    }

    public function shared(): static
    {
        return $this->state(['personal_team' => false]);
    }
}
