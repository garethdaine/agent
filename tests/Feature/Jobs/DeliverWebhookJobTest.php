<?php

declare(strict_types=1);

namespace Tests\Feature\Jobs;

use App\Jobs\Agent\DeliverWebhookJob;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class DeliverWebhookJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_implements_should_queue(): void
    {
        $this->assertTrue(
            is_subclass_of(DeliverWebhookJob::class, ShouldQueue::class)
            || in_array(ShouldQueue::class, class_implements(DeliverWebhookJob::class) ?: [])
        );
    }

    public function test_can_be_dispatched(): void
    {
        Queue::fake();

        DeliverWebhookJob::dispatch('https://example.com/webhook', ['event' => 'test'], 'secret123');

        Queue::assertPushed(DeliverWebhookJob::class);
    }

    public function test_can_be_dispatched_without_secret(): void
    {
        Queue::fake();

        DeliverWebhookJob::dispatch('https://example.com/webhook', ['event' => 'test']);

        Queue::assertPushed(DeliverWebhookJob::class);
    }
}
