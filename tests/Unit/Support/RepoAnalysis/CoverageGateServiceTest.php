<?php

declare(strict_types=1);

namespace Tests\Unit\Support\RepoAnalysis;

use App\Models\RepoAnalysisArtifact;
use App\Models\RepoAnalysisSession;
use App\Models\RepoAnalysisTask;
use App\Models\User;
use App\Support\RepoAnalysis\CoverageGateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CoverageGateServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_evaluate_blocks_when_required_artifact_class_is_missing(): void
    {
        config([
            'repo_analysis.coverage.required_artifact_classes' => [
                'filesystem_manifest',
                'dependency_manifest',
            ],
        ]);

        $session = $this->createSession();

        RepoAnalysisArtifact::query()->create([
            'repo_analysis_session_id' => $session->id,
            'artifact_type' => 'filesystem_manifest',
            'artifact_key' => 'filesystem_manifest.json',
            'content_hash' => hash('sha256', 'filesystem-manifest'),
            'payload_json' => [],
            'metadata_json' => [],
        ]);

        $result = app(CoverageGateService::class)->evaluate($session->fresh());

        $this->assertFalse($result['passed']);
        $this->assertSame(['dependency_manifest'], $result['missing_artifact_classes']);
        $this->assertContains(
            'missing_required_artifact_classes',
            array_column($result['blocking_failures'], 'code')
        );
    }

    public function test_evaluate_blocks_when_critical_task_failure_exists(): void
    {
        config([
            'repo_analysis.coverage.required_artifact_classes' => [],
        ]);

        $session = $this->createSession();

        RepoAnalysisTask::query()->create([
            'repo_analysis_session_id' => $session->id,
            'task_key' => 'risk_hotspot',
            'task_type' => 'analyzer',
            'status' => 'failed',
            'phase' => 3,
            'analyzer_name' => 'risk_hotspot',
            'analyzer_version' => '1.0.0',
            'attempt_count' => 1,
            'max_attempts' => 2,
            'metadata_json' => ['severity' => 'critical'],
            'error_code' => 'ANALYZER_FAILED',
            'error_summary' => 'Critical analyzer failed.',
        ]);

        $result = app(CoverageGateService::class)->evaluate($session->fresh());

        $this->assertFalse($result['passed']);
        $this->assertContains(
            'critical_task_failure_present',
            array_column($result['blocking_failures'], 'code')
        );
    }

    public function test_evaluate_passes_with_warning_when_no_tests_mapping_is_reported(): void
    {
        config([
            'repo_analysis.coverage.required_artifact_classes' => [
                'filesystem_manifest',
                'test_coverage_map',
            ],
        ]);

        $session = $this->createSession();

        RepoAnalysisArtifact::query()->create([
            'repo_analysis_session_id' => $session->id,
            'artifact_type' => 'filesystem_manifest',
            'artifact_key' => 'filesystem_manifest.json',
            'content_hash' => hash('sha256', 'filesystem-manifest'),
            'payload_json' => [],
            'metadata_json' => [],
        ]);

        RepoAnalysisArtifact::query()->create([
            'repo_analysis_session_id' => $session->id,
            'artifact_type' => 'test_coverage_map',
            'artifact_key' => 'test_coverage_map.json',
            'content_hash' => hash('sha256', 'test-coverage-map'),
            'payload_json' => [
                'warnings' => [
                    [
                        'code' => 'empty_test_suite',
                        'message' => 'No tests discovered for coverage mapping.',
                    ],
                ],
            ],
            'metadata_json' => [],
        ]);

        $result = app(CoverageGateService::class)->evaluate($session->fresh());

        $this->assertTrue($result['passed']);
        $this->assertSame([], $result['blocking_failures']);
        $this->assertContains('empty_test_suite', array_column($result['warnings'], 'code'));
    }

    private function createSession(): RepoAnalysisSession
    {
        $user = User::factory()->create();

        return RepoAnalysisSession::query()->create([
            'user_id' => $user->id,
            'project_directory' => base_path(),
            'snapshot_hash' => hash('sha256', 'snapshot'),
            'status' => 'validating',
            'phase' => 4,
        ]);
    }
}

