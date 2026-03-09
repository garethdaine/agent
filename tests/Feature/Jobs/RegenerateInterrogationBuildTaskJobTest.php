<?php

declare(strict_types=1);

namespace Tests\Feature\Jobs;

use App\Jobs\RegenerateInterrogationBuildTaskJob;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class RegenerateInterrogationBuildTaskJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_implements_should_queue(): void
    {
        $this->assertTrue(
            is_subclass_of(RegenerateInterrogationBuildTaskJob::class, ShouldQueue::class)
            || in_array(ShouldQueue::class, class_implements(RegenerateInterrogationBuildTaskJob::class) ?: [])
        );
    }

    public function test_can_be_dispatched(): void
    {
        Queue::fake();

        RegenerateInterrogationBuildTaskJob::dispatch(1, 10, 'Please improve error handling');

        Queue::assertPushed(RegenerateInterrogationBuildTaskJob::class);
    }

    public function test_can_be_dispatched_with_additional_context(): void
    {
        Queue::fake();

        RegenerateInterrogationBuildTaskJob::dispatch(1, 10, 'Please improve error handling', 'Additional context here');

        Queue::assertPushed(RegenerateInterrogationBuildTaskJob::class);
    }
}
