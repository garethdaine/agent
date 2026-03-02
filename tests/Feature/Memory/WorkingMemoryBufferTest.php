<?php

declare(strict_types=1);

namespace Tests\Feature\Memory;

use App\Models\AgentJob;
use App\Models\AgentJobRun;
use App\Models\User;
use App\Support\Memory\WorkingMemoryBuffer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

/**
 * Tests for WorkingMemoryBuffer Redis-backed sorted set operations.
 *
 * Verifies:
 * - append() adds entry to Redis sorted set
 * - getRecent() returns last N turns in order
 * - Aggregates output until tool call boundary
 * - Truncates to 15 entries (No-API mode)
 * - setTTL() sets 2-hour expiry
 * - clear() removes all entries for run
 */
class WorkingMemoryBufferTest extends TestCase
{
    use RefreshDatabase;

    private WorkingMemoryBuffer $buffer;

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

        $this->buffer = app(WorkingMemoryBuffer::class);

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
     * Test that append() adds entry to Redis sorted set.
     */
    public function test_append_adds_entry_to_redis_sorted_set(): void
    {
        $this->buffer->append($this->run->id, 'assistant', 'Hello, how can I help?');

        $entries = $this->buffer->getRecent($this->run->id, 1);

        $this->assertCount(1, $entries);
        $this->assertEquals('assistant', $entries[0]['role']);
        $this->assertEquals('Hello, how can I help?', $entries[0]['content']);
    }

    /**
     * Test that getRecent() returns entries in chronological order (oldest to newest).
     */
    public function test_get_recent_returns_entries_in_chronological_order(): void
    {
        $this->buffer->append($this->run->id, 'user', 'First message');
        usleep(1000); // Ensure different timestamps
        $this->buffer->append($this->run->id, 'assistant', 'Second message');
        usleep(1000);
        $this->buffer->append($this->run->id, 'user', 'Third message');

        $entries = $this->buffer->getRecent($this->run->id, 5);

        $this->assertCount(3, $entries);
        $this->assertEquals('First message', $entries[0]['content']);
        $this->assertEquals('Second message', $entries[1]['content']);
        $this->assertEquals('Third message', $entries[2]['content']);
    }

    /**
     * Test that getRecent() respects limit parameter.
     */
    public function test_get_recent_respects_limit(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->buffer->append($this->run->id, 'user', "Message $i");
            usleep(1000);
        }

        $entries = $this->buffer->getRecent($this->run->id, 3);

        // Should return the last 3 messages
        $this->assertCount(3, $entries);
        $this->assertEquals('Message 3', $entries[0]['content']);
        $this->assertEquals('Message 4', $entries[1]['content']);
        $this->assertEquals('Message 5', $entries[2]['content']);
    }

    /**
     * Test that buffer truncates to 15 entries (No-API mode oldest-first eviction).
     */
    public function test_buffer_truncates_to_max_messages(): void
    {
        $maxMessages = config('memory.working_memory.max_messages', 15);

        // Add more than max messages
        for ($i = 1; $i <= $maxMessages + 5; $i++) {
            $this->buffer->append($this->run->id, 'user', "Message $i");
            usleep(1000);
        }

        $entries = $this->buffer->getRecent($this->run->id);

        $this->assertCount($maxMessages, $entries);
        // First 5 messages should be evicted, starting with message 6
        $this->assertEquals('Message 6', $entries[0]['content']);
        $this->assertEquals('Message '.($maxMessages + 5), $entries[$maxMessages - 1]['content']);
    }

    /**
     * Test that setTTL() sets expiry on the sorted set.
     */
    public function test_set_ttl_sets_expiry(): void
    {
        $this->buffer->append($this->run->id, 'user', 'Test message');

        $this->buffer->setTTL($this->run->id, 100);

        $connection = Redis::connection('memory');
        $prefix = config('memory.working_memory.key_prefix', 'memory:run:');
        $key = $prefix.$this->run->id.':working';

        $ttl = $connection->ttl($key);

        // TTL should be close to 100 seconds (allow some margin)
        $this->assertGreaterThan(90, $ttl);
        $this->assertLessThanOrEqual(100, $ttl);
    }

    /**
     * Test that setTTL() uses default 2-hour expiry.
     */
    public function test_set_ttl_uses_default_two_hour_expiry(): void
    {
        $this->buffer->append($this->run->id, 'user', 'Test message');

        $this->buffer->setTTL($this->run->id);

        $connection = Redis::connection('memory');
        $prefix = config('memory.working_memory.key_prefix', 'memory:run:');
        $key = $prefix.$this->run->id.':working';

        $ttl = $connection->ttl($key);

        // Default TTL is 7200 seconds (2 hours)
        $defaultTtl = config('memory.working_memory.ttl_seconds', 7200);
        $this->assertGreaterThan($defaultTtl - 10, $ttl);
        $this->assertLessThanOrEqual($defaultTtl, $ttl);
    }

    /**
     * Test that clear() removes all entries for run.
     */
    public function test_clear_removes_all_entries_for_run(): void
    {
        $this->buffer->append($this->run->id, 'user', 'Message 1');
        $this->buffer->append($this->run->id, 'assistant', 'Message 2');

        $this->buffer->clear($this->run->id);

        $entries = $this->buffer->getRecent($this->run->id);

        $this->assertCount(0, $entries);
    }

    /**
     * Test that entries include metadata.
     */
    public function test_append_includes_metadata(): void
    {
        $metadata = [
            'tool_name' => 'search',
            'result_count' => 5,
        ];

        $this->buffer->append($this->run->id, 'tool', 'Search results...', $metadata);

        $entries = $this->buffer->getRecent($this->run->id, 1);

        $this->assertCount(1, $entries);
        $this->assertEquals('tool', $entries[0]['role']);
        $this->assertEquals('Search results...', $entries[0]['content']);
        $this->assertEquals($metadata, $entries[0]['metadata']);
    }

    /**
     * Test that entries have timestamp.
     */
    public function test_entries_have_timestamp(): void
    {
        $before = microtime(true);
        $this->buffer->append($this->run->id, 'user', 'Test message');
        $after = microtime(true);

        $entries = $this->buffer->getRecent($this->run->id, 1);

        $this->assertArrayHasKey('timestamp', $entries[0]);
        $this->assertGreaterThanOrEqual($before, $entries[0]['timestamp']);
        $this->assertLessThanOrEqual($after, $entries[0]['timestamp']);
    }

    /**
     * Test that getRecent() returns empty array for non-existent run.
     */
    public function test_get_recent_returns_empty_for_non_existent_run(): void
    {
        $entries = $this->buffer->getRecent(999999);

        $this->assertIsArray($entries);
        $this->assertCount(0, $entries);
    }

    /**
     * Test that operations are isolated by run ID.
     */
    public function test_operations_are_isolated_by_run_id(): void
    {
        $run2 = AgentJobRun::factory()->for($this->job, 'job')->create();

        $this->buffer->append($this->run->id, 'user', 'Message for run 1');
        $this->buffer->append($run2->id, 'user', 'Message for run 2');

        $entries1 = $this->buffer->getRecent($this->run->id);
        $entries2 = $this->buffer->getRecent($run2->id);

        $this->assertCount(1, $entries1);
        $this->assertCount(1, $entries2);
        $this->assertEquals('Message for run 1', $entries1[0]['content']);
        $this->assertEquals('Message for run 2', $entries2[0]['content']);

        // Clear run 2's key
        Redis::connection('memory')->del(config('memory.working_memory.key_prefix').$run2->id.':working');
    }

    /**
     * Test that tool_call role is properly stored.
     */
    public function test_tool_call_role_is_properly_stored(): void
    {
        $this->buffer->append($this->run->id, 'tool_call', 'Calling search API', [
            'tool_name' => 'search',
            'arguments' => ['query' => 'test'],
        ]);

        $entries = $this->buffer->getRecent($this->run->id);

        $this->assertCount(1, $entries);
        $this->assertEquals('tool_call', $entries[0]['role']);
        $this->assertEquals('search', $entries[0]['metadata']['tool_name']);
    }

    /**
     * Test that empty content is still stored.
     */
    public function test_empty_content_is_still_stored(): void
    {
        $this->buffer->append($this->run->id, 'assistant', '');

        $entries = $this->buffer->getRecent($this->run->id);

        $this->assertCount(1, $entries);
        $this->assertEquals('', $entries[0]['content']);
    }

    /**
     * Test that large content is handled.
     */
    public function test_large_content_is_handled(): void
    {
        $largeContent = str_repeat('a', 10000);

        $this->buffer->append($this->run->id, 'assistant', $largeContent);

        $entries = $this->buffer->getRecent($this->run->id);

        $this->assertCount(1, $entries);
        $this->assertEquals($largeContent, $entries[0]['content']);
    }
}
