<?php

declare(strict_types=1);

namespace Tests\Feature\Jobs;

use App\Jobs\Messenger\SendAccountLinkPrompt;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SendAccountLinkPromptTest extends TestCase
{
    use RefreshDatabase;

    public function test_implements_should_queue(): void
    {
        $this->assertTrue(
            is_subclass_of(SendAccountLinkPrompt::class, ShouldQueue::class)
            || in_array(ShouldQueue::class, class_implements(SendAccountLinkPrompt::class) ?: [])
        );
    }

    public function test_can_be_dispatched(): void
    {
        Queue::fake();

        SendAccountLinkPrompt::dispatch('connector-account-1', 'provider-user-1', 'channel-1');

        Queue::assertPushed(SendAccountLinkPrompt::class);
    }

    public function test_can_be_dispatched_with_all_params(): void
    {
        Queue::fake();

        SendAccountLinkPrompt::dispatch('connector-account-1', 'provider-user-1', 'channel-1', 'thread-1', true);

        Queue::assertPushed(SendAccountLinkPrompt::class);
    }
}
