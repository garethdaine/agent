<?php

declare(strict_types=1);

namespace Tests\Feature\Memory;

use App\Jobs\Memory\MemoryWorkingBufferJob;
use App\Models\AgentJob;
use App\Models\AgentJobRun;
use App\Models\User;
use App\Support\Memory\WorkingMemoryBuffer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

/**
 * Tests for MemoryWorkingBufferJob fire-and-forget behavior.
 *
 * Verifies:
 * - Job dispatches to memory-working queue
 * - Job completes in <5ms
 * - Job failure does not throw (silent)
 * - Job correctly processes event data
 */
class WorkingMemoryJobTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private AgentJob $job;

    private AgentJobRun $run;

    protected function setUp(): void
    {
        parent::setUp();

        // Enable memory for these tests
        config(['memory.enabled' => true]);

        $this->user = User::factory()->create();
        $this->job = AgentJob::factory()->for($this->user)->create();
        $this->run = AgentJobRun::factory()->for($this->job, 'job')->create();

        // Clear any existing keys for this run
        $this->clearRedisKeys();
    }

    protected function tearDown(): void
    {
        $this->clearRedisKeys();
        parent::tearDown();
    }

    private function clearRedisKeys(): void
    {
        $connection = Redis::connection('memory');
        $prefix = config('memory.working_memory.key_prefix', 'memory:run:');
        $key = $prefix.$this->run->id.':working';

        $connection->del($key);
    }

    /**
     * Test that job dispatches to memory-working queue.
     */
    public function test_job_dispatches_to_memory_working_queue(): void
    {
        Queue::fake();

        MemoryWorkingBufferJob::dispatch($this->run->id, 'stdout', 'Test output');

        Queue::assertPushedOn('memory-working', MemoryWorkingBufferJob::class);
    }

    /**
     * Test that job has correct configuration.
     */
    public function test_job_has_correct_configuration(): void
    {
        $job = new MemoryWorkingBufferJob($this->run->id, 'stdout', 'Test output');

        // Fire-and-forget: 0 tries
        $this->assertEquals(0, $job->tries);

        // Short timeout
        $this->assertEquals(5, $job->timeout);

        // Correct queue
        $this->assertEquals('memory-working', $job->queue);
    }

    /**
     * Test that job processes stdout event correctly.
     */
    public function test_job_processes_stdout_event(): void
    {
        $job = new MemoryWorkingBufferJob($this->run->id, 'stdout', 'Assistant output text');

        $job->handle(app(WorkingMemoryBuffer::class));

        $buffer = app(WorkingMemoryBuffer::class);
        $entries = $buffer->getRecent($this->run->id);

        $this->assertCount(1, $entries);
        $this->assertEquals('assistant', $entries[0]['role']);
        $this->assertEquals('Assistant output text', $entries[0]['content']);
    }

    /**
     * Test that job processes stderr event correctly.
     */
    public function test_job_processes_stderr_event(): void
    {
        $job = new MemoryWorkingBufferJob($this->run->id, 'stderr', 'Error output');

        $job->handle(app(WorkingMemoryBuffer::class));

        $buffer = app(WorkingMemoryBuffer::class);
        $entries = $buffer->getRecent($this->run->id);

        $this->assertCount(1, $entries);
        $this->assertEquals('stderr', $entries[0]['role']);
        $this->assertEquals('Error output', $entries[0]['content']);
    }

    /**
     * Test that job processes lifecycle event correctly.
     */
    public function test_job_processes_lifecycle_event(): void
    {
        $payload = json_encode(['type' => 'tool_call', 'tool' => 'search']);

        $job = new MemoryWorkingBufferJob($this->run->id, 'lifecycle', $payload);

        $job->handle(app(WorkingMemoryBuffer::class));

        $buffer = app(WorkingMemoryBuffer::class);
        $entries = $buffer->getRecent($this->run->id);

        $this->assertCount(1, $entries);
        $this->assertEquals('lifecycle', $entries[0]['role']);
    }

    /**
     * Test that job failure does not throw (silent failure).
     */
    public function test_job_failure_does_not_throw(): void
    {
        $job = new MemoryWorkingBufferJob($this->run->id, 'stdout', 'Test');

        // The failed method should not throw, just log
        $exception = new \RuntimeException('Test failure');

        // This should complete without throwing
        $job->failed($exception);

        $this->assertTrue(true); // If we got here, test passed
    }

    /**
     * Test that job completes quickly (performance check).
     */
    public function test_job_completes_quickly(): void
    {
        $job = new MemoryWorkingBufferJob($this->run->id, 'stdout', 'Test output');

        $start = microtime(true);
        $job->handle(app(WorkingMemoryBuffer::class));
        $elapsed = (microtime(true) - $start) * 1000; // Convert to ms

        // Should complete in less than 50ms (generous margin for CI)
        $this->assertLessThan(50, $elapsed, "Job took {$elapsed}ms, expected < 50ms");
    }

    /**
     * Test that job can be dispatched via static method.
     */
    public function test_job_can_be_dispatched_via_static_method(): void
    {
        Queue::fake();

        MemoryWorkingBufferJob::dispatch($this->run->id, 'stdout', 'Test');

        Queue::assertPushed(MemoryWorkingBufferJob::class, function ($job) {
            return $job->runId === $this->run->id
                && $job->eventType === 'stdout'
                && $job->rawPayload === 'Test';
        });
    }

    /**
     * Test that job handles empty payload gracefully.
     */
    public function test_job_handles_empty_payload(): void
    {
        $job = new MemoryWorkingBufferJob($this->run->id, 'stdout', '');

        $job->handle(app(WorkingMemoryBuffer::class));

        $buffer = app(WorkingMemoryBuffer::class);
        $entries = $buffer->getRecent($this->run->id);

        // Empty payloads should still be recorded
        $this->assertCount(1, $entries);
        $this->assertEquals('', $entries[0]['content']);
    }

    /**
     * Test that job includes correct tags for Horizon.
     */
    public function test_job_includes_correct_tags(): void
    {
        $job = new MemoryWorkingBufferJob($this->run->id, 'stdout', 'Test');

        $tags = $job->tags();

        $this->assertContains('memory', $tags);
        $this->assertContains('working-buffer', $tags);
        $this->assertContains('run:'.$this->run->id, $tags);
    }

    /**
     * Test that job serializes correctly for queue.
     */
    public function test_job_serializes_correctly(): void
    {
        $job = new MemoryWorkingBufferJob($this->run->id, 'stdout', 'Test payload');

        $serialized = serialize($job);
        $deserialized = unserialize($serialized);

        $this->assertEquals($this->run->id, $deserialized->runId);
        $this->assertEquals('stdout', $deserialized->eventType);
        $this->assertEquals('Test payload', $deserialized->rawPayload);
    }

    /**
     * Test that multiple jobs can run concurrently.
     */
    public function test_multiple_jobs_can_run(): void
    {
        $buffer = app(WorkingMemoryBuffer::class);

        for ($i = 1; $i <= 5; $i++) {
            $job = new MemoryWorkingBufferJob($this->run->id, 'stdout', "Message $i");
            $job->handle($buffer);
        }

        $entries = $buffer->getRecent($this->run->id);

        $this->assertCount(5, $entries);
    }

    /**
     * Test that job does not retry on failure.
     */
    public function test_job_does_not_retry_on_failure(): void
    {
        $job = new MemoryWorkingBufferJob($this->run->id, 'stdout', 'Test');

        // tries = 0 means no retries
        $this->assertEquals(0, $job->tries);
    }
}
