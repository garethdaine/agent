<?php

declare(strict_types=1);

namespace Tests\Feature\Jobs;

use App\Jobs\Memory\MemoryPruneJob;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class MemoryPruneJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_implements_should_queue(): void
    {
        $this->assertTrue(
            is_subclass_of(MemoryPruneJob::class, ShouldQueue::class)
            || in_array(ShouldQueue::class, class_implements(MemoryPruneJob::class) ?: [])
        );
    }

    public function test_can_be_dispatched(): void
    {
        Queue::fake();

        MemoryPruneJob::dispatch();

        Queue::assertPushed(MemoryPruneJob::class);
    }

    public function test_can_be_dispatched_with_user_id(): void
    {
        Queue::fake();

        MemoryPruneJob::dispatch(42, true);

        Queue::assertPushed(MemoryPruneJob::class);
    }
}
