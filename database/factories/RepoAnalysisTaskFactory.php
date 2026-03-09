<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\RepoAnalysisSession;
use App\Models\RepoAnalysisTask;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RepoAnalysisTask>
 */
class RepoAnalysisTaskFactory extends Factory
{
    protected $model = RepoAnalysisTask::class;

    public function definition(): array
    {
        return [
            'repo_analysis_session_id' => RepoAnalysisSession::factory(),
            'task_key' => fake()->slug(2),
            'task_type' => 'analysis',
            'status' => 'pending',
            'phase' => 3,
            'attempt_count' => 0,
            'max_attempts' => 3,
            'metadata_json' => [],
        ];
    }
}
