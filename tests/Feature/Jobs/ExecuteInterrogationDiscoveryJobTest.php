<?php

declare(strict_types=1);

namespace Tests\Feature\Jobs;

use App\Jobs\ExecuteInterrogationDiscoveryJob;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ExecuteInterrogationDiscoveryJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_implements_should_queue(): void
    {
        $this->assertTrue(
            is_subclass_of(ExecuteInterrogationDiscoveryJob::class, ShouldQueue::class)
            || in_array(ShouldQueue::class, class_implements(ExecuteInterrogationDiscoveryJob::class) ?: [])
        );
    }

    public function test_can_be_dispatched(): void
    {
        Queue::fake();

        ExecuteInterrogationDiscoveryJob::dispatch(1);

        Queue::assertPushed(ExecuteInterrogationDiscoveryJob::class);
    }
}
