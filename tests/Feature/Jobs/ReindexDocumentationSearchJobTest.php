<?php

declare(strict_types=1);

namespace Tests\Feature\Jobs;

use App\Jobs\ReindexDocumentationSearchJob;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ReindexDocumentationSearchJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_implements_should_queue(): void
    {
        $this->assertTrue(
            is_subclass_of(ReindexDocumentationSearchJob::class, ShouldQueue::class)
            || in_array(ShouldQueue::class, class_implements(ReindexDocumentationSearchJob::class) ?: [])
        );
    }

    public function test_can_be_dispatched(): void
    {
        Queue::fake();

        ReindexDocumentationSearchJob::dispatch([1, 2, 3], [10, 20]);

        Queue::assertPushed(ReindexDocumentationSearchJob::class);
    }

    public function test_can_be_dispatched_with_api_artifact_ids(): void
    {
        Queue::fake();

        ReindexDocumentationSearchJob::dispatch([1, 2], [10], [100, 200]);

        Queue::assertPushed(ReindexDocumentationSearchJob::class);
    }
}
