<?php

declare(strict_types=1);

namespace Tests\Feature\Jobs;

use App\Jobs\Documentation\PersistDocsTelemetryEventJob;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PersistDocsTelemetryEventJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_implements_should_queue(): void
    {
        $this->assertTrue(
            is_subclass_of(PersistDocsTelemetryEventJob::class, ShouldQueue::class)
            || in_array(ShouldQueue::class, class_implements(PersistDocsTelemetryEventJob::class) ?: [])
        );
    }

    public function test_can_be_dispatched(): void
    {
        Queue::fake();

        PersistDocsTelemetryEventJob::dispatch('page_viewed', ['page' => '/docs/overview', 'duration' => 30]);

        Queue::assertPushed(PersistDocsTelemetryEventJob::class);
    }
}
