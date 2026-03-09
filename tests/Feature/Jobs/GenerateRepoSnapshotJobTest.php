<?php

declare(strict_types=1);

namespace Tests\Feature\Jobs;

use App\Jobs\RepoAnalysis\GenerateRepoSnapshotJob;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class GenerateRepoSnapshotJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_implements_should_queue(): void
    {
        $this->assertTrue(
            is_subclass_of(GenerateRepoSnapshotJob::class, ShouldQueue::class)
            || in_array(ShouldQueue::class, class_implements(GenerateRepoSnapshotJob::class) ?: [])
        );
    }

    public function test_can_be_dispatched(): void
    {
        Queue::fake();

        GenerateRepoSnapshotJob::dispatch(1);

        Queue::assertPushed(GenerateRepoSnapshotJob::class);
    }

    public function test_can_be_dispatched_without_dispatch_next(): void
    {
        Queue::fake();

        GenerateRepoSnapshotJob::dispatch(1, false);

        Queue::assertPushed(GenerateRepoSnapshotJob::class);
    }
}
