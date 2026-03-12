<?php

declare(strict_types=1);

namespace Tests\Feature\Memory;

use App\Models\InterrogationEvent;
use App\Models\InterrogationSession;
use App\Models\MemoryConversationLog;
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
 * Tests for InterrogationSession memory formation pipeline.
 *
 * Verifies:
 * - Builds entries from InterrogationEvent Q&A pairs
 * - Persists conversation logs with source_type identification
 * - Maps event types to correct roles (assistant/user/system)
 * - Handles empty sessions gracefully
 * - Runs extraction/embedding/graph when API enabled
 * - Extraction failure returns partial result
 * - Non-API mode only persists conversation logs
 */
class InterrogationMemoryFormationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private InterrogationSession $session;

    protected function setUp(): void
    {
        parent::setUp();

        config(['memory.enabled' => true]);
        config(['memory.api_enabled' => false]);

        $this->user = User::factory()->create();
        $this->session = InterrogationSession::factory()->for($this->user)->create([
            'status' => InterrogationSession::STATUS_COMPLETED,
            'feature_brief' => 'Build a user authentication system.',
        ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Test that Q&A events are converted to conversation log entries.
     */
    public function test_builds_entries_from_question_answer_events(): void
    {
        InterrogationEvent::factory()->for($this->session, 'session')->create([
            'event_type' => InterrogationEvent::TYPE_QUESTION,
            'sequence' => 1,
            'payload' => ['question_text' => 'What authentication method should we use?'],
        ]);

        InterrogationEvent::factory()->for($this->session, 'session')->create([
            'event_type' => InterrogationEvent::TYPE_ANSWER,
            'sequence' => 2,
            'payload' => ['answer_text' => 'We should use OAuth2 with JWT tokens.'],
        ]);

        $pipeline = $this->createPipeline();
        $result = $pipeline->processInterrogationSession($this->session);

        $this->assertTrue($result->success);
        $this->assertEquals(2, $result->conversationLogsCreated);

        $logs = MemoryConversationLog::where('source_type', MemoryConversationLog::SOURCE_INTERROGATION)
            ->where('source_id', (string) $this->session->id)
            ->orderBy('sequence')
            ->get();

        $this->assertCount(2, $logs);

        // Question should be mapped to assistant role
        $this->assertEquals('assistant', $logs[0]->role);
        $this->assertStringContainsString('What authentication method', $logs[0]->content);

        // Answer should be mapped to user role
        $this->assertEquals('user', $logs[1]->role);
        $this->assertStringContainsString('OAuth2 with JWT tokens', $logs[1]->content);
    }

    /**
     * Test that discovery events are mapped to system role.
     */
    public function test_discovery_events_mapped_to_system_role(): void
    {
        InterrogationEvent::factory()->for($this->session, 'session')->create([
            'event_type' => InterrogationEvent::TYPE_DISCOVERY_ACTIVITY,
            'sequence' => 1,
            'payload' => ['message' => 'Found Laravel 12 with Sanctum package installed.'],
        ]);

        $pipeline = $this->createPipeline();
        $result = $pipeline->processInterrogationSession($this->session);

        $this->assertTrue($result->success);

        $log = MemoryConversationLog::where('source_type', MemoryConversationLog::SOURCE_INTERROGATION)
            ->where('source_id', (string) $this->session->id)
            ->first();

        $this->assertNotNull($log);
        $this->assertEquals('system', $log->role);
        $this->assertStringContainsString('Laravel 12 with Sanctum', $log->content);
    }

    /**
     * Test that summary and plan events are mapped to assistant role.
     */
    public function test_summary_and_plan_events_mapped_to_assistant(): void
    {
        InterrogationEvent::factory()->for($this->session, 'session')->create([
            'event_type' => InterrogationEvent::TYPE_SUMMARY,
            'sequence' => 1,
            'payload' => ['content' => 'Summary of authentication requirements.'],
        ]);

        InterrogationEvent::factory()->for($this->session, 'session')->create([
            'event_type' => InterrogationEvent::TYPE_PLAN,
            'sequence' => 2,
            'payload' => ['content' => 'Implementation plan for OAuth2 flow.'],
        ]);

        $pipeline = $this->createPipeline();
        $result = $pipeline->processInterrogationSession($this->session);

        $this->assertTrue($result->success);
        $this->assertEquals(2, $result->conversationLogsCreated);

        $logs = MemoryConversationLog::where('source_type', MemoryConversationLog::SOURCE_INTERROGATION)
            ->where('source_id', (string) $this->session->id)
            ->orderBy('sequence')
            ->get();

        $this->assertEquals('assistant', $logs[0]->role);
        $this->assertEquals('assistant', $logs[1]->role);
    }

    /**
     * Test that empty sessions (no events) return success with zero logs.
     */
    public function test_empty_session_returns_success(): void
    {
        $pipeline = $this->createPipeline();
        $result = $pipeline->processInterrogationSession($this->session);

        $this->assertTrue($result->success);
        $this->assertEquals(0, $result->conversationLogsCreated);
    }

    /**
     * Test that events with empty payloads are skipped.
     */
    public function test_skips_events_with_empty_content(): void
    {
        InterrogationEvent::factory()->for($this->session, 'session')->create([
            'event_type' => InterrogationEvent::TYPE_QUESTION,
            'sequence' => 1,
            'payload' => ['question_text' => 'What should we build?'],
        ]);

        InterrogationEvent::factory()->for($this->session, 'session')->create([
            'event_type' => InterrogationEvent::TYPE_PHASE_TRANSITION,
            'sequence' => 2,
            'payload' => [], // Empty payload — should be skipped
        ]);

        $pipeline = $this->createPipeline();
        $result = $pipeline->processInterrogationSession($this->session);

        $this->assertTrue($result->success);
        $this->assertEquals(1, $result->conversationLogsCreated);
    }

    /**
     * Test that conversation logs use source_type and source_id (not run_id or runtime_session_id).
     */
    public function test_persists_with_source_type_columns(): void
    {
        InterrogationEvent::factory()->for($this->session, 'session')->create([
            'event_type' => InterrogationEvent::TYPE_QUESTION,
            'sequence' => 1,
            'payload' => ['question_text' => 'Test question.'],
        ]);

        $pipeline = $this->createPipeline();
        $pipeline->processInterrogationSession($this->session);

        $log = MemoryConversationLog::where('source_type', MemoryConversationLog::SOURCE_INTERROGATION)->first();

        $this->assertNotNull($log);
        $this->assertNull($log->run_id);
        $this->assertNull($log->job_id);
        $this->assertNull($log->runtime_session_id);
        $this->assertEquals(MemoryConversationLog::SOURCE_INTERROGATION, $log->source_type);
        $this->assertEquals((string) $this->session->id, $log->source_id);
        $this->assertEquals($this->user->id, $log->user_id);
    }

    /**
     * Test that extraction runs when API mode is enabled.
     */
    public function test_runs_extraction_when_api_enabled(): void
    {
        config(['memory.api_enabled' => true]);

        InterrogationEvent::factory()->for($this->session, 'session')->create([
            'event_type' => InterrogationEvent::TYPE_QUESTION,
            'sequence' => 1,
            'payload' => ['question_text' => 'What database should we use?'],
        ]);

        InterrogationEvent::factory()->for($this->session, 'session')->create([
            'event_type' => InterrogationEvent::TYPE_ANSWER,
            'sequence' => 2,
            'payload' => ['answer_text' => 'PostgreSQL for relational data.'],
        ]);

        $mockExtraction = Mockery::mock(ExtractionProvider::class);
        $mockExtraction->shouldReceive('getProviderName')->andReturn('test');
        $mockExtraction->shouldReceive('extractEntities')
            ->once()
            ->andReturn([
                ['type' => 'Dependency', 'name' => 'PostgreSQL', 'confidence' => 0.9],
            ]);
        $mockExtraction->shouldReceive('scoreImportance')->andReturn(0.7);

        $pipeline = $this->createPipeline(extractionProvider: $mockExtraction);
        $result = $pipeline->processInterrogationSession($this->session);

        $this->assertTrue($result->success);
        $this->assertEquals(2, $result->conversationLogsCreated);
    }

    /**
     * Test that extraction failure returns partial result with conversation logs.
     */
    public function test_extraction_failure_returns_partial_result(): void
    {
        config(['memory.api_enabled' => true]);

        InterrogationEvent::factory()->for($this->session, 'session')->create([
            'event_type' => InterrogationEvent::TYPE_QUESTION,
            'sequence' => 1,
            'payload' => ['question_text' => 'Test question for extraction failure.'],
        ]);

        $mockExtraction = Mockery::mock(ExtractionProvider::class);
        $mockExtraction->shouldReceive('getProviderName')->andReturn('test');
        $mockExtraction->shouldReceive('extractEntities')
            ->andThrow(new \RuntimeException('API quota exceeded'));

        $pipeline = $this->createPipeline(extractionProvider: $mockExtraction);
        $result = $pipeline->processInterrogationSession($this->session);

        $this->assertFalse($result->success);
        // Conversation logs should have been persisted before extraction failed
        $this->assertEquals(1, $result->conversationLogsCreated);
    }

    /**
     * Test that non-API mode only persists conversation logs.
     */
    public function test_non_api_mode_only_persists_logs(): void
    {
        config(['memory.api_enabled' => false]);

        InterrogationEvent::factory()->for($this->session, 'session')->create([
            'event_type' => InterrogationEvent::TYPE_QUESTION,
            'sequence' => 1,
            'payload' => ['question_text' => 'Design question.'],
        ]);

        InterrogationEvent::factory()->for($this->session, 'session')->create([
            'event_type' => InterrogationEvent::TYPE_ANSWER,
            'sequence' => 2,
            'payload' => ['answer_text' => 'Design answer.'],
        ]);

        $pipeline = $this->createPipeline();
        $result = $pipeline->processInterrogationSession($this->session);

        $this->assertTrue($result->success);
        $this->assertEquals(2, $result->conversationLogsCreated);
        $this->assertEquals(0, $result->embeddingsCreated);
        $this->assertEquals(0, $result->entitiesStored);
    }

    /**
     * Test multiple event types in a full session lifecycle.
     */
    public function test_full_session_lifecycle_entries(): void
    {
        // Discovery
        InterrogationEvent::factory()->for($this->session, 'session')->create([
            'event_type' => InterrogationEvent::TYPE_DISCOVERY_ACTIVITY,
            'sequence' => 1,
            'payload' => ['message' => 'Scanning project structure...'],
        ]);

        // Q&A round
        InterrogationEvent::factory()->for($this->session, 'session')->create([
            'event_type' => InterrogationEvent::TYPE_QUESTION,
            'sequence' => 2,
            'payload' => ['question_text' => 'What is the target platform?'],
        ]);
        InterrogationEvent::factory()->for($this->session, 'session')->create([
            'event_type' => InterrogationEvent::TYPE_ANSWER,
            'sequence' => 3,
            'payload' => ['answer_text' => 'Web application with mobile support.'],
        ]);

        // Summary
        InterrogationEvent::factory()->for($this->session, 'session')->create([
            'event_type' => InterrogationEvent::TYPE_SUMMARY,
            'sequence' => 4,
            'payload' => ['content' => 'Requirements summary for web+mobile app.'],
        ]);

        $pipeline = $this->createPipeline();
        $result = $pipeline->processInterrogationSession($this->session);

        $this->assertTrue($result->success);
        $this->assertEquals(4, $result->conversationLogsCreated);

        $logs = MemoryConversationLog::where('source_type', MemoryConversationLog::SOURCE_INTERROGATION)
            ->where('source_id', (string) $this->session->id)
            ->orderBy('sequence')
            ->get();

        $this->assertEquals('system', $logs[0]->role);     // discovery
        $this->assertEquals('assistant', $logs[1]->role);   // question
        $this->assertEquals('user', $logs[2]->role);        // answer
        $this->assertEquals('assistant', $logs[3]->role);   // summary
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
