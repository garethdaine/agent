<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\NlOrgParseAttempt;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NlOrgParseAttempt>
 */
class NlOrgParseAttemptFactory extends Factory
{
    protected $model = NlOrgParseAttempt::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'raw_input' => $this->faker->sentence(),
            'parsed_result' => ['action' => 'create', 'entity' => $this->faker->word()],
            'confidence' => $this->faker->randomFloat(2, 0.5, 1.0),
            'applied_at' => null,
        ];
    }
}
