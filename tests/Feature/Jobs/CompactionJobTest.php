<?php

declare(strict_types=1);

namespace Tests\Feature\Jobs;

use App\Jobs\Messenger\CompactionJob;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class CompactionJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_implements_should_queue(): void
    {
        $this->assertTrue(
            is_subclass_of(CompactionJob::class, ShouldQueue::class)
            || in_array(ShouldQueue::class, class_implements(CompactionJob::class) ?: [])
        );
    }

    public function test_can_be_dispatched(): void
    {
        Queue::fake();

        CompactionJob::dispatch('chat-session-uuid-123');

        Queue::assertPushed(CompactionJob::class);
    }

    public function test_can_be_dispatched_with_instructions(): void
    {
        Queue::fake();

        CompactionJob::dispatch('chat-session-uuid-123', 'Summarize the key points');

        Queue::assertPushed(CompactionJob::class);
    }
}
