<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\RepoAnalysisArtifact;
use App\Models\RepoAnalysisSession;
use App\Models\RepoAnalysisTask;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RepoAnalysisArtifact>
 */
class RepoAnalysisArtifactFactory extends Factory
{
    protected $model = RepoAnalysisArtifact::class;

    public function definition(): array
    {
        return [
            'repo_analysis_session_id' => RepoAnalysisSession::factory(),
            'repo_analysis_task_id' => RepoAnalysisTask::factory(),
            'artifact_type' => $this->faker->randomElement(['architecture_map', 'dependency_graph', 'quality_report']),
            'artifact_key' => $this->faker->unique()->slug(2),
            'content_hash' => hash('sha256', $this->faker->sentence()),
            'schema_version' => '1.0',
            'analyzer_version' => '1.0.0',
            'storage_disk' => 'local',
            'storage_path' => 'artifacts/'.$this->faker->uuid().'.json',
            'payload_json' => [],
            'metadata_json' => [],
            'error_code' => null,
            'error_summary' => null,
        ];
    }
}
