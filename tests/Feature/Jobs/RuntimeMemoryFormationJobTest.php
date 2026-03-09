<?php

declare(strict_types=1);

namespace Tests\Feature\Jobs;

use App\Jobs\Memory\RuntimeMemoryFormationJob;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class RuntimeMemoryFormationJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_implements_should_queue(): void
    {
        $this->assertTrue(
            is_subclass_of(RuntimeMemoryFormationJob::class, ShouldQueue::class)
            || in_array(ShouldQueue::class, class_implements(RuntimeMemoryFormationJob::class) ?: [])
        );
    }

    public function test_can_be_dispatched(): void
    {
        Queue::fake();

        RuntimeMemoryFormationJob::dispatch('runtime-session-abc-123');

        Queue::assertPushed(RuntimeMemoryFormationJob::class);
    }
}
