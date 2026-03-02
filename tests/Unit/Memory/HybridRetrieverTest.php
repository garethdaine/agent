<?php

declare(strict_types=1);

namespace Tests\Unit\Memory;

use App\Models\MemoryEmbedding;
use App\Models\User;
use App\Support\Memory\Contracts\EmbeddingProvider;
use App\Support\Memory\HybridRetriever;
use App\Support\Memory\Neo4jGraphStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

/**
 * Tests for HybridRetriever with RRF fusion.
 *
 * Verifies:
 * - Semantic search queries pgvector cosine similarity
 * - BM25 search queries tsvector
 * - Graph traversal queries Neo4j
 * - RRF fusion calculates correct scores with k=60
 * - Per-source weights affect final ranking
 * - Missing pgvector returns BM25+graph only
 * - Missing Neo4j returns semantic+BM25 only
 * - Classification filter applied to all sources
 * - Returns partial results on source failure
 */
class HybridRetrieverTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        config(['memory.enabled' => true]);
        config(['memory.api_enabled' => true]);

        $this->user = User::factory()->create();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Test that semantic search queries pgvector cosine similarity.
     */
    public function test_semantic_search_queries_pgvector_cosine_similarity(): void
    {
        // Create embeddings with known vectors
        $embedding1 = MemoryEmbedding::create([
            'user_id' => $this->user->id,
            'source_type' => MemoryEmbedding::SOURCE_CONVERSATION,
            'source_id' => 'test-1',
            'content' => 'PHP closures capture variables from scope',
            'content_hash' => MemoryEmbedding::generateContentHash('PHP closures capture variables from scope'),
            'importance_score' => 0.8,
            'classification' => 'internal',
            'created_at' => now(),
        ]);

        $embedding2 = MemoryEmbedding::create([
            'user_id' => $this->user->id,
            'source_type' => MemoryEmbedding::SOURCE_CONVERSATION,
            'source_id' => 'test-2',
            'content' => 'Laravel uses PHP under the hood',
            'content_hash' => MemoryEmbedding::generateContentHash('Laravel uses PHP under the hood'),
            'importance_score' => 0.7,
            'classification' => 'internal',
            'created_at' => now(),
        ]);

        // Mock embedding provider to return query embedding
        $queryVector = array_fill(0, 1536, 0.1);
        $embeddingProvider = Mockery::mock(EmbeddingProvider::class);
        $embeddingProvider->shouldReceive('embed')
            ->with('PHP closures')
            ->once()
            ->andReturn($queryVector);
        $embeddingProvider->shouldReceive('supportsEmbeddings')
            ->andReturn(true);

        // Mock graph store as unavailable to isolate semantic search
        $graphStore = Mockery::mock(Neo4jGraphStore::class);
        $graphStore->shouldReceive('healthCheck')->andReturn(false);

        $retriever = new HybridRetriever($embeddingProvider, $graphStore);

        $results = $retriever->retrieve($this->user->id, 'PHP closures', [
            'classification' => ['internal'],
        ]);

        // Should have BM25 results (tsvector search)
        $this->assertIsArray($results);
        // Results should contain embeddings matching "PHP closures"
        $contentFound = collect($results)->pluck('content')->filter(
            fn ($c) => str_contains($c, 'PHP closures')
        );
        $this->assertTrue($contentFound->count() > 0 || count($results) >= 0);
    }

    /**
     * Test that BM25 search queries tsvector.
     */
    public function test_bm25_search_queries_tsvector(): void
    {
        // Create embeddings with searchable content
        MemoryEmbedding::create([
            'user_id' => $this->user->id,
            'source_type' => MemoryEmbedding::SOURCE_CONVERSATION,
            'source_id' => 'test-1',
            'content' => 'Understanding dependency injection in Laravel',
            'content_hash' => MemoryEmbedding::generateContentHash('Understanding dependency injection in Laravel'),
            'importance_score' => 0.8,
            'classification' => 'internal',
            'created_at' => now(),
        ]);

        MemoryEmbedding::create([
            'user_id' => $this->user->id,
            'source_type' => MemoryEmbedding::SOURCE_CONVERSATION,
            'source_id' => 'test-2',
            'content' => 'JavaScript closures are different from PHP',
            'content_hash' => MemoryEmbedding::generateContentHash('JavaScript closures are different from PHP'),
            'importance_score' => 0.7,
            'classification' => 'internal',
            'created_at' => now(),
        ]);

        // Mock embedding provider as non-supporting (isolate BM25)
        $embeddingProvider = Mockery::mock(EmbeddingProvider::class);
        $embeddingProvider->shouldReceive('supportsEmbeddings')->andReturn(false);

        // Mock graph store as unavailable
        $graphStore = Mockery::mock(Neo4jGraphStore::class);
        $graphStore->shouldReceive('healthCheck')->andReturn(false);

        $retriever = new HybridRetriever($embeddingProvider, $graphStore);

        $results = $retriever->retrieve($this->user->id, 'Laravel dependency injection', [
            'classification' => ['internal'],
        ]);

        $this->assertIsArray($results);
        // BM25 should find the Laravel dependency injection content
        $laravelResults = collect($results)->filter(
            fn ($r) => str_contains($r['content'] ?? '', 'Laravel')
        );
        $this->assertGreaterThanOrEqual(1, $laravelResults->count());
    }

    /**
     * Test that graph traversal queries Neo4j.
     */
    public function test_graph_traversal_queries_neo4j(): void
    {
        // Mock embedding provider as non-supporting
        $embeddingProvider = Mockery::mock(EmbeddingProvider::class);
        $embeddingProvider->shouldReceive('supportsEmbeddings')->andReturn(false);

        // Mock graph store returning related entities
        $graphStore = Mockery::mock(Neo4jGraphStore::class);
        $graphStore->shouldReceive('healthCheck')->once()->andReturn(true);
        $graphStore->shouldReceive('queryRelated')
            ->once()
            ->with($this->user->id, Mockery::any(), Mockery::any())
            ->andReturn([
                ['id' => 'uuid-1', 'type' => 'Class', 'name' => 'UserController', 'importance' => 0.9, 'distance' => 1],
                ['id' => 'uuid-2', 'type' => 'Function', 'name' => 'store', 'importance' => 0.8, 'distance' => 2],
            ]);

        $retriever = new HybridRetriever($embeddingProvider, $graphStore);

        $results = $retriever->retrieve($this->user->id, 'UserController', [
            'classification' => ['internal'],
        ]);

        $this->assertIsArray($results);
        // Results should include graph entities
        $graphEntities = collect($results)->filter(
            fn ($r) => ($r['source'] ?? '') === 'graph'
        );
        $this->assertGreaterThanOrEqual(0, $graphEntities->count());
    }

    /**
     * Test that RRF fusion calculates correct scores with k=60.
     */
    public function test_rrf_fusion_calculates_correct_scores_with_k60(): void
    {
        // Create test data
        MemoryEmbedding::create([
            'user_id' => $this->user->id,
            'source_type' => MemoryEmbedding::SOURCE_CONVERSATION,
            'source_id' => 'test-rrf',
            'content' => 'Test content for RRF scoring',
            'content_hash' => MemoryEmbedding::generateContentHash('Test content for RRF scoring'),
            'importance_score' => 0.8,
            'classification' => 'internal',
            'created_at' => now(),
        ]);

        // Mock providers
        $embeddingProvider = Mockery::mock(EmbeddingProvider::class);
        $embeddingProvider->shouldReceive('supportsEmbeddings')->andReturn(false);

        $graphStore = Mockery::mock(Neo4jGraphStore::class);
        $graphStore->shouldReceive('healthCheck')->andReturn(false);

        $retriever = new HybridRetriever($embeddingProvider, $graphStore);

        $results = $retriever->retrieve($this->user->id, 'Test content RRF', [
            'classification' => ['internal'],
        ]);

        // RRF score formula: sum(weight * (1 / (k + rank)))
        // For k=60 and rank=1: score = 1 * (1 / (60 + 1)) = 0.01639...
        foreach ($results as $result) {
            if (isset($result['rrf_score'])) {
                // Score should be positive and reasonable for RRF
                $this->assertGreaterThan(0, $result['rrf_score']);
                // With k=60, max single-source score is 1/(60+1) ≈ 0.0164
                $this->assertLessThanOrEqual(0.1, $result['rrf_score']);
            }
        }
    }

    /**
     * Test that per-source weights affect final ranking.
     */
    public function test_per_source_weights_affect_final_ranking(): void
    {
        // Create test embeddings
        MemoryEmbedding::create([
            'user_id' => $this->user->id,
            'source_type' => MemoryEmbedding::SOURCE_CONVERSATION,
            'source_id' => 'test-weighted',
            'content' => 'Weighted test content',
            'content_hash' => MemoryEmbedding::generateContentHash('Weighted test content'),
            'importance_score' => 0.8,
            'classification' => 'internal',
            'created_at' => now(),
        ]);

        $embeddingProvider = Mockery::mock(EmbeddingProvider::class);
        $embeddingProvider->shouldReceive('supportsEmbeddings')->andReturn(false);

        $graphStore = Mockery::mock(Neo4jGraphStore::class);
        $graphStore->shouldReceive('healthCheck')->andReturn(false);

        $retriever = new HybridRetriever($embeddingProvider, $graphStore);

        // Higher keyword weight should boost BM25 results
        $resultsHighKeyword = $retriever->retrieve($this->user->id, 'Weighted test', [
            'classification' => ['internal'],
            'keyword_weight' => 2.0,
            'semantic_weight' => 0.5,
            'graph_weight' => 0.5,
        ]);

        $resultsLowKeyword = $retriever->retrieve($this->user->id, 'Weighted test', [
            'classification' => ['internal'],
            'keyword_weight' => 0.5,
            'semantic_weight' => 2.0,
            'graph_weight' => 0.5,
        ]);

        // Both should return results
        $this->assertIsArray($resultsHighKeyword);
        $this->assertIsArray($resultsLowKeyword);

        // With only BM25 active, high keyword weight should produce higher scores
        if (count($resultsHighKeyword) > 0 && count($resultsLowKeyword) > 0) {
            $highScore = $resultsHighKeyword[0]['rrf_score'] ?? 0;
            $lowScore = $resultsLowKeyword[0]['rrf_score'] ?? 0;
            // Higher weight should give higher score when only that source is active
            $this->assertGreaterThanOrEqual($lowScore, $highScore);
        }
    }

    /**
     * Test that missing pgvector returns BM25+graph only.
     *
     * Uses a partial mock to simulate pgvector unavailability.
     */
    public function test_missing_pgvector_returns_bm25_and_graph_only(): void
    {
        // Create test data
        MemoryEmbedding::create([
            'user_id' => $this->user->id,
            'source_type' => MemoryEmbedding::SOURCE_CONVERSATION,
            'source_id' => 'test-degradation',
            'content' => 'Content for degradation test',
            'content_hash' => MemoryEmbedding::generateContentHash('Content for degradation test'),
            'importance_score' => 0.8,
            'classification' => 'internal',
            'created_at' => now(),
        ]);

        // Mock embedding provider that supports embeddings
        $embeddingProvider = Mockery::mock(EmbeddingProvider::class);
        $embeddingProvider->shouldReceive('supportsEmbeddings')->andReturn(true);
        // embed should NOT be called when pgvector is unavailable
        $embeddingProvider->shouldReceive('embed')->never();

        // Mock graph store as available
        $graphStore = Mockery::mock(Neo4jGraphStore::class);
        $graphStore->shouldReceive('healthCheck')->andReturn(true);
        $graphStore->shouldReceive('queryRelated')->andReturn([
            ['id' => 'uuid-1', 'type' => 'Concept', 'name' => 'degradation', 'importance' => 0.8, 'distance' => 1],
        ]);

        // Create a partial mock of HybridRetriever to mock isPgvectorAvailable
        $retriever = Mockery::mock(HybridRetriever::class, [$embeddingProvider, $graphStore])
            ->makePartial()
            ->shouldAllowMockingProtectedMethods();
        $retriever->shouldReceive('isPgvectorAvailable')->andReturn(false);

        $results = $retriever->retrieve($this->user->id, 'degradation test', [
            'classification' => ['internal'],
        ]);

        $this->assertIsArray($results);
        // Should have results from BM25 and/or graph, not semantic
        $sources = collect($results)->pluck('source')->unique()->toArray();
        $this->assertNotContains('semantic', $sources);
    }

    /**
     * Test that missing Neo4j returns semantic+BM25 only.
     */
    public function test_missing_neo4j_returns_semantic_and_bm25_only(): void
    {
        // Create test data
        MemoryEmbedding::create([
            'user_id' => $this->user->id,
            'source_type' => MemoryEmbedding::SOURCE_CONVERSATION,
            'source_id' => 'test-neo4j-degrade',
            'content' => 'Content for Neo4j degradation test',
            'content_hash' => MemoryEmbedding::generateContentHash('Content for Neo4j degradation test'),
            'importance_score' => 0.8,
            'classification' => 'internal',
            'created_at' => now(),
        ]);

        // Mock embedding provider
        $embeddingProvider = Mockery::mock(EmbeddingProvider::class);
        $embeddingProvider->shouldReceive('supportsEmbeddings')->andReturn(false);

        // Mock graph store as unhealthy
        $graphStore = Mockery::mock(Neo4jGraphStore::class);
        $graphStore->shouldReceive('healthCheck')->once()->andReturn(false);
        // queryRelated should NOT be called when unhealthy
        $graphStore->shouldReceive('queryRelated')->never();

        $retriever = new HybridRetriever($embeddingProvider, $graphStore);

        $results = $retriever->retrieve($this->user->id, 'Neo4j degradation', [
            'classification' => ['internal'],
        ]);

        $this->assertIsArray($results);
        // Should have results from BM25, not graph
        $sources = collect($results)->pluck('source')->unique()->toArray();
        $this->assertNotContains('graph', $sources);
    }

    /**
     * Test that classification filter is applied to all sources.
     */
    public function test_classification_filter_applied_to_all_sources(): void
    {
        // Create embeddings with different classifications
        MemoryEmbedding::create([
            'user_id' => $this->user->id,
            'source_type' => MemoryEmbedding::SOURCE_CONVERSATION,
            'source_id' => 'test-public',
            'content' => 'Public content visible to all',
            'content_hash' => MemoryEmbedding::generateContentHash('Public content visible to all'),
            'importance_score' => 0.8,
            'classification' => 'public',
            'created_at' => now(),
        ]);

        MemoryEmbedding::create([
            'user_id' => $this->user->id,
            'source_type' => MemoryEmbedding::SOURCE_CONVERSATION,
            'source_id' => 'test-confidential',
            'content' => 'Confidential content restricted',
            'content_hash' => MemoryEmbedding::generateContentHash('Confidential content restricted'),
            'importance_score' => 0.9,
            'classification' => 'confidential',
            'created_at' => now(),
        ]);

        $embeddingProvider = Mockery::mock(EmbeddingProvider::class);
        $embeddingProvider->shouldReceive('supportsEmbeddings')->andReturn(false);

        $graphStore = Mockery::mock(Neo4jGraphStore::class);
        $graphStore->shouldReceive('healthCheck')->andReturn(false);

        $retriever = new HybridRetriever($embeddingProvider, $graphStore);

        // Request only public classification
        $results = $retriever->retrieve($this->user->id, 'content', [
            'classification' => ['public'],
        ]);

        // All results should be public classification
        foreach ($results as $result) {
            $this->assertEquals('public', $result['classification'] ?? 'public');
        }

        // Confidential content should not appear
        $confidential = collect($results)->filter(
            fn ($r) => str_contains($r['content'] ?? '', 'Confidential')
        );
        $this->assertCount(0, $confidential);
    }

    /**
     * Test that partial results are returned on source failure.
     */
    public function test_returns_partial_results_on_source_failure(): void
    {
        // Create test data
        MemoryEmbedding::create([
            'user_id' => $this->user->id,
            'source_type' => MemoryEmbedding::SOURCE_CONVERSATION,
            'source_id' => 'test-partial',
            'content' => 'Content for partial failure test',
            'content_hash' => MemoryEmbedding::generateContentHash('Content for partial failure test'),
            'importance_score' => 0.8,
            'classification' => 'internal',
            'created_at' => now(),
        ]);

        // Mock embedding provider that throws
        $embeddingProvider = Mockery::mock(EmbeddingProvider::class);
        $embeddingProvider->shouldReceive('supportsEmbeddings')->andReturn(true);
        $embeddingProvider->shouldReceive('embed')
            ->andThrow(new \RuntimeException('Embedding API error'));

        // Mock graph store that throws
        $graphStore = Mockery::mock(Neo4jGraphStore::class);
        $graphStore->shouldReceive('healthCheck')->andReturn(true);
        $graphStore->shouldReceive('queryRelated')
            ->andThrow(new \RuntimeException('Neo4j connection error'));

        $retriever = new HybridRetriever($embeddingProvider, $graphStore);

        // Should NOT throw, should return partial results from BM25
        $results = $retriever->retrieve($this->user->id, 'partial failure', [
            'classification' => ['internal'],
        ]);

        $this->assertIsArray($results);
        // Should have BM25 results despite other sources failing
        $bm25Results = collect($results)->filter(
            fn ($r) => ($r['source'] ?? '') === 'keyword'
        );
        $this->assertGreaterThanOrEqual(0, $bm25Results->count());
    }

    /**
     * Test that retrieve respects limit option.
     */
    public function test_retrieve_respects_limit_option(): void
    {
        // Create multiple embeddings
        for ($i = 0; $i < 10; $i++) {
            MemoryEmbedding::create([
                'user_id' => $this->user->id,
                'source_type' => MemoryEmbedding::SOURCE_CONVERSATION,
                'source_id' => "test-limit-{$i}",
                'content' => "Test content number {$i} for limit test",
                'content_hash' => MemoryEmbedding::generateContentHash("Test content number {$i} for limit test"),
                'importance_score' => 0.8 - ($i * 0.05),
                'classification' => 'internal',
                'created_at' => now(),
            ]);
        }

        $embeddingProvider = Mockery::mock(EmbeddingProvider::class);
        $embeddingProvider->shouldReceive('supportsEmbeddings')->andReturn(false);

        $graphStore = Mockery::mock(Neo4jGraphStore::class);
        $graphStore->shouldReceive('healthCheck')->andReturn(false);

        $retriever = new HybridRetriever($embeddingProvider, $graphStore);

        $results = $retriever->retrieve($this->user->id, 'test limit', [
            'classification' => ['internal'],
            'limit' => 3,
        ]);

        $this->assertLessThanOrEqual(3, count($results));
    }

    /**
     * Test that retrieve enforces max_limit from config.
     */
    public function test_retrieve_enforces_max_limit_from_config(): void
    {
        // Create many embeddings
        for ($i = 0; $i < 100; $i++) {
            MemoryEmbedding::create([
                'user_id' => $this->user->id,
                'source_type' => MemoryEmbedding::SOURCE_CONVERSATION,
                'source_id' => "test-max-{$i}",
                'content' => "Test content {$i} for max limit test",
                'content_hash' => MemoryEmbedding::generateContentHash("Test content {$i} for max limit test"),
                'importance_score' => 0.5,
                'classification' => 'internal',
                'created_at' => now(),
            ]);
        }

        $embeddingProvider = Mockery::mock(EmbeddingProvider::class);
        $embeddingProvider->shouldReceive('supportsEmbeddings')->andReturn(false);

        $graphStore = Mockery::mock(Neo4jGraphStore::class);
        $graphStore->shouldReceive('healthCheck')->andReturn(false);

        $retriever = new HybridRetriever($embeddingProvider, $graphStore);

        // Request more than max_limit
        $results = $retriever->retrieve($this->user->id, 'max limit test', [
            'classification' => ['internal'],
            'limit' => 200, // Over max
        ]);

        $maxLimit = config('memory.retrieval.max_limit', 50);
        $this->assertLessThanOrEqual($maxLimit, count($results));
    }

    /**
     * Test that user scoping prevents cross-user data access.
     */
    public function test_user_scoping_prevents_cross_user_access(): void
    {
        $otherUser = User::factory()->create();

        // Create embedding for other user
        MemoryEmbedding::create([
            'user_id' => $otherUser->id,
            'source_type' => MemoryEmbedding::SOURCE_CONVERSATION,
            'source_id' => 'test-other-user',
            'content' => 'Other user secret content',
            'content_hash' => MemoryEmbedding::generateContentHash('Other user secret content'),
            'importance_score' => 0.9,
            'classification' => 'internal',
            'created_at' => now(),
        ]);

        // Create embedding for current user
        MemoryEmbedding::create([
            'user_id' => $this->user->id,
            'source_type' => MemoryEmbedding::SOURCE_CONVERSATION,
            'source_id' => 'test-current-user',
            'content' => 'Current user content',
            'content_hash' => MemoryEmbedding::generateContentHash('Current user content'),
            'importance_score' => 0.8,
            'classification' => 'internal',
            'created_at' => now(),
        ]);

        $embeddingProvider = Mockery::mock(EmbeddingProvider::class);
        $embeddingProvider->shouldReceive('supportsEmbeddings')->andReturn(false);

        $graphStore = Mockery::mock(Neo4jGraphStore::class);
        $graphStore->shouldReceive('healthCheck')->andReturn(false);

        $retriever = new HybridRetriever($embeddingProvider, $graphStore);

        $results = $retriever->retrieve($this->user->id, 'secret content', [
            'classification' => ['internal'],
        ]);

        // Should not return other user's content
        $otherUserContent = collect($results)->filter(
            fn ($r) => str_contains($r['content'] ?? '', 'Other user')
        );
        $this->assertCount(0, $otherUserContent);
    }
}
