<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\RepoAnalysisReport;
use App\Models\RepoAnalysisSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RepoAnalysisReport>
 */
class RepoAnalysisReportFactory extends Factory
{
    protected $model = RepoAnalysisReport::class;

    public function definition(): array
    {
        return [
            'repo_analysis_session_id' => RepoAnalysisSession::factory(),
            'report_version' => '1.0',
            'report_hash' => hash('sha256', $this->faker->sentence()),
            'status' => 'generated',
            'payload_json' => [],
            'metadata_json' => [],
            'markdown_export_path' => null,
            'json_export_path' => null,
            'error_code' => null,
            'error_summary' => null,
            'generated_at' => now(),
        ];
    }
}
