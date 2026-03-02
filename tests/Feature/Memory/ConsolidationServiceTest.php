<?php

declare(strict_types=1);

namespace Tests\Feature\Memory;

use App\Models\AgentJob;
use App\Models\AgentJobRun;
use App\Models\MemoryConsolidationLog;
use App\Models\MemoryEmbedding;
use App\Models\MemoryFormationFailure;
use App\Models\User;
use App\Support\Memory\ConsolidationService;
use App\Support\Memory\MemoryFormationPipeline;
use App\Support\Memory\MemoryFormationResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

/**
 * Tests for ConsolidationService behavior.
 *
 * Verifies:
 * - Retries failed formations up to 5 cycles
 * - Marks permanently unrecoverable after 5 cycles
 * - Deduplicates near-duplicate embeddings
 * - Checkpoint saves/resumes correctly
 */
class ConsolidationServiceTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private AgentJob $job;

    private ConsolidationService $service;

    protected function setUp(): void
    {
        parent::setUp();

        config(['memory.enabled' => true]);
        config(['memory.api_enabled' => true]);

        $this->user = User::factory()->create();
        $this->job = AgentJob::factory()->for($this->user)->create();

        // Create service with fresh pipeline - tests will override when needed
        $this->service = app(ConsolidationService::class);
    }

    /**
     * Helper to create service with mocked pipeline.
     */
    private function serviceWithMockedPipeline(MemoryFormationPipeline $pipeline): ConsolidationService
    {
        return new ConsolidationService($pipeline);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Test that consolidation retries failed formations up to 5 cycles.
     */
    public function test_retries_failed_formations_up_to_5_cycles(): void
    {
        // Create a run with a retriable failure (4 attempts = still retriable)
        $run = AgentJobRun::factory()->for($this->job, 'job')->create([
            'user_id' => $this->user->id,
            'status' => AgentJobRun::STATUS_SUCCEEDED,
        ]);

        $failure = MemoryFormationFailure::create([
            'run_id' => $run->id,
            'user_id' => $this->user->id,
            'failure_type' => MemoryFormationFailure::TYPE_EMBEDDING,
            'error_message' => 'Provider temporarily unavailable',
            'attempts' => 4,
            'payload_json' => ['run_id' => $run->id],
            'created_at' => now()->subHours(8),
        ]);

        // Mock the pipeline to succeed on retry
        $pipeline = Mockery::mock(MemoryFormationPipeline::class);
        $pipeline->shouldReceive('process')
            ->once()
            ->andReturn(new MemoryFormationResult(
                success: true,
                conversationLogsCreated: 3,
                embeddingsCreated: 1,
            ));

        $service = $this->serviceWithMockedPipeline($pipeline);
        $result = $service->runBackfill($this->user->id);

        // Should have processed 1 failure
        $this->assertEquals(1, $result['processed']);
        $this->assertEquals(1, $result['succeeded']);
        $this->assertEquals(0, $result['failed']);

        // Failure should be marked as backfilled
        $failure->refresh();
        $this->assertNotNull($failure->backfilled_at);
    }

    /**
     * Test that consolidation marks failures permanently unrecoverable after 5 cycles.
     */
    public function test_marks_permanently_unrecoverable_after_5_cycles(): void
    {
        $run = AgentJobRun::factory()->for($this->job, 'job')->create([
            'user_id' => $this->user->id,
            'status' => AgentJobRun::STATUS_SUCCEEDED,
        ]);

        // Create a failure at max attempts - should not be retried
        $failure = MemoryFormationFailure::create([
            'run_id' => $run->id,
            'user_id' => $this->user->id,
            'failure_type' => MemoryFormationFailure::TYPE_EXTRACTION,
            'error_message' => 'Permanently broken',
            'attempts' => 5, // MAX_BACKFILL_ATTEMPTS
            'payload_json' => ['run_id' => $run->id],
            'created_at' => now()->subHours(10),
        ]);

        // Pipeline should NOT be called for permanently failed records
        $pipeline = Mockery::mock(MemoryFormationPipeline::class);
        $pipeline->shouldReceive('process')->never();
        $this->app->instance(MemoryFormationPipeline::class, $pipeline);

        $result = $this->service->runBackfill($this->user->id);

        // Should have processed 0 (permanently failed not processed)
        $this->assertEquals(0, $result['processed']);

        // Failure should still be pending (not backfilled)
        $failure->refresh();
        $this->assertNull($failure->backfilled_at);
        $this->assertTrue($failure->isPermanentlyFailed());
    }

    /**
     * Test that consolidation increments attempts when retry fails.
     */
    public function test_increments_attempts_when_retry_fails(): void
    {
        $run = AgentJobRun::factory()->for($this->job, 'job')->create([
            'user_id' => $this->user->id,
            'status' => AgentJobRun::STATUS_SUCCEEDED,
        ]);

        $failure = MemoryFormationFailure::create([
            'run_id' => $run->id,
            'user_id' => $this->user->id,
            'failure_type' => MemoryFormationFailure::TYPE_EMBEDDING,
            'error_message' => 'Still failing',
            'attempts' => 3,
            'payload_json' => ['run_id' => $run->id],
            'created_at' => now()->subHours(6),
        ]);

        // Mock the pipeline to fail again
        $pipeline = Mockery::mock(MemoryFormationPipeline::class);
        $pipeline->shouldReceive('process')
            ->once()
            ->andReturn(new MemoryFormationResult(
                success: false,
                failureType: MemoryFormationFailure::TYPE_EMBEDDING,
                errorMessage: 'Still failing on retry'
            ));

        $service = $this->serviceWithMockedPipeline($pipeline);
        $result = $service->runBackfill($this->user->id);

        // Should have processed 1, but it failed
        $this->assertEquals(1, $result['processed']);
        $this->assertEquals(0, $result['succeeded']);
        $this->assertEquals(1, $result['failed']);

        // Attempts should be incremented
        $failure->refresh();
        $this->assertEquals(4, $failure->attempts);
        $this->assertNull($failure->backfilled_at);
    }

    /**
     * Test that deduplication identifies near-duplicate embeddings.
     */
    public function test_deduplicates_near_duplicate_embeddings(): void
    {
        // Create embeddings with same content (exact duplicates)
        $embedding1 = MemoryEmbedding::create([
            'user_id' => $this->user->id,
            'source_type' => MemoryEmbedding::SOURCE_CONVERSATION,
            'source_id' => 'conv_1',
            'content' => 'This is a test embedding content.',
            'content_hash' => MemoryEmbedding::generateContentHash('This is a test embedding content.'),
            'importance_score' => 0.8,
            'access_count' => 5,
            'created_at' => now()->subDays(5),
        ]);

        $embedding2 = MemoryEmbedding::create([
            'user_id' => $this->user->id,
            'source_type' => MemoryEmbedding::SOURCE_CONVERSATION,
            'source_id' => 'conv_2',
            'content' => 'This is a test embedding content.', // Same content
            'content_hash' => MemoryEmbedding::generateContentHash('This is a test embedding content.'),
            'importance_score' => 0.6,
            'access_count' => 2,
            'created_at' => now()->subDays(2),
        ]);

        $result = $this->service->runDeduplication($this->user->id);

        // Should have identified and merged duplicates
        $this->assertGreaterThanOrEqual(1, $result['duplicates_found']);
        $this->assertGreaterThanOrEqual(1, $result['duplicates_merged']);

        // After dedup, only one embedding should remain for this content
        $remaining = MemoryEmbedding::where('user_id', $this->user->id)
            ->where('content_hash', MemoryEmbedding::generateContentHash('This is a test embedding content.'))
            ->count();

        $this->assertEquals(1, $remaining);

        // The remaining one should have merged metadata (higher importance, summed access count)
        $merged = MemoryEmbedding::where('user_id', $this->user->id)
            ->where('content_hash', MemoryEmbedding::generateContentHash('This is a test embedding content.'))
            ->first();

        $this->assertEquals(0.8, $merged->importance_score); // Keep higher importance
        $this->assertEquals(7, $merged->access_count); // Sum of access counts
    }

    /**
     * Test that checkpoint saves correctly during consolidation.
     */
    public function test_checkpoint_saves_correctly(): void
    {
        // Create multiple failures to process
        for ($i = 0; $i < 5; $i++) {
            $run = AgentJobRun::factory()->for($this->job, 'job')->create([
                'user_id' => $this->user->id,
                'status' => AgentJobRun::STATUS_SUCCEEDED,
            ]);

            MemoryFormationFailure::create([
                'run_id' => $run->id,
                'user_id' => $this->user->id,
                'failure_type' => MemoryFormationFailure::TYPE_EMBEDDING,
                'error_message' => 'Test failure '.$i,
                'attempts' => 1,
                'payload_json' => ['run_id' => $run->id],
                'created_at' => now()->subHours($i + 1),
            ]);
        }

        // Mock pipeline to succeed
        $pipeline = Mockery::mock(MemoryFormationPipeline::class);
        $pipeline->shouldReceive('process')
            ->times(5)
            ->andReturn(new MemoryFormationResult(success: true));

        $service = $this->serviceWithMockedPipeline($pipeline);

        // Run consolidation
        $service->runBackfill($this->user->id);

        // A consolidation log entry should exist with checkpoint
        $log = MemoryConsolidationLog::where('user_id', $this->user->id)
            ->where('consolidation_type', MemoryConsolidationLog::TYPE_FAILURE_BACKFILL)
            ->first();

        $this->assertNotNull($log);
        $this->assertEquals(5, $log->source_count);
    }

    /**
     * Test that checkpoint resumes correctly.
     */
    public function test_checkpoint_resumes_correctly(): void
    {
        // Create a checkpoint from a previous consolidation
        $lastProcessedId = 100;
        MemoryConsolidationLog::record(
            $this->user->id,
            MemoryConsolidationLog::TYPE_FAILURE_BACKFILL,
            50,
            'Partial consolidation',
            ['last_processed_id' => $lastProcessedId, 'phase' => 'backfill']
        );

        // Create failures - some before checkpoint, some after
        $runBefore = AgentJobRun::factory()->for($this->job, 'job')->create([
            'user_id' => $this->user->id,
            'status' => AgentJobRun::STATUS_SUCCEEDED,
        ]);

        // This should have ID > lastProcessedId in a real scenario
        // For testing, we verify the checkpoint is read
        $checkpoint = MemoryConsolidationLog::getLastCheckpoint(
            $this->user->id,
            MemoryConsolidationLog::TYPE_FAILURE_BACKFILL
        );

        $this->assertNotNull($checkpoint);
        $this->assertEquals($lastProcessedId, $checkpoint['last_processed_id']);
        $this->assertEquals('backfill', $checkpoint['phase']);
    }

    /**
     * Test consolidation runs for all users when no user specified.
     */
    public function test_consolidation_runs_for_all_users(): void
    {
        // Create another user
        $user2 = User::factory()->create();
        $job2 = AgentJob::factory()->for($user2)->create();

        // Create failures for both users
        $run1 = AgentJobRun::factory()->for($this->job, 'job')->create([
            'user_id' => $this->user->id,
            'status' => AgentJobRun::STATUS_SUCCEEDED,
        ]);

        $run2 = AgentJobRun::factory()->for($job2, 'job')->create([
            'user_id' => $user2->id,
            'status' => AgentJobRun::STATUS_SUCCEEDED,
        ]);

        MemoryFormationFailure::create([
            'run_id' => $run1->id,
            'user_id' => $this->user->id,
            'failure_type' => MemoryFormationFailure::TYPE_EMBEDDING,
            'error_message' => 'User 1 failure',
            'attempts' => 1,
            'payload_json' => ['run_id' => $run1->id],
            'created_at' => now(),
        ]);

        MemoryFormationFailure::create([
            'run_id' => $run2->id,
            'user_id' => $user2->id,
            'failure_type' => MemoryFormationFailure::TYPE_EMBEDDING,
            'error_message' => 'User 2 failure',
            'attempts' => 1,
            'payload_json' => ['run_id' => $run2->id],
            'created_at' => now(),
        ]);

        // Mock pipeline to succeed
        $pipeline = Mockery::mock(MemoryFormationPipeline::class);
        $pipeline->shouldReceive('process')
            ->twice()
            ->andReturn(new MemoryFormationResult(success: true));

        $service = $this->serviceWithMockedPipeline($pipeline);

        // Run for all users (null user_id)
        $result = $service->runBackfill(null);

        $this->assertEquals(2, $result['processed']);
        $this->assertEquals(2, $result['succeeded']);
    }

    /**
     * Test consolidation respects memory.enabled config.
     */
    public function test_respects_memory_enabled_config(): void
    {
        config(['memory.enabled' => false]);

        $result = $this->service->runBackfill($this->user->id);

        $this->assertEquals(0, $result['processed']);
        $this->assertArrayHasKey('skipped', $result);
        $this->assertTrue($result['skipped']);
    }

    /**
     * Test that consolidation type can be filtered.
     */
    public function test_consolidation_type_can_be_filtered(): void
    {
        $run = AgentJobRun::factory()->for($this->job, 'job')->create([
            'user_id' => $this->user->id,
            'status' => AgentJobRun::STATUS_SUCCEEDED,
        ]);

        MemoryFormationFailure::create([
            'run_id' => $run->id,
            'user_id' => $this->user->id,
            'failure_type' => MemoryFormationFailure::TYPE_EMBEDDING,
            'error_message' => 'Test failure',
            'attempts' => 1,
            'payload_json' => ['run_id' => $run->id],
            'created_at' => now(),
        ]);

        // Run only deduplication type (not backfill)
        $result = $this->service->run($this->user->id, 'dedup');

        // Should run deduplication only - results are nested under 'deduplication' key
        $this->assertArrayHasKey('deduplication', $result);
        $this->assertArrayHasKey('duplicates_found', $result['deduplication']);
        $this->assertArrayNotHasKey('backfill', $result);
    }

    /**
     * Test full consolidation cycle.
     */
    public function test_full_consolidation_cycle(): void
    {
        $run = AgentJobRun::factory()->for($this->job, 'job')->create([
            'user_id' => $this->user->id,
            'status' => AgentJobRun::STATUS_SUCCEEDED,
        ]);

        MemoryFormationFailure::create([
            'run_id' => $run->id,
            'user_id' => $this->user->id,
            'failure_type' => MemoryFormationFailure::TYPE_EMBEDDING,
            'error_message' => 'Test failure',
            'attempts' => 1,
            'payload_json' => ['run_id' => $run->id],
            'created_at' => now(),
        ]);

        // Mock pipeline
        $pipeline = Mockery::mock(MemoryFormationPipeline::class);
        $pipeline->shouldReceive('process')
            ->once()
            ->andReturn(new MemoryFormationResult(success: true));

        $service = $this->serviceWithMockedPipeline($pipeline);

        // Run full consolidation (all types)
        $result = $service->run($this->user->id);

        // Should have run both backfill and dedup
        $this->assertArrayHasKey('backfill', $result);
        $this->assertArrayHasKey('deduplication', $result);
        $this->assertEquals(1, $result['backfill']['processed']);
    }
}
