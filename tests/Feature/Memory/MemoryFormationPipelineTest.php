<?php

declare(strict_types=1);

namespace Tests\Feature\Memory;

use App\Models\AgentJob;
use App\Models\AgentJobRun;
use App\Models\MemoryConversationLog;
use App\Models\MemoryEmbedding;
use App\Models\MemoryFormationFailure;
use App\Models\Runtime\RuntimeSession;
use App\Models\Runtime\RuntimeTurn;
use App\Models\User;
use App\Support\Memory\Contracts\EmbeddingProvider;
use App\Support\Memory\Contracts\ExtractionProvider;
use App\Support\Memory\MemoryFormationPipeline;
use App\Support\Memory\Neo4jGraphStore;
use App\Support\Memory\WorkingMemoryBuffer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Redis;
use Mockery;
use Tests\TestCase;

/**
 * Tests for MemoryFormationPipeline orchestration.
 *
 * Verifies:
 * - Persists conversation logs from Working Memory
 * - Extracts entities using ExtractionProvider (mock)
 * - Generates embeddings using EmbeddingProvider (mock)
 * - Deduplicates by content_hash
 * - Stores graph entities in Neo4j (mock)
 * - Records failure on partial completion
 */
class MemoryFormationPipelineTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private AgentJob $job;

    private AgentJobRun $run;

    protected function setUp(): void
    {
        parent::setUp();

        config(['memory.enabled' => true]);
        config(['memory.api_enabled' => true]);

        $this->user = User::factory()->create();
        $this->job = AgentJob::factory()->for($this->user)->create();
        $this->run = AgentJobRun::factory()->for($this->job, 'job')->create([
            'user_id' => $this->user->id,
            'status' => AgentJobRun::STATUS_SUCCEEDED,
        ]);

        $this->clearRedisKeys();
    }

    protected function tearDown(): void
    {
        $this->clearRedisKeys();
        Mockery::close();
        parent::tearDown();
    }

    private function clearRedisKeys(): void
    {
        try {
            $connection = Redis::connection('memory');
            $prefix = config('memory.working_memory.key_prefix', 'memory:run:');
            $key = $prefix.$this->run->id.':working';
            $connection->del($key);
        } catch (\Throwable) {
            // Ignore Redis errors in cleanup
        }
    }

    /**
     * Test that pipeline persists conversation logs from Working Memory.
     */
    public function test_persists_conversation_logs_from_working_memory(): void
    {
        // Seed working memory with entries
        $buffer = app(WorkingMemoryBuffer::class);
        $buffer->append($this->run->id, 'user', 'Hello assistant');
        $buffer->append($this->run->id, 'assistant', 'Hello! How can I help?');
        $buffer->append($this->run->id, 'user', 'Explain PHP closures');

        // Mock providers
        $extractionProvider = Mockery::mock(ExtractionProvider::class);
        $extractionProvider->shouldReceive('extractEntities')->andReturn([]);
        $extractionProvider->shouldReceive('scoreImportance')->andReturn(0.5);
        $extractionProvider->shouldReceive('getProviderName')->andReturn('mock');

        $embeddingProvider = Mockery::mock(EmbeddingProvider::class);
        $embeddingProvider->shouldReceive('embed')->andReturn(array_fill(0, 1536, 0.1));
        $embeddingProvider->shouldReceive('getProviderName')->andReturn('mock');

        $graphStore = Mockery::mock(Neo4jGraphStore::class);
        $graphStore->shouldReceive('storeEntities')->andReturn();
        $graphStore->shouldReceive('storeRelationships')->andReturn();
        $graphStore->shouldReceive('healthCheck')->andReturn(true);

        $pipeline = new MemoryFormationPipeline(
            $buffer,
            $extractionProvider,
            $embeddingProvider,
            $graphStore
        );

        $result = $pipeline->process($this->run);

        $this->assertTrue($result->success);

        // Verify conversation logs were persisted
        $logs = MemoryConversationLog::forRun($this->run->id)->ordered()->get();
        $this->assertCount(3, $logs);
        $this->assertEquals('user', $logs[0]->role);
        $this->assertEquals('Hello assistant', $logs[0]->content);
        $this->assertEquals('assistant', $logs[1]->role);
        $this->assertEquals('Hello! How can I help?', $logs[1]->content);
    }

    /**
     * Test that pipeline extracts entities using ExtractionProvider.
     */
    public function test_extracts_entities_using_extraction_provider(): void
    {
        $buffer = app(WorkingMemoryBuffer::class);
        $buffer->append($this->run->id, 'user', 'John from Acme Corp called about the API');

        $extractionProvider = Mockery::mock(ExtractionProvider::class);
        $extractionProvider->shouldReceive('extractEntities')
            ->once()
            ->with(Mockery::on(fn ($text) => str_contains($text, 'John from Acme Corp')))
            ->andReturn([
                ['type' => 'Person', 'name' => 'John', 'confidence' => 0.95],
                ['type' => 'Organization', 'name' => 'Acme Corp', 'confidence' => 0.9],
                ['type' => 'Concept', 'name' => 'API', 'confidence' => 0.85],
            ]);
        $extractionProvider->shouldReceive('scoreImportance')->andReturn(0.7);
        $extractionProvider->shouldReceive('getProviderName')->andReturn('mock');

        $embeddingProvider = Mockery::mock(EmbeddingProvider::class);
        $embeddingProvider->shouldReceive('embed')->andReturn(array_fill(0, 1536, 0.1));
        $embeddingProvider->shouldReceive('getProviderName')->andReturn('mock');

        $graphStore = Mockery::mock(Neo4jGraphStore::class);
        $graphStore->shouldReceive('storeEntities')
            ->once()
            ->with($this->user->id, Mockery::on(function ($entities) {
                return count($entities) === 3
                    && $entities[0]['type'] === 'Person'
                    && $entities[0]['name'] === 'John';
            }));
        $graphStore->shouldReceive('storeRelationships')->andReturn();
        $graphStore->shouldReceive('healthCheck')->andReturn(true);

        $pipeline = new MemoryFormationPipeline(
            $buffer,
            $extractionProvider,
            $embeddingProvider,
            $graphStore
        );

        $result = $pipeline->process($this->run);

        $this->assertTrue($result->success);
    }

    /**
     * Test that pipeline generates embeddings using EmbeddingProvider.
     */
    public function test_generates_embeddings_using_embedding_provider(): void
    {
        $buffer = app(WorkingMemoryBuffer::class);
        $buffer->append($this->run->id, 'assistant', 'PHP closures capture variables from scope');

        $extractionProvider = Mockery::mock(ExtractionProvider::class);
        $extractionProvider->shouldReceive('extractEntities')->andReturn([
            ['type' => 'Concept', 'name' => 'PHP closures', 'confidence' => 0.9],
        ]);
        $extractionProvider->shouldReceive('scoreImportance')->andReturn(0.8);
        $extractionProvider->shouldReceive('getProviderName')->andReturn('mock');

        $embeddingVector = array_fill(0, 1536, 0.1);
        $embeddingProvider = Mockery::mock(EmbeddingProvider::class);
        $embeddingProvider->shouldReceive('embed')
            ->once()
            ->with(Mockery::on(fn ($text) => str_contains($text, 'PHP closures')))
            ->andReturn($embeddingVector);
        $embeddingProvider->shouldReceive('getProviderName')->andReturn('mock');

        $graphStore = Mockery::mock(Neo4jGraphStore::class);
        $graphStore->shouldReceive('storeEntities')->andReturn();
        $graphStore->shouldReceive('storeRelationships')->andReturn();
        $graphStore->shouldReceive('healthCheck')->andReturn(true);

        $pipeline = new MemoryFormationPipeline(
            $buffer,
            $extractionProvider,
            $embeddingProvider,
            $graphStore
        );

        $result = $pipeline->process($this->run);

        $this->assertTrue($result->success);

        // Verify embedding was stored
        $embedding = MemoryEmbedding::forUser($this->user->id)->first();
        $this->assertNotNull($embedding);
        $this->assertStringContainsString('PHP closures', $embedding->content);
        $this->assertEquals(0.8, $embedding->importance_score);
    }

    /**
     * Test that pipeline deduplicates by content_hash.
     */
    public function test_deduplicates_by_content_hash(): void
    {
        $buffer = app(WorkingMemoryBuffer::class);
        $rawContent = 'This is duplicate content';
        $buffer->append($this->run->id, 'assistant', $rawContent);

        // The pipeline combines content with role prefix: [role]: content
        $combinedContent = "[assistant]: {$rawContent}";

        // Pre-create an existing embedding with the same combined content format
        $existingHash = MemoryEmbedding::generateContentHash($combinedContent);
        MemoryEmbedding::create([
            'user_id' => $this->user->id,
            'source_type' => MemoryEmbedding::SOURCE_CONVERSATION,
            'source_id' => 'existing',
            'content' => $combinedContent,
            'content_hash' => $existingHash,
            'importance_score' => 0.5,
            'classification' => 'internal',
            'created_at' => now(),
        ]);

        $extractionProvider = Mockery::mock(ExtractionProvider::class);
        $extractionProvider->shouldReceive('extractEntities')->andReturn([]);
        $extractionProvider->shouldReceive('scoreImportance')->andReturn(0.5);
        $extractionProvider->shouldReceive('getProviderName')->andReturn('mock');

        // Embedding should not be called since content already exists
        $embeddingProvider = Mockery::mock(EmbeddingProvider::class);
        $embeddingProvider->shouldReceive('embed')->never();
        $embeddingProvider->shouldReceive('getProviderName')->andReturn('mock');

        $graphStore = Mockery::mock(Neo4jGraphStore::class);
        $graphStore->shouldReceive('storeEntities')->andReturn();
        $graphStore->shouldReceive('storeRelationships')->andReturn();
        $graphStore->shouldReceive('healthCheck')->andReturn(true);

        $pipeline = new MemoryFormationPipeline(
            $buffer,
            $extractionProvider,
            $embeddingProvider,
            $graphStore
        );

        $result = $pipeline->process($this->run);

        $this->assertTrue($result->success);

        // Should still be only 1 embedding (the original)
        $this->assertEquals(1, MemoryEmbedding::forUser($this->user->id)->count());
    }

    /**
     * Test that pipeline stores entities in Neo4j graph.
     */
    public function test_stores_entities_in_neo4j_graph(): void
    {
        $buffer = app(WorkingMemoryBuffer::class);
        $buffer->append($this->run->id, 'user', 'Add error handling to UserController');

        $entities = [
            ['type' => 'Concept', 'name' => 'error handling', 'confidence' => 0.9],
            ['type' => 'Class', 'name' => 'UserController', 'confidence' => 0.95],
        ];

        $extractionProvider = Mockery::mock(ExtractionProvider::class);
        $extractionProvider->shouldReceive('extractEntities')->andReturn($entities);
        $extractionProvider->shouldReceive('scoreImportance')->andReturn(0.7);
        $extractionProvider->shouldReceive('getProviderName')->andReturn('mock');

        $embeddingProvider = Mockery::mock(EmbeddingProvider::class);
        $embeddingProvider->shouldReceive('embed')->andReturn(array_fill(0, 1536, 0.1));
        $embeddingProvider->shouldReceive('getProviderName')->andReturn('mock');

        $graphStore = Mockery::mock(Neo4jGraphStore::class);
        $graphStore->shouldReceive('healthCheck')->once()->andReturn(true);
        $graphStore->shouldReceive('storeEntities')
            ->once()
            ->with($this->user->id, Mockery::on(function ($stored) {
                return count($stored) === 2
                    && $stored[0]['type'] === 'Concept'
                    && $stored[1]['type'] === 'Class';
            }));
        $graphStore->shouldReceive('storeRelationships')
            ->once()
            ->with($this->user->id, Mockery::type('array'));

        $pipeline = new MemoryFormationPipeline(
            $buffer,
            $extractionProvider,
            $embeddingProvider,
            $graphStore
        );

        $result = $pipeline->process($this->run);

        $this->assertTrue($result->success);
    }

    /**
     * Test that pipeline records failure on extraction error.
     */
    public function test_records_failure_on_extraction_error(): void
    {
        $buffer = app(WorkingMemoryBuffer::class);
        $buffer->append($this->run->id, 'user', 'Test content');

        $extractionProvider = Mockery::mock(ExtractionProvider::class);
        $extractionProvider->shouldReceive('extractEntities')
            ->andThrow(new \RuntimeException('Provider API error'));
        $extractionProvider->shouldReceive('getProviderName')->andReturn('mock');

        $embeddingProvider = Mockery::mock(EmbeddingProvider::class);
        $embeddingProvider->shouldReceive('embed')->never();
        $embeddingProvider->shouldReceive('getProviderName')->andReturn('mock');

        $graphStore = Mockery::mock(Neo4jGraphStore::class);
        $graphStore->shouldReceive('healthCheck')->andReturn(true);
        $graphStore->shouldReceive('storeEntities')->never();
        $graphStore->shouldReceive('storeRelationships')->never();

        $pipeline = new MemoryFormationPipeline(
            $buffer,
            $extractionProvider,
            $embeddingProvider,
            $graphStore
        );

        $result = $pipeline->process($this->run);

        $this->assertFalse($result->success);
        $this->assertEquals(MemoryFormationFailure::TYPE_EXTRACTION, $result->failureType);
        $this->assertStringContainsString('Provider API error', $result->errorMessage);

        // But conversation logs should still be persisted (partial success)
        $logs = MemoryConversationLog::forRun($this->run->id)->get();
        $this->assertCount(1, $logs);
    }

    /**
     * Test that pipeline degrades gracefully on embedding error.
     *
     * Embedding failure is no longer fatal — the pipeline continues
     * to graph storage and returns success with 0 embeddings.
     */
    public function test_degrades_gracefully_on_embedding_error(): void
    {
        $buffer = app(WorkingMemoryBuffer::class);
        $buffer->append($this->run->id, 'user', 'Test content');

        $extractionProvider = Mockery::mock(ExtractionProvider::class);
        $extractionProvider->shouldReceive('extractEntities')->andReturn([]);
        $extractionProvider->shouldReceive('scoreImportance')->andReturn(0.5);
        $extractionProvider->shouldReceive('getProviderName')->andReturn('mock');

        $embeddingProvider = Mockery::mock(EmbeddingProvider::class);
        $embeddingProvider->shouldReceive('embed')
            ->andThrow(new \RuntimeException('Embedding API error'));
        $embeddingProvider->shouldReceive('getProviderName')->andReturn('mock');

        $graphStore = Mockery::mock(Neo4jGraphStore::class);
        $graphStore->shouldReceive('healthCheck')->andReturn(true);
        $graphStore->shouldReceive('storeEntities')->andReturn();
        $graphStore->shouldReceive('storeRelationships')->andReturn();

        $pipeline = new MemoryFormationPipeline(
            $buffer,
            $extractionProvider,
            $embeddingProvider,
            $graphStore
        );

        $result = $pipeline->process($this->run);

        $this->assertTrue($result->success);
        $this->assertEquals(0, $result->embeddingsCreated);

        // Conversation logs should still be persisted
        $logs = MemoryConversationLog::forRun($this->run->id)->get();
        $this->assertCount(1, $logs);
    }

    /**
     * Test that pipeline records failure on Neo4j graph error.
     */
    public function test_records_failure_on_graph_error(): void
    {
        $buffer = app(WorkingMemoryBuffer::class);
        $buffer->append($this->run->id, 'user', 'Test content');

        $extractionProvider = Mockery::mock(ExtractionProvider::class);
        $extractionProvider->shouldReceive('extractEntities')->andReturn([
            ['type' => 'Concept', 'name' => 'test', 'confidence' => 0.9],
        ]);
        $extractionProvider->shouldReceive('scoreImportance')->andReturn(0.5);
        $extractionProvider->shouldReceive('getProviderName')->andReturn('mock');

        $embeddingProvider = Mockery::mock(EmbeddingProvider::class);
        $embeddingProvider->shouldReceive('embed')->andReturn(array_fill(0, 1536, 0.1));
        $embeddingProvider->shouldReceive('getProviderName')->andReturn('mock');

        $graphStore = Mockery::mock(Neo4jGraphStore::class);
        $graphStore->shouldReceive('healthCheck')->andReturn(true);
        $graphStore->shouldReceive('storeEntities')
            ->andThrow(new \RuntimeException('Neo4j connection error'));
        $graphStore->shouldReceive('storeRelationships')->never();

        $pipeline = new MemoryFormationPipeline(
            $buffer,
            $extractionProvider,
            $embeddingProvider,
            $graphStore
        );

        $result = $pipeline->process($this->run);

        $this->assertFalse($result->success);
        $this->assertEquals(MemoryFormationFailure::TYPE_GRAPH, $result->failureType);

        // Conversation logs and embeddings should still be persisted
        $logs = MemoryConversationLog::forRun($this->run->id)->get();
        $this->assertCount(1, $logs);

        $embeddings = MemoryEmbedding::forUser($this->user->id)->get();
        $this->assertCount(1, $embeddings);
    }

    /**
     * Test that pipeline handles empty working memory gracefully.
     */
    public function test_handles_empty_working_memory(): void
    {
        // Don't add anything to working memory

        $extractionProvider = Mockery::mock(ExtractionProvider::class);
        $extractionProvider->shouldReceive('extractEntities')->never();
        $extractionProvider->shouldReceive('getProviderName')->andReturn('mock');

        $embeddingProvider = Mockery::mock(EmbeddingProvider::class);
        $embeddingProvider->shouldReceive('embed')->never();
        $embeddingProvider->shouldReceive('getProviderName')->andReturn('mock');

        $graphStore = Mockery::mock(Neo4jGraphStore::class);
        $graphStore->shouldReceive('storeEntities')->never();
        $graphStore->shouldReceive('storeRelationships')->never();
        $graphStore->shouldReceive('healthCheck')->andReturn(true);

        $pipeline = new MemoryFormationPipeline(
            app(WorkingMemoryBuffer::class),
            $extractionProvider,
            $embeddingProvider,
            $graphStore
        );

        $result = $pipeline->process($this->run);

        // Should succeed but with no data to process
        $this->assertTrue($result->success);
        $this->assertEquals(0, $result->conversationLogsCreated);
        $this->assertEquals(0, $result->embeddingsCreated);
        $this->assertEquals(0, $result->entitiesStored);
    }

    /**
     * Test that pipeline skips API calls when api_enabled is false.
     */
    public function test_skips_api_calls_when_api_disabled(): void
    {
        config(['memory.api_enabled' => false]);

        $buffer = app(WorkingMemoryBuffer::class);
        $buffer->append($this->run->id, 'user', 'Test content');

        $extractionProvider = Mockery::mock(ExtractionProvider::class);
        $extractionProvider->shouldReceive('extractEntities')->never();
        $extractionProvider->shouldReceive('getProviderName')->andReturn('mock');

        $embeddingProvider = Mockery::mock(EmbeddingProvider::class);
        $embeddingProvider->shouldReceive('embed')->never();
        $embeddingProvider->shouldReceive('getProviderName')->andReturn('mock');

        $graphStore = Mockery::mock(Neo4jGraphStore::class);
        $graphStore->shouldReceive('storeEntities')->never();
        $graphStore->shouldReceive('storeRelationships')->never();
        $graphStore->shouldReceive('healthCheck')->andReturn(true);

        $pipeline = new MemoryFormationPipeline(
            $buffer,
            $extractionProvider,
            $embeddingProvider,
            $graphStore
        );

        $result = $pipeline->process($this->run);

        // Should succeed - conversation logs still persisted
        $this->assertTrue($result->success);

        // Conversation logs should still be persisted (No-API mode)
        $logs = MemoryConversationLog::forRun($this->run->id)->get();
        $this->assertCount(1, $logs);
    }

    /**
     * Test that pipeline sets correct sequence numbers on conversation logs.
     */
    public function test_sets_correct_sequence_numbers(): void
    {
        $buffer = app(WorkingMemoryBuffer::class);
        $buffer->append($this->run->id, 'user', 'First message');
        usleep(1000); // Ensure different timestamps
        $buffer->append($this->run->id, 'assistant', 'Second message');
        usleep(1000);
        $buffer->append($this->run->id, 'user', 'Third message');

        $extractionProvider = Mockery::mock(ExtractionProvider::class);
        $extractionProvider->shouldReceive('extractEntities')->andReturn([]);
        $extractionProvider->shouldReceive('scoreImportance')->andReturn(0.5);
        $extractionProvider->shouldReceive('getProviderName')->andReturn('mock');

        $embeddingProvider = Mockery::mock(EmbeddingProvider::class);
        $embeddingProvider->shouldReceive('embed')->andReturn(array_fill(0, 1536, 0.1));
        $embeddingProvider->shouldReceive('getProviderName')->andReturn('mock');

        $graphStore = Mockery::mock(Neo4jGraphStore::class);
        $graphStore->shouldReceive('storeEntities')->andReturn();
        $graphStore->shouldReceive('storeRelationships')->andReturn();
        $graphStore->shouldReceive('healthCheck')->andReturn(true);

        $pipeline = new MemoryFormationPipeline(
            $buffer,
            $extractionProvider,
            $embeddingProvider,
            $graphStore
        );

        $result = $pipeline->process($this->run);

        $this->assertTrue($result->success);

        $logs = MemoryConversationLog::forRun($this->run->id)->ordered()->get();
        $this->assertEquals(1, $logs[0]->sequence);
        $this->assertEquals(2, $logs[1]->sequence);
        $this->assertEquals(3, $logs[2]->sequence);
    }

    /**
     * Test that pipeline associates logs with correct job and run.
     */
    public function test_associates_logs_with_correct_job_and_run(): void
    {
        $buffer = app(WorkingMemoryBuffer::class);
        $buffer->append($this->run->id, 'user', 'Test');

        $extractionProvider = Mockery::mock(ExtractionProvider::class);
        $extractionProvider->shouldReceive('extractEntities')->andReturn([]);
        $extractionProvider->shouldReceive('scoreImportance')->andReturn(0.5);
        $extractionProvider->shouldReceive('getProviderName')->andReturn('mock');

        $embeddingProvider = Mockery::mock(EmbeddingProvider::class);
        $embeddingProvider->shouldReceive('embed')->andReturn(array_fill(0, 1536, 0.1));
        $embeddingProvider->shouldReceive('getProviderName')->andReturn('mock');

        $graphStore = Mockery::mock(Neo4jGraphStore::class);
        $graphStore->shouldReceive('storeEntities')->andReturn();
        $graphStore->shouldReceive('storeRelationships')->andReturn();
        $graphStore->shouldReceive('healthCheck')->andReturn(true);

        $pipeline = new MemoryFormationPipeline(
            $buffer,
            $extractionProvider,
            $embeddingProvider,
            $graphStore
        );

        $pipeline->process($this->run);

        $log = MemoryConversationLog::forRun($this->run->id)->first();
        $this->assertEquals($this->run->id, $log->run_id);
        $this->assertEquals($this->job->id, $log->job_id);
        $this->assertEquals($this->user->id, $log->user_id);
    }

    /**
     * Test that pipeline skips graph storage when Neo4j is unhealthy.
     */
    public function test_skips_graph_when_neo4j_unhealthy(): void
    {
        $buffer = app(WorkingMemoryBuffer::class);
        $buffer->append($this->run->id, 'user', 'Test content');

        $extractionProvider = Mockery::mock(ExtractionProvider::class);
        $extractionProvider->shouldReceive('extractEntities')->andReturn([
            ['type' => 'Concept', 'name' => 'test', 'confidence' => 0.9],
        ]);
        $extractionProvider->shouldReceive('scoreImportance')->andReturn(0.5);
        $extractionProvider->shouldReceive('getProviderName')->andReturn('mock');

        $embeddingProvider = Mockery::mock(EmbeddingProvider::class);
        $embeddingProvider->shouldReceive('embed')->andReturn(array_fill(0, 1536, 0.1));
        $embeddingProvider->shouldReceive('getProviderName')->andReturn('mock');

        $graphStore = Mockery::mock(Neo4jGraphStore::class);
        $graphStore->shouldReceive('healthCheck')->once()->andReturn(false);
        $graphStore->shouldReceive('storeEntities')->never();
        $graphStore->shouldReceive('storeRelationships')->never();

        $pipeline = new MemoryFormationPipeline(
            $buffer,
            $extractionProvider,
            $embeddingProvider,
            $graphStore
        );

        $result = $pipeline->process($this->run);

        // Should still succeed - graceful degradation
        $this->assertTrue($result->success);
        $this->assertTrue($result->graphSkipped);
    }

    /**
     * Test that processRuntimeSession persists runtime session turns to memory_conversation_logs.
     */
    public function test_process_runtime_session_persists_conversation_logs(): void
    {
        config(['memory.api_enabled' => false]);

        $session = RuntimeSession::factory()->create([
            'user_id' => $this->user->id,
            'started_at' => now(),
        ]);
        RuntimeTurn::factory()->create([
            'runtime_session_id' => $session->id,
            'sequence' => 1,
            'summary' => 'I can help with that.',
        ]);
        RuntimeTurn::factory()->create([
            'runtime_session_id' => $session->id,
            'sequence' => 2,
            'summary' => 'Here is the result.',
        ]);

        $pipeline = new MemoryFormationPipeline(app(WorkingMemoryBuffer::class), null, null, null);

        $result = $pipeline->processRuntimeSession($session->fresh());

        $this->assertTrue($result->success);
        $this->assertGreaterThanOrEqual(2, $result->conversationLogsCreated);

        $logs = MemoryConversationLog::forRuntimeSession($session->id)->ordered()->get();
        $this->assertGreaterThanOrEqual(2, $logs->count());
        $this->assertEquals($session->id, $logs->first()->runtime_session_id);
        $this->assertNull($logs->first()->run_id);
        $this->assertNull($logs->first()->job_id);
    }
}
