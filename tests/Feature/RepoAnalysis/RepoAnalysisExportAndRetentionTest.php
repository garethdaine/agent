<?php

declare(strict_types=1);

namespace Tests\Feature\RepoAnalysis;

use App\Models\RepoAnalysisArtifact;
use App\Models\RepoAnalysisReport;
use App\Models\RepoAnalysisSession;
use App\Models\RepoAnalysisTask;
use App\Models\User;
use App\Support\RepoAnalysis\ExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Tests\TestCase;

class RepoAnalysisExportAndRetentionTest extends TestCase
{
    use RefreshDatabase;

    private string $projectRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->projectRoot = storage_path('framework/testing/code-analysis-export-'.Str::uuid());
        File::deleteDirectory($this->projectRoot);
        File::ensureDirectoryExists($this->projectRoot);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->projectRoot);
        parent::tearDown();
    }

    public function test_export_service_writes_versioned_report_files_with_collision_suffixes(): void
    {
        $session = $this->createSession('Collision Session');
        $report = $this->createReport($session);

        $service = app(ExportService::class);

        $first = $service->export($session, $report);
        $second = $service->export($session, $report->fresh());
        $third = $service->export($session, $report->fresh());

        $this->assertMatchesRegularExpression(
            '#/docs/discovery/code-analysis/collision-session\.md$#',
            $first['markdown_export_path']
        );
        $this->assertMatchesRegularExpression(
            '#/docs/discovery/code-analysis/collision-session\.json$#',
            $first['json_export_path']
        );
        $this->assertStringContainsString('## Repository Profile', File::get($first['markdown_export_path']));
        $this->assertStringContainsString('### Code Analysis Glossary', File::get($first['markdown_export_path']));

        $this->assertMatchesRegularExpression(
            '#/docs/discovery/code-analysis/collision-session-v2\.md$#',
            $second['markdown_export_path']
        );
        $this->assertMatchesRegularExpression(
            '#/docs/discovery/code-analysis/collision-session-v2\.json$#',
            $second['json_export_path']
        );

        $this->assertMatchesRegularExpression(
            '#/docs/discovery/code-analysis/collision-session-v3\.md$#',
            $third['markdown_export_path']
        );
        $this->assertMatchesRegularExpression(
            '#/docs/discovery/code-analysis/collision-session-v3\.json$#',
            $third['json_export_path']
        );
    }

    public function test_export_service_rejects_export_path_policy_violations(): void
    {
        config([
            'repo_analysis.exports.relative_directory' => '../outside',
        ]);

        $session = $this->createSession('Policy Violation');
        $report = $this->createReport($session);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Export path policy violation');

        app(ExportService::class)->export($session, $report);
    }

    public function test_retention_cleanup_deletes_only_old_task_artifacts_and_preserves_reports_and_exports(): void
    {
        config([
            'repo_analysis.retention.task_artifacts_ttl_days' => 30,
        ]);

        $session = $this->createSession('Retention Session');
        $task = RepoAnalysisTask::query()->create([
            'repo_analysis_session_id' => $session->id,
            'task_key' => 'filesystem_manifest',
            'task_type' => 'analyzer',
            'status' => 'completed',
            'phase' => 3,
            'analyzer_name' => 'filesystem_manifest',
            'analyzer_version' => '1.0.0',
            'attempt_count' => 1,
            'max_attempts' => 2,
            'input_hash' => hash('sha256', 'input'),
            'output_hash' => hash('sha256', 'output'),
            'depends_on_json' => [],
            'artifact_ids_json' => [],
            'metadata_json' => [],
        ]);

        $oldTaskArtifact = RepoAnalysisArtifact::query()->forceCreate([
            'repo_analysis_session_id' => $session->id,
            'repo_analysis_task_id' => $task->id,
            'artifact_type' => 'filesystem_manifest',
            'artifact_key' => 'filesystem_manifest.old.json',
            'content_hash' => hash('sha256', 'old-task-artifact'),
            'payload_json' => [],
            'metadata_json' => [],
            'created_at' => now('UTC')->subDays(31),
            'updated_at' => now('UTC')->subDays(31),
        ]);

        $recentTaskArtifact = RepoAnalysisArtifact::query()->create([
            'repo_analysis_session_id' => $session->id,
            'repo_analysis_task_id' => $task->id,
            'artifact_type' => 'dependency_manifest',
            'artifact_key' => 'dependency_manifest.recent.json',
            'content_hash' => hash('sha256', 'recent-task-artifact'),
            'payload_json' => [],
            'metadata_json' => [],
        ]);

        $oldSessionArtifact = RepoAnalysisArtifact::query()->forceCreate([
            'repo_analysis_session_id' => $session->id,
            'repo_analysis_task_id' => null,
            'artifact_type' => 'coverage_validation',
            'artifact_key' => 'coverage.validation.json',
            'content_hash' => hash('sha256', 'old-session-artifact'),
            'payload_json' => [],
            'metadata_json' => [],
            'created_at' => now('UTC')->subDays(45),
            'updated_at' => now('UTC')->subDays(45),
        ]);

        $reportDirectory = $this->projectRoot.'/docs/discovery/code-analysis';
        File::ensureDirectoryExists($reportDirectory);
        $markdownPath = $reportDirectory.'/retention-session.md';
        $jsonPath = $reportDirectory.'/retention-session.json';
        File::put($markdownPath, '# Retention Session');
        File::put($jsonPath, '{"session":"retention"}');

        $report = RepoAnalysisReport::query()->create([
            'repo_analysis_session_id' => $session->id,
            'report_version' => '1.0.0',
            'report_hash' => hash('sha256', 'retention-report'),
            'status' => 'generated',
            'payload_json' => ['session_id' => $session->id],
            'metadata_json' => [],
            'markdown_export_path' => $markdownPath,
            'json_export_path' => $jsonPath,
            'generated_at' => now('UTC')->subDays(45),
        ]);

        $this->artisan('code-analysis:prune-artifacts')
            ->assertExitCode(0);

        $this->assertDatabaseMissing('repo_analysis_artifacts', ['id' => $oldTaskArtifact->id]);
        $this->assertDatabaseHas('repo_analysis_artifacts', ['id' => $recentTaskArtifact->id]);
        $this->assertDatabaseHas('repo_analysis_artifacts', ['id' => $oldSessionArtifact->id]);
        $this->assertDatabaseHas('repo_analysis_reports', ['id' => $report->id]);
        $this->assertFileExists($markdownPath);
        $this->assertFileExists($jsonPath);
    }

    private function createSession(string $name): RepoAnalysisSession
    {
        $user = User::factory()->create();

        return RepoAnalysisSession::query()->create([
            'user_id' => $user->id,
            'name' => $name,
            'project_directory' => $this->projectRoot,
            'snapshot_hash' => hash('sha256', 'snapshot'),
            'phase' => 5,
            'status' => 'reporting',
        ]);
    }

    private function createReport(RepoAnalysisSession $session): RepoAnalysisReport
    {
        return RepoAnalysisReport::query()->create([
            'repo_analysis_session_id' => $session->id,
            'report_version' => '1.0.0',
            'report_hash' => hash('sha256', $session->id.'-report'),
            'status' => 'generated',
            'payload_json' => [
                'session_id' => $session->id,
                'snapshot_hash' => $session->snapshot_hash,
                'artifacts' => [],
                'repository_profile' => [
                    'overview' => [
                        'project_directory' => $session->project_directory,
                        'snapshot_hash' => $session->snapshot_hash,
                        'snapshot_file_count' => 0,
                        'inferred_stack' => ['Laravel'],
                        'language_breakdown' => ['PHP' => 10],
                    ],
                    'glossary' => [
                        'task_graph' => 'Deterministic analyzer DAG generated in phase 2, with dependency-ordered tasks executed in phase 3.',
                        'coverage_gate' => 'Phase 4 validation gate.',
                        'artifacts' => 'Versioned outputs for analysis stages.',
                    ],
                    'coverage_gate' => [
                        'passed' => true,
                        'task_count' => 1,
                        'completed_task_count' => 1,
                        'required_artifact_classes' => [],
                        'missing_artifact_classes' => [],
                        'blocking_failure_codes' => [],
                        'warning_codes' => [],
                    ],
                    'limitations' => [
                        'Static deterministic scan only.',
                    ],
                ],
            ],
            'metadata_json' => [],
            'generated_at' => now('UTC'),
        ]);
    }
}
