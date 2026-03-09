<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\DelegateeProfile;
use App\Models\TrustScoreHistory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TrustScoreHistory>
 */
class TrustScoreHistoryFactory extends Factory
{
    protected $model = TrustScoreHistory::class;

    public function definition(): array
    {
        return [
            'delegatee_profile_id' => DelegateeProfile::factory(),
            'score' => fake()->randomFloat(2, 0, 1),
            'components_json' => [
                'star_completion' => fake()->randomFloat(2, 0, 1),
                'task_correct' => fake()->randomFloat(2, 0, 1),
                'first_pass_success' => fake()->randomFloat(2, 0, 1),
                'recovery' => fake()->randomFloat(2, 0, 1),
            ],
            'calculated_at' => now(),
            'reason' => 'scheduled_recalculation',
        ];
    }
}
