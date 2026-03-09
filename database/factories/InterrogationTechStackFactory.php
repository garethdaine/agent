<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\InterrogationSession;
use App\Models\InterrogationTechStack;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InterrogationTechStack>
 */
class InterrogationTechStackFactory extends Factory
{
    protected $model = InterrogationTechStack::class;

    public function definition(): array
    {
        return [
            'interrogation_session_id' => InterrogationSession::factory(),
            'sequence' => $this->faker->numberBetween(1, 10),
            'name' => $this->faker->randomElement(['Laravel', 'Vue.js', 'PostgreSQL', 'Redis', 'Tailwind CSS']),
            'documentation_url' => $this->faker->optional()->url(),
            'metadata_json' => null,
        ];
    }
}
