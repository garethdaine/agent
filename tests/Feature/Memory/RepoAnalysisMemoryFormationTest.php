<?php

declare(strict_types=1);

namespace Tests\Feature\Memory;

use App\Models\MemoryConversationLog;
use App\Models\RepoAnalysisArtifact;
use App\Models\RepoAnalysisSession;
use App\Models\RepoAnalysisTask;
use App\Models\User;
use App\Support\Memory\Contracts\EmbeddingProvider;
use App\Support\Memory\Contracts\ExtractionProvider;
use App\Support\Memory\EntitySanitizer;
use App\Support\Memory\MemoryFormationPipeline;
use App\Support\Memory\Neo4jGraphStore;
use App\Support\Memory\WorkingMemoryBuffer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

/**
 * Tests for RepoAnalysisSession memory formation pipeline.
 *
 * Verifies:
 * - Builds entries from completed RepoAnalysisTask records
 * - Builds entries from RepoAnalysisArtifact payloads
 * - Persists conversation logs with source_type identification
 * - Handles empty sessions gracefully
 * - Runs extraction/embedding/graph when API enabled
 * - Skips tasks that are not completed
 * - Includes session name as context entry
 */
class RepoAnalysisMemoryFormationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private RepoAnalysisSession $session;

    protected function setUp(): void
    {
        parent::setUp();

        config(['memory.enabled' => true]);
        config(['memory.api_enabled' => false]);

        $this->user = User::factory()->create();
        $this->session = RepoAnalysisSession::factory()->for($this->user)->create([
            'name' => 'Laravel Agent Repository Analysis',
            'status' => 'completed',
        ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Test that completed tasks are converted to conversation log entries.
     */
    public function test_builds_entries_from_completed_tasks(): void
    {
        RepoAnalysisTask::factory()->for($this->session, 'session')->create([
            'analyzer_name' => 'architecture',
            'task_key' => 'architecture-overview',
            'status' => 'completed',
            'metadata_json' => [
                'section_title' => 'Architecture Overview',
                'output' => 'The application follows a layered architecture pattern.',
            ],
            'finished_at' => now(),
        ]);

        $pipeline = $this->createPipeline();
        $result = $pipeline->processRepoAnalysisSession($this->session);

        $this->assertTrue($result->success);
        // Should have: session context + task completion + task output = 3 entries
        $this->assertGreaterThanOrEqual(2, $result->conversationLogsCreated);

        $logs = MemoryConversationLog::where('source_type', MemoryConversationLog::SOURCE_REPO_ANALYSIS)
            ->where('source_id', (string) $this->session->id)
            ->get();

        // Check that task output is included
        $outputLog = $logs->first(fn ($log) => str_contains($log->content, 'layered architecture'));
        $this->assertNotNull($outputLog);
        $this->assertEquals('assistant', $outputLog->role);
    }

    /**
     * Test that pending/failed tasks are not included.
     */
    public function test_skips_non_completed_tasks(): void
    {
        RepoAnalysisTask::factory()->for($this->session, 'session')->create([
            'analyzer_name' => 'dependencies',
            'task_key' => 'dependency-analysis',
            'status' => 'pending',
            'metadata_json' => [
                'output' => 'This should not appear.',
            ],
        ]);

        RepoAnalysisTask::factory()->for($this->session, 'session')->create([
            'analyzer_name' => 'quality',
            'task_key' => 'quality-check',
            'status' => 'failed',
            'metadata_json' => [
                'output' => 'This should also not appear.',
            ],
        ]);

        $pipeline = $this->createPipeline();
        $result = $pipeline->processRepoAnalysisSession($this->session);

        $this->assertTrue($result->success);

        $logs = MemoryConversationLog::where('source_type', MemoryConversationLog::SOURCE_REPO_ANALYSIS)
            ->where('source_id', (string) $this->session->id)
            ->get();

        // Only session context entry should be present (no task entries)
        foreach ($logs as $log) {
            $this->assertStringNotContainsString('This should not appear', $log->content);
            $this->assertStringNotContainsString('This should also not appear', $log->content);
        }
    }

    /**
     * Test that artifacts with payloads are included as entries.
     */
    public function test_builds_entries_from_artifact_payloads(): void
    {
        $task = RepoAnalysisTask::factory()->for($this->session, 'session')->create([
            'analyzer_name' => 'architecture',
            'task_key' => 'arch-map',
            'status' => 'completed',
            'finished_at' => now(),
        ]);

        RepoAnalysisArtifact::factory()
            ->for($this->session, 'session')
            ->for($task, 'task')
            ->create([
                'artifact_type' => 'architecture_map',
                'artifact_key' => 'main-architecture',
                'payload_json' => [
                    'summary' => 'Application uses MVC with service layer pattern.',
                ],
            ]);

        $pipeline = $this->createPipeline();
        $result = $pipeline->processRepoAnalysisSession($this->session);

        $this->assertTrue($result->success);

        $logs = MemoryConversationLog::where('source_type', MemoryConversationLog::SOURCE_REPO_ANALYSIS)
            ->where('source_id', (string) $this->session->id)
            ->get();

        $artifactLog = $logs->first(fn ($log) => str_contains($log->content, 'MVC with service layer'));
        $this->assertNotNull($artifactLog);
        $this->assertEquals('assistant', $artifactLog->role);
    }

    /**
     * Test that artifacts with empty payloads are skipped.
     */
    public function test_skips_artifacts_with_empty_payloads(): void
    {
        $task = RepoAnalysisTask::factory()->for($this->session, 'session')->create([
            'status' => 'completed',
            'finished_at' => now(),
        ]);

        RepoAnalysisArtifact::factory()
            ->for($this->session, 'session')
            ->for($task, 'task')
            ->create([
                'payload_json' => [],
            ]);

        $pipeline = $this->createPipeline();
        $result = $pipeline->processRepoAnalysisSession($this->session);

        $this->assertTrue($result->success);

        // Only session context + task entries, no artifact entries
        $logs = MemoryConversationLog::where('source_type', MemoryConversationLog::SOURCE_REPO_ANALYSIS)
            ->where('source_id', (string) $this->session->id)
            ->get();

        foreach ($logs as $log) {
            $this->assertStringNotContainsString('artifact', strtolower($log->content));
        }
    }

    /**
     * Test that session name is included as context.
     */
    public function test_includes_session_name_as_context(): void
    {
        $pipeline = $this->createPipeline();
        $result = $pipeline->processRepoAnalysisSession($this->session);

        $this->assertTrue($result->success);

        $logs = MemoryConversationLog::where('source_type', MemoryConversationLog::SOURCE_REPO_ANALYSIS)
            ->where('source_id', (string) $this->session->id)
            ->get();

        $contextLog = $logs->first(fn ($log) => str_contains($log->content, 'Laravel Agent Repository Analysis'));
        $this->assertNotNull($contextLog);
        $this->assertEquals('system', $contextLog->role);
    }

    /**
     * Test that empty sessions return success with zero logs.
     */
    public function test_empty_session_returns_success(): void
    {
        // Session with no name, no tasks, no artifacts
        $emptySession = RepoAnalysisSession::factory()->for($this->user)->create([
            'name' => '',
            'status' => 'completed',
        ]);

        $pipeline = $this->createPipeline();
        $result = $pipeline->processRepoAnalysisSession($emptySession);

        $this->assertTrue($result->success);
        $this->assertEquals(0, $result->conversationLogsCreated);
    }

    /**
     * Test that logs use source_type and source_id columns.
     */
    public function test_persists_with_source_type_columns(): void
    {
        RepoAnalysisTask::factory()->for($this->session, 'session')->create([
            'analyzer_name' => 'dependencies',
            'task_key' => 'dep-scan',
            'status' => 'completed',
            'metadata_json' => ['output' => 'Found 42 dependencies.'],
            'finished_at' => now(),
        ]);

        $pipeline = $this->createPipeline();
        $pipeline->processRepoAnalysisSession($this->session);

        $log = MemoryConversationLog::where('source_type', MemoryConversationLog::SOURCE_REPO_ANALYSIS)->first();

        $this->assertNotNull($log);
        $this->assertNull($log->run_id);
        $this->assertNull($log->job_id);
        $this->assertNull($log->runtime_session_id);
        $this->assertEquals(MemoryConversationLog::SOURCE_REPO_ANALYSIS, $log->source_type);
        $this->assertEquals((string) $this->session->id, $log->source_id);
        $this->assertEquals($this->user->id, $log->user_id);
    }

    /**
     * Test that extraction runs when API mode is enabled.
     */
    public function test_runs_extraction_when_api_enabled(): void
    {
        config(['memory.api_enabled' => true]);

        RepoAnalysisTask::factory()->for($this->session, 'session')->create([
            'analyzer_name' => 'architecture',
            'task_key' => 'arch-overview',
            'status' => 'completed',
            'metadata_json' => [
                'section_title' => 'Architecture',
                'output' => 'Uses Laravel framework with PostgreSQL database.',
            ],
            'finished_at' => now(),
        ]);

        $mockExtraction = Mockery::mock(ExtractionProvider::class);
        $mockExtraction->shouldReceive('getProviderName')->andReturn('test');
        $mockExtraction->shouldReceive('extractEntities')
            ->once()
            ->andReturn([
                ['type' => 'Dependency', 'name' => 'Laravel', 'confidence' => 0.95],
                ['type' => 'Dependency', 'name' => 'PostgreSQL', 'confidence' => 0.9],
            ]);
        $mockExtraction->shouldReceive('scoreImportance')->andReturn(0.8);

        $pipeline = $this->createPipeline(extractionProvider: $mockExtraction);
        $result = $pipeline->processRepoAnalysisSession($this->session);

        $this->assertTrue($result->success);
        $this->assertGreaterThanOrEqual(2, $result->conversationLogsCreated);
    }

    /**
     * Test non-API mode only persists conversation logs.
     */
    public function test_non_api_mode_only_persists_logs(): void
    {
        config(['memory.api_enabled' => false]);

        RepoAnalysisTask::factory()->for($this->session, 'session')->create([
            'analyzer_name' => 'testing',
            'task_key' => 'test-coverage',
            'status' => 'completed',
            'metadata_json' => ['output' => 'Test coverage is 78%.'],
            'finished_at' => now(),
        ]);

        $pipeline = $this->createPipeline();
        $result = $pipeline->processRepoAnalysisSession($this->session);

        $this->assertTrue($result->success);
        $this->assertGreaterThanOrEqual(1, $result->conversationLogsCreated);
        $this->assertEquals(0, $result->embeddingsCreated);
        $this->assertEquals(0, $result->entitiesStored);
    }

    private function createPipeline(
        ?ExtractionProvider $extractionProvider = null,
        ?EmbeddingProvider $embeddingProvider = null,
        ?Neo4jGraphStore $graphStore = null,
    ): MemoryFormationPipeline {
        $workingMemoryBuffer = Mockery::mock(WorkingMemoryBuffer::class);
        $workingMemoryBuffer->shouldReceive('getRecent')->andReturn([]);

        return new MemoryFormationPipeline(
            $workingMemoryBuffer,
            $extractionProvider,
            $embeddingProvider,
            $graphStore,
            null,
            new EntitySanitizer,
        );
    }
}
