<?php

declare(strict_types=1);

namespace Tests\Feature\Jobs;

use App\Jobs\SyncInterrogationTasksToTaskProviderJob;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SyncInterrogationTasksToTaskProviderJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_implements_should_queue(): void
    {
        $this->assertTrue(
            is_subclass_of(SyncInterrogationTasksToTaskProviderJob::class, ShouldQueue::class)
            || in_array(ShouldQueue::class, class_implements(SyncInterrogationTasksToTaskProviderJob::class) ?: [])
        );
    }

    public function test_can_be_dispatched(): void
    {
        Queue::fake();

        SyncInterrogationTasksToTaskProviderJob::dispatch(1);

        Queue::assertPushed(SyncInterrogationTasksToTaskProviderJob::class);
    }
}
