<?php

declare(strict_types=1);

namespace Tests\Unit\Support\RepoAnalysis;

use App\Models\RepoAnalysisArtifact;
use App\Models\RepoAnalysisSession;
use App\Models\User;
use App\Support\RepoAnalysis\ReportComposer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportComposerTest extends TestCase
{
    use RefreshDatabase;

    public function test_compose_generates_same_hash_for_same_artifacts_regardless_of_insert_order(): void
    {
        $session = $this->createSession();

        $this->seedArtifacts($session, [
            ['artifact_key' => 'z-risk.json', 'artifact_type' => 'risk_hotspot', 'content_hash' => hash('sha256', 'z')],
            ['artifact_key' => 'a-filesystem.json', 'artifact_type' => 'filesystem_manifest', 'content_hash' => hash('sha256', 'a')],
            ['artifact_key' => 'm-tests.json', 'artifact_type' => 'test_coverage_map', 'content_hash' => hash('sha256', 'm')],
        ]);

        $first = app(ReportComposer::class)->compose($session->fresh(), []);

        RepoAnalysisArtifact::query()
            ->where('repo_analysis_session_id', $session->id)
            ->delete();

        $this->seedArtifacts($session, [
            ['artifact_key' => 'm-tests.json', 'artifact_type' => 'test_coverage_map', 'content_hash' => hash('sha256', 'm')],
            ['artifact_key' => 'z-risk.json', 'artifact_type' => 'risk_hotspot', 'content_hash' => hash('sha256', 'z')],
            ['artifact_key' => 'a-filesystem.json', 'artifact_type' => 'filesystem_manifest', 'content_hash' => hash('sha256', 'a')],
        ]);

        $second = app(ReportComposer::class)->compose($session->fresh(), []);

        $this->assertSame($first['report_hash'], $second['report_hash']);
        $this->assertSame(
            ['a-filesystem.json', 'm-tests.json', 'z-risk.json'],
            array_column($second['payload'], 'artifact_key')
        );
    }

    public function test_compose_hash_changes_when_artifact_hash_changes(): void
    {
        $session = $this->createSession();

        $artifact = RepoAnalysisArtifact::query()->create([
            'repo_analysis_session_id' => $session->id,
            'artifact_type' => 'filesystem_manifest',
            'artifact_key' => 'filesystem_manifest.json',
            'content_hash' => hash('sha256', 'v1'),
            'payload_json' => [],
            'metadata_json' => [],
        ]);

        $first = app(ReportComposer::class)->compose($session->fresh(), []);

        $artifact->update([
            'content_hash' => hash('sha256', 'v2'),
        ]);

        $second = app(ReportComposer::class)->compose($session->fresh(), []);

        $this->assertNotSame($first['report_hash'], $second['report_hash']);
    }

    public function test_compose_includes_repository_profile_glossary_for_human_readability(): void
    {
        $session = $this->createSession();

        $composed = app(ReportComposer::class)->compose($session->fresh(), [
            'passed' => true,
            'task_count' => 4,
            'completed_task_count' => 4,
        ]);

        $this->assertIsArray($composed['payload_json']['repository_profile'] ?? null);
        $this->assertSame(
            'Deterministic analyzer DAG generated in phase 2, with dependency-ordered tasks executed in phase 3.',
            data_get($composed, 'payload_json.repository_profile.glossary.task_graph')
        );
        $this->assertSame(
            true,
            data_get($composed, 'payload_json.repository_profile.coverage_gate.passed')
        );
    }

    /**
     * @param  array<int, array{artifact_key: string, artifact_type: string, content_hash: string}>  $artifacts
     */
    private function seedArtifacts(RepoAnalysisSession $session, array $artifacts): void
    {
        foreach ($artifacts as $artifact) {
            RepoAnalysisArtifact::query()->create([
                'repo_analysis_session_id' => $session->id,
                'artifact_type' => $artifact['artifact_type'],
                'artifact_key' => $artifact['artifact_key'],
                'content_hash' => $artifact['content_hash'],
                'payload_json' => [],
                'metadata_json' => [],
            ]);
        }
    }

    private function createSession(): RepoAnalysisSession
    {
        $user = User::factory()->create();

        return RepoAnalysisSession::query()->create([
            'user_id' => $user->id,
            'name' => 'Deterministic Report Session',
            'project_directory' => base_path(),
            'snapshot_hash' => hash('sha256', 'snapshot-stable'),
            'phase' => 5,
            'status' => 'reporting',
        ]);
    }
}
