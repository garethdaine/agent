<?php

declare(strict_types=1);

namespace Tests\Feature\Memory;

use App\Models\AgentJob;
use App\Models\AgentJobRun;
use App\Models\MemoryConsolidationLog;
use App\Models\MemoryEmbedding;
use App\Models\User;
use App\Support\Memory\ForgettingService;
use App\Support\Memory\Neo4jGraphStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

/**
 * Tests for ForgettingService behavior.
 *
 * Verifies:
 * - Prunes embeddings below 0.1 decay score
 * - Soft-deletes graph entities below 0.2 after 90 days
 * - Dry-run returns items without deleting
 * - --force actually deletes
 */
class ForgettingServiceTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private AgentJob $job;

    private AgentJobRun $run;

    private ForgettingService $service;

    protected function setUp(): void
    {
        parent::setUp();

        config(['memory.enabled' => true]);
        config(['memory.api_enabled' => true]);
        config(['memory.forgetting.embedding_decay_threshold' => 0.1]);
        config(['memory.forgetting.graph_importance_threshold' => 0.2]);
        config(['memory.forgetting.graph_retention_days' => 90]);

        $this->user = User::factory()->create();
        $this->job = AgentJob::factory()->for($this->user)->create();
        $this->run = AgentJobRun::factory()->for($this->job, 'job')->create([
            'user_id' => $this->user->id,
            'status' => AgentJobRun::STATUS_SUCCEEDED,
        ]);
        $this->service = app(ForgettingService::class);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Test that embeddings below 0.1 decay score are pruned.
     */
    public function test_prunes_embeddings_below_decay_threshold(): void
    {
        // Create embeddings with different decay scores
        // Low decay score (old, very low importance, no access)
        // Formula: importance × recency_decay × access_bonus
        // With 0.05 importance, 180 days old: recency_decay = 0.5^(180/30) = 0.5^6 = 0.0156
        // accessBonus = 1 + (log10(1) * 0.1) = 1
        // Score = 0.05 * 0.0156 * 1 = 0.00078 (well below 0.1)
        $oldEmbedding = MemoryEmbedding::create([
            'user_id' => $this->user->id,
            'source_type' => MemoryEmbedding::SOURCE_CONVERSATION,
            'source_id' => 'conv_old',
            'content' => 'Old content that should be pruned.',
            'content_hash' => MemoryEmbedding::generateContentHash('Old content that should be pruned.'),
            'importance_score' => 0.05, // Very low importance
            'access_count' => 0,
            'last_accessed_at' => null, // Never accessed - will use created_at
            'created_at' => now()->subDays(180),
        ]);

        // Verify the old embedding decay score is below threshold
        $oldDecay = $oldEmbedding->calculateDecayScore();
        $this->assertLessThan(0.1, $oldDecay, "Old embedding decay score {$oldDecay} should be below 0.1");

        // High decay score (recent, high importance, accessed)
        // With 0.8 importance, 2 days old: recency_decay = 0.5^(2/30) = 0.954
        // accessBonus = 1 + (log10(10) * 0.1) = 1.1
        // Score = 0.8 * 0.954 * 1.1 = 0.84 (well above 0.1)
        $recentEmbedding = MemoryEmbedding::create([
            'user_id' => $this->user->id,
            'source_type' => MemoryEmbedding::SOURCE_CONVERSATION,
            'source_id' => 'conv_recent',
            'content' => 'Recent content that should be kept.',
            'content_hash' => MemoryEmbedding::generateContentHash('Recent content that should be kept.'),
            'importance_score' => 0.8,
            'access_count' => 10,
            'last_accessed_at' => now()->subDays(2),
            'created_at' => now()->subDays(7),
        ]);

        // Verify the recent embedding decay score is above threshold
        $recentDecay = $recentEmbedding->calculateDecayScore();
        $this->assertGreaterThan(0.1, $recentDecay, "Recent embedding decay score {$recentDecay} should be above 0.1");

        // Force prune
        $result = $this->service->prune($this->user->id, dryRun: false);

        $this->assertGreaterThanOrEqual(1, $result['embeddings_pruned']);

        // Old embedding should be deleted
        $this->assertNull(MemoryEmbedding::find($oldEmbedding->id));

        // Recent embedding should remain
        $this->assertNotNull(MemoryEmbedding::find($recentEmbedding->id));
    }

    /**
     * Test that graph entities below 0.2 importance after 90 days are soft-deleted.
     */
    public function test_soft_deletes_graph_entities_below_threshold(): void
    {
        $graphStore = Mockery::mock(Neo4jGraphStore::class);

        // Expect the graph store to receive a prune call
        $graphStore->shouldReceive('healthCheck')->andReturn(true);
        $graphStore->shouldReceive('pruneEntities')
            ->once()
            ->with(
                $this->user->id,
                Mockery::on(fn ($threshold) => abs($threshold - 0.2) < 0.001),
                Mockery::on(fn ($days) => $days === 90),
                Mockery::on(fn ($dryRun) => $dryRun === false)
            )
            ->andReturn(['pruned' => 5]);

        // Create service with mocked graph store
        $service = new ForgettingService($graphStore);

        $result = $service->prune($this->user->id, dryRun: false);

        $this->assertArrayHasKey('graph_entities_pruned', $result);
        $this->assertEquals(5, $result['graph_entities_pruned']);
    }

    /**
     * Test that dry-run returns items without deleting.
     */
    public function test_dry_run_returns_items_without_deleting(): void
    {
        // Create an embedding that should be pruned
        $oldEmbedding = MemoryEmbedding::create([
            'user_id' => $this->user->id,
            'source_type' => MemoryEmbedding::SOURCE_CONVERSATION,
            'source_id' => 'conv_old',
            'content' => 'Old content that would be pruned.',
            'content_hash' => MemoryEmbedding::generateContentHash('Old content that would be pruned.'),
            'importance_score' => 0.05, // Very low importance
            'access_count' => 0,
            'last_accessed_at' => now()->subDays(200),
            'created_at' => now()->subDays(300),
        ]);

        // Default is dry-run
        $result = $this->service->prune($this->user->id);

        // Should report what would be pruned
        $this->assertTrue($result['dry_run']);
        $this->assertGreaterThanOrEqual(1, $result['embeddings_would_prune']);

        // But embedding should still exist
        $this->assertNotNull(MemoryEmbedding::find($oldEmbedding->id));
    }

    /**
     * Test that --force flag actually deletes.
     */
    public function test_force_flag_actually_deletes(): void
    {
        // Create an embedding that should be pruned
        $oldEmbedding = MemoryEmbedding::create([
            'user_id' => $this->user->id,
            'source_type' => MemoryEmbedding::SOURCE_CONVERSATION,
            'source_id' => 'conv_old',
            'content' => 'Content to be forcefully pruned.',
            'content_hash' => MemoryEmbedding::generateContentHash('Content to be forcefully pruned.'),
            'importance_score' => 0.01,
            'access_count' => 0,
            'last_accessed_at' => now()->subDays(365),
            'created_at' => now()->subDays(400),
        ]);

        // Force mode (not dry-run)
        $result = $this->service->prune($this->user->id, dryRun: false);

        // Should report actual deletion
        $this->assertFalse($result['dry_run']);
        $this->assertGreaterThanOrEqual(1, $result['embeddings_pruned']);

        // Embedding should be deleted
        $this->assertNull(MemoryEmbedding::find($oldEmbedding->id));
    }

    /**
     * Test prune respects user scope.
     */
    public function test_prune_respects_user_scope(): void
    {
        $user2 = User::factory()->create();

        // Create embedding for user 1 that should be pruned
        $user1Embedding = MemoryEmbedding::create([
            'user_id' => $this->user->id,
            'source_type' => MemoryEmbedding::SOURCE_CONVERSATION,
            'source_id' => 'conv_1',
            'content' => 'User 1 old content.',
            'content_hash' => MemoryEmbedding::generateContentHash('User 1 old content.'),
            'importance_score' => 0.01,
            'access_count' => 0,
            'last_accessed_at' => now()->subDays(365),
            'created_at' => now()->subDays(400),
        ]);

        // Create embedding for user 2 that should be pruned
        $user2Embedding = MemoryEmbedding::create([
            'user_id' => $user2->id,
            'source_type' => MemoryEmbedding::SOURCE_CONVERSATION,
            'source_id' => 'conv_2',
            'content' => 'User 2 old content.',
            'content_hash' => MemoryEmbedding::generateContentHash('User 2 old content.'),
            'importance_score' => 0.01,
            'access_count' => 0,
            'last_accessed_at' => now()->subDays(365),
            'created_at' => now()->subDays(400),
        ]);

        // Prune only user 1
        $this->service->prune($this->user->id, dryRun: false);

        // User 1's embedding should be deleted
        $this->assertNull(MemoryEmbedding::find($user1Embedding->id));

        // User 2's embedding should still exist
        $this->assertNotNull(MemoryEmbedding::find($user2Embedding->id));
    }

    /**
     * Test prune logs consolidation entry.
     */
    public function test_prune_logs_consolidation_entry(): void
    {
        MemoryEmbedding::create([
            'user_id' => $this->user->id,
            'source_type' => MemoryEmbedding::SOURCE_CONVERSATION,
            'source_id' => 'conv_1',
            'content' => 'Content to prune.',
            'content_hash' => MemoryEmbedding::generateContentHash('Content to prune.'),
            'importance_score' => 0.01,
            'access_count' => 0,
            'last_accessed_at' => now()->subDays(365),
            'created_at' => now()->subDays(400),
        ]);

        $this->service->prune($this->user->id, dryRun: false);

        $log = MemoryConsolidationLog::where('user_id', $this->user->id)
            ->where('consolidation_type', MemoryConsolidationLog::TYPE_PRUNE)
            ->first();

        $this->assertNotNull($log);
        $this->assertGreaterThanOrEqual(1, $log->source_count);
    }

    /**
     * Test prune handles empty result gracefully.
     */
    public function test_prune_handles_empty_result(): void
    {
        // No embeddings to prune
        $result = $this->service->prune($this->user->id, dryRun: false);

        $this->assertEquals(0, $result['embeddings_pruned']);
        $this->assertFalse($result['dry_run']);
    }

    /**
     * Test prune uses configurable thresholds.
     */
    public function test_prune_uses_configurable_thresholds(): void
    {
        // Set a higher threshold - more aggressive pruning
        config(['memory.forgetting.embedding_decay_threshold' => 0.5]);

        // Create embedding with moderate decay score
        $embedding = MemoryEmbedding::create([
            'user_id' => $this->user->id,
            'source_type' => MemoryEmbedding::SOURCE_CONVERSATION,
            'source_id' => 'conv_1',
            'content' => 'Moderate decay content.',
            'content_hash' => MemoryEmbedding::generateContentHash('Moderate decay content.'),
            'importance_score' => 0.3,
            'access_count' => 1,
            'last_accessed_at' => now()->subDays(60),
            'created_at' => now()->subDays(90),
        ]);

        // Refresh service to pick up new config
        $this->service = app(ForgettingService::class);

        $result = $this->service->prune($this->user->id, dryRun: false);

        // Should be pruned with higher threshold
        $this->assertNull(MemoryEmbedding::find($embedding->id));
    }

    /**
     * Test that prune respects memory.enabled config.
     */
    public function test_respects_memory_enabled_config(): void
    {
        config(['memory.enabled' => false]);

        // Create embedding that would normally be pruned
        $embedding = MemoryEmbedding::create([
            'user_id' => $this->user->id,
            'source_type' => MemoryEmbedding::SOURCE_CONVERSATION,
            'source_id' => 'conv_1',
            'content' => 'Old content.',
            'content_hash' => MemoryEmbedding::generateContentHash('Old content.'),
            'importance_score' => 0.01,
            'access_count' => 0,
            'last_accessed_at' => now()->subDays(365),
            'created_at' => now()->subDays(400),
        ]);

        $result = $this->service->prune($this->user->id, dryRun: false);

        $this->assertTrue($result['skipped']);

        // Embedding should NOT be pruned
        $this->assertNotNull(MemoryEmbedding::find($embedding->id));
    }

    /**
     * Test prune for all users when no user specified.
     */
    public function test_prune_for_all_users(): void
    {
        $user2 = User::factory()->create();

        // Create old embeddings for both users
        $emb1 = MemoryEmbedding::create([
            'user_id' => $this->user->id,
            'source_type' => MemoryEmbedding::SOURCE_CONVERSATION,
            'source_id' => 'conv_1',
            'content' => 'User 1 old.',
            'content_hash' => MemoryEmbedding::generateContentHash('User 1 old.'),
            'importance_score' => 0.01,
            'access_count' => 0,
            'last_accessed_at' => now()->subDays(365),
            'created_at' => now()->subDays(400),
        ]);

        $emb2 = MemoryEmbedding::create([
            'user_id' => $user2->id,
            'source_type' => MemoryEmbedding::SOURCE_CONVERSATION,
            'source_id' => 'conv_2',
            'content' => 'User 2 old.',
            'content_hash' => MemoryEmbedding::generateContentHash('User 2 old.'),
            'importance_score' => 0.01,
            'access_count' => 0,
            'last_accessed_at' => now()->subDays(365),
            'created_at' => now()->subDays(400),
        ]);

        // Prune all users (null)
        $result = $this->service->prune(null, dryRun: false);

        $this->assertGreaterThanOrEqual(2, $result['embeddings_pruned']);

        // Both should be deleted
        $this->assertNull(MemoryEmbedding::find($emb1->id));
        $this->assertNull(MemoryEmbedding::find($emb2->id));
    }

    /**
     * Test dry-run result includes details of what would be pruned.
     */
    public function test_dry_run_includes_pruning_details(): void
    {
        MemoryEmbedding::create([
            'user_id' => $this->user->id,
            'source_type' => MemoryEmbedding::SOURCE_CONVERSATION,
            'source_id' => 'conv_1',
            'content' => 'Old content 1.',
            'content_hash' => MemoryEmbedding::generateContentHash('Old content 1.'),
            'importance_score' => 0.01,
            'access_count' => 0,
            'last_accessed_at' => now()->subDays(365),
            'created_at' => now()->subDays(400),
        ]);

        MemoryEmbedding::create([
            'user_id' => $this->user->id,
            'source_type' => MemoryEmbedding::SOURCE_CONVERSATION,
            'source_id' => 'conv_2',
            'content' => 'Old content 2.',
            'content_hash' => MemoryEmbedding::generateContentHash('Old content 2.'),
            'importance_score' => 0.02,
            'access_count' => 0,
            'last_accessed_at' => now()->subDays(300),
            'created_at' => now()->subDays(350),
        ]);

        $result = $this->service->prune($this->user->id);

        $this->assertTrue($result['dry_run']);
        $this->assertArrayHasKey('embeddings_would_prune', $result);
        $this->assertArrayHasKey('candidates', $result);
        $this->assertGreaterThanOrEqual(2, count($result['candidates']));
    }

    /**
     * Test that working memory TTL pruning is handled.
     */
    public function test_working_memory_ttl_pruning(): void
    {
        // This test verifies that working memory Redis keys with TTL
        // are handled - actual Redis TTL is automatic
        $result = $this->service->prune($this->user->id, dryRun: false);

        // Working memory pruning should be reported
        $this->assertArrayHasKey('working_memory_expired', $result);
    }
}
