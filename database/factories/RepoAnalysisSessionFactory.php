<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\RepoAnalysisSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RepoAnalysisSession>
 */
class RepoAnalysisSessionFactory extends Factory
{
    protected $model = RepoAnalysisSession::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->sentence(3),
            'project_directory' => '/tmp/test-project-'.fake()->uuid(),
            'analyzer_profile' => 'default',
            'runner_type' => 'claude',
            'status' => 'setup',
            'phase' => 0,
            'manifest_stats_json' => [],
            'report_summary_json' => [],
            'metadata_json' => [],
        ];
    }
}
