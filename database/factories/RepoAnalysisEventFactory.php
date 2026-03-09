<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\RepoAnalysisEvent;
use App\Models\RepoAnalysisSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RepoAnalysisEvent>
 */
class RepoAnalysisEventFactory extends Factory
{
    protected $model = RepoAnalysisEvent::class;

    public function definition(): array
    {
        return [
            'repo_analysis_session_id' => RepoAnalysisSession::factory(),
            'sequence' => $this->faker->numberBetween(1, 100),
            'event_type' => $this->faker->randomElement(['started', 'completed', 'failed', 'progress']),
            'payload_json' => [],
            'event_ts' => now(),
            'phase' => 1,
            'status' => 'completed',
            'error_code' => null,
            'error_summary' => null,
            'metadata_json' => [],
        ];
    }
}
