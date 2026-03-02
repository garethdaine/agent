<?php

declare(strict_types=1);

namespace Tests\Feature\Memory;

use App\Jobs\Memory\MemoryFormationJob;
use App\Models\AgentJob;
use App\Models\AgentJobRun;
use App\Models\MemoryFormationFailure;
use App\Models\User;
use App\Support\Memory\MemoryFormationPipeline;
use App\Support\Memory\MemoryFormationResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

/**
 * Tests for MemoryFormationJob behavior.
 *
 * Verifies:
 * - Retries 5 times with exponential backoff [10s, 30s, 60s, 120s, 300s]
 * - Creates MemoryFormationFailure on exhaustion
 * - Serializes payload for backfill
 */
class MemoryFormationJobTest extends TestCase
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
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Test that job has correct retry configuration.
     */
    public function test_job_has_correct_retry_configuration(): void
    {
        $job = new MemoryFormationJob($this->run->id);

        // 5 retries as per spec
        $this->assertEquals(5, $job->tries);

        // Exponential backoff: [10, 30, 60, 120, 300]
        $this->assertEquals([10, 30, 60, 120, 300], $job->backoff());

        // Long timeout for API calls
        $this->assertEquals(300, $job->timeout);

        // Correct queue
        $this->assertEquals('memory-formation', $job->queue);
    }

    /**
     * Test that job dispatches to memory-formation queue.
     */
    public function test_job_dispatches_to_memory_formation_queue(): void
    {
        Queue::fake();

        MemoryFormationJob::dispatch($this->run->id);

        Queue::assertPushedOn('memory-formation', MemoryFormationJob::class);
    }

    /**
     * Test that job executes pipeline successfully.
     */
    public function test_job_executes_pipeline_successfully(): void
    {
        $pipeline = Mockery::mock(MemoryFormationPipeline::class);
        $pipeline->shouldReceive('process')
            ->once()
            ->with(Mockery::on(fn ($run) => $run->id === $this->run->id))
            ->andReturn(new MemoryFormationResult(
                success: true,
                conversationLogsCreated: 3,
                embeddingsCreated: 1,
                entitiesStored: 2
            ));

        $this->app->instance(MemoryFormationPipeline::class, $pipeline);

        $job = new MemoryFormationJob($this->run->id);
        $job->handle($pipeline);

        // No failure record should be created
        $this->assertEquals(0, MemoryFormationFailure::count());
    }

    /**
     * Test that job creates failure record on pipeline failure.
     */
    public function test_creates_failure_record_on_pipeline_failure(): void
    {
        $pipeline = Mockery::mock(MemoryFormationPipeline::class);
        $pipeline->shouldReceive('process')
            ->once()
            ->andReturn(new MemoryFormationResult(
                success: false,
                failureType: MemoryFormationFailure::TYPE_EXTRACTION,
                errorMessage: 'Provider API error'
            ));

        $this->app->instance(MemoryFormationPipeline::class, $pipeline);

        $job = new MemoryFormationJob($this->run->id);
        $job->handle($pipeline);

        // Failure record should be created
        $failure = MemoryFormationFailure::first();
        $this->assertNotNull($failure);
        $this->assertEquals($this->run->id, $failure->run_id);
        $this->assertEquals($this->user->id, $failure->user_id);
        $this->assertEquals(MemoryFormationFailure::TYPE_EXTRACTION, $failure->failure_type);
        $this->assertStringContainsString('Provider API error', $failure->error_message);
    }

    /**
     * Test that job serializes payload for backfill.
     */
    public function test_serializes_payload_for_backfill(): void
    {
        $pipeline = Mockery::mock(MemoryFormationPipeline::class);
        $pipeline->shouldReceive('process')
            ->once()
            ->andReturn(new MemoryFormationResult(
                success: false,
                failureType: MemoryFormationFailure::TYPE_EMBEDDING,
                errorMessage: 'Embedding failed',
                partialData: [
                    'conversation_logs_created' => 5,
                    'entities_extracted' => ['Person' => 2, 'API' => 1],
                ]
            ));

        $this->app->instance(MemoryFormationPipeline::class, $pipeline);

        $job = new MemoryFormationJob($this->run->id);
        $job->handle($pipeline);

        $failure = MemoryFormationFailure::first();
        $this->assertNotNull($failure);

        $payload = $failure->getPayload();
        $this->assertArrayHasKey('run_id', $payload);
        $this->assertEquals($this->run->id, $payload['run_id']);
        $this->assertArrayHasKey('partial_data', $payload);
        $this->assertEquals(5, $payload['partial_data']['conversation_logs_created']);
    }

    /**
     * Test that job handles missing run gracefully.
     */
    public function test_handles_missing_run_gracefully(): void
    {
        $pipeline = Mockery::mock(MemoryFormationPipeline::class);
        $pipeline->shouldReceive('process')->never();

        $this->app->instance(MemoryFormationPipeline::class, $pipeline);

        $job = new MemoryFormationJob(999999); // Non-existent run

        // Should not throw
        $job->handle($pipeline);

        // No failure record for missing run
        $this->assertEquals(0, MemoryFormationFailure::count());
    }

    /**
     * Test that job includes correct tags for Horizon.
     */
    public function test_job_includes_correct_tags(): void
    {
        $job = new MemoryFormationJob($this->run->id);

        $tags = $job->tags();

        $this->assertContains('memory', $tags);
        $this->assertContains('formation', $tags);
        $this->assertContains('run:'.$this->run->id, $tags);
    }

    /**
     * Test that job serializes correctly for queue.
     */
    public function test_job_serializes_correctly(): void
    {
        $job = new MemoryFormationJob($this->run->id);

        $serialized = serialize($job);
        $deserialized = unserialize($serialized);

        $this->assertEquals($this->run->id, $deserialized->runId);
    }

    /**
     * Test that failed() method creates exhaustion failure record.
     */
    public function test_failed_method_creates_exhaustion_failure(): void
    {
        $job = new MemoryFormationJob($this->run->id);

        $exception = new \RuntimeException('Final failure after 5 retries');
        $job->failed($exception);

        $failure = MemoryFormationFailure::first();
        $this->assertNotNull($failure);
        $this->assertEquals($this->run->id, $failure->run_id);
        $this->assertEquals($this->user->id, $failure->user_id);
        $this->assertEquals(MemoryFormationFailure::TYPE_TIMEOUT, $failure->failure_type);
        $this->assertStringContainsString('Final failure', $failure->error_message);
    }

    /**
     * Test that job skips if memory is disabled.
     */
    public function test_skips_if_memory_disabled(): void
    {
        config(['memory.enabled' => false]);

        $pipeline = Mockery::mock(MemoryFormationPipeline::class);
        $pipeline->shouldReceive('process')->never();

        $this->app->instance(MemoryFormationPipeline::class, $pipeline);

        $job = new MemoryFormationJob($this->run->id);
        $job->handle($pipeline);

        // No failures, just silently skipped
        $this->assertEquals(0, MemoryFormationFailure::count());
    }

    /**
     * Test that job can be dispatched with onQueue.
     */
    public function test_job_can_be_dispatched_with_queue(): void
    {
        Queue::fake();

        MemoryFormationJob::dispatch($this->run->id)->onQueue('memory-formation');

        Queue::assertPushed(MemoryFormationJob::class, function ($job) {
            return $job->runId === $this->run->id && $job->queue === 'memory-formation';
        });
    }

    /**
     * Test that backoff returns correct values.
     */
    public function test_backoff_returns_correct_values(): void
    {
        $job = new MemoryFormationJob($this->run->id);

        $backoff = $job->backoff();

        $this->assertCount(5, $backoff);
        $this->assertEquals(10, $backoff[0]);
        $this->assertEquals(30, $backoff[1]);
        $this->assertEquals(60, $backoff[2]);
        $this->assertEquals(120, $backoff[3]);
        $this->assertEquals(300, $backoff[4]);
    }

    /**
     * Test that job does not create duplicate failure records.
     */
    public function test_does_not_create_duplicate_failure_records(): void
    {
        // Pre-create a failure record
        MemoryFormationFailure::create([
            'run_id' => $this->run->id,
            'user_id' => $this->user->id,
            'failure_type' => MemoryFormationFailure::TYPE_EXTRACTION,
            'error_message' => 'Previous failure',
            'payload_json' => ['run_id' => $this->run->id],
            'created_at' => now(),
        ]);

        $pipeline = Mockery::mock(MemoryFormationPipeline::class);
        $pipeline->shouldReceive('process')
            ->once()
            ->andReturn(new MemoryFormationResult(
                success: false,
                failureType: MemoryFormationFailure::TYPE_EXTRACTION,
                errorMessage: 'New failure'
            ));

        $this->app->instance(MemoryFormationPipeline::class, $pipeline);

        $job = new MemoryFormationJob($this->run->id);
        $job->handle($pipeline);

        // Should increment attempts on existing record, not create new one
        $failures = MemoryFormationFailure::where('run_id', $this->run->id)->get();
        $this->assertEquals(1, $failures->count());
        $this->assertEquals(2, $failures->first()->attempts);
    }

    /**
     * Test that job handles exception gracefully during handle.
     */
    public function test_handles_exception_during_handle(): void
    {
        $pipeline = Mockery::mock(MemoryFormationPipeline::class);
        $pipeline->shouldReceive('process')
            ->once()
            ->andThrow(new \RuntimeException('Unexpected error'));

        $this->app->instance(MemoryFormationPipeline::class, $pipeline);

        $job = new MemoryFormationJob($this->run->id);

        $this->expectException(\RuntimeException::class);
        $job->handle($pipeline);
    }

    /**
     * Test that shouldQueue returns false when memory is disabled.
     */
    public function test_should_not_queue_when_memory_disabled(): void
    {
        config(['memory.enabled' => false]);

        $job = new MemoryFormationJob($this->run->id);

        // The shouldQueue method should return false
        $this->assertFalse($job->shouldQueue());
    }

    /**
     * Test that shouldQueue returns true when memory is enabled.
     */
    public function test_should_queue_when_memory_enabled(): void
    {
        config(['memory.enabled' => true]);

        $job = new MemoryFormationJob($this->run->id);

        $this->assertTrue($job->shouldQueue());
    }
}
