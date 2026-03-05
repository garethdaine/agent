<?php

declare(strict_types=1);

namespace Tests\Unit\Messenger;

use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Services\Messenger\ContextUsageEstimator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ContextUsageEstimatorTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function estimate_returns_message_count_and_estimated_tokens(): void
    {
        $session = ChatSession::factory()->create();
        ChatMessage::factory()->forSession($session)->inbound()->create(['content' => 'Hello']);
        ChatMessage::factory()->forSession($session)->outbound()->create(['content' => 'Hi there']);

        config(['messenger.context.chars_per_token' => 4]);
        $estimator = new ContextUsageEstimator;
        $result = $estimator->estimate($session);

        $this->assertSame(2, $result['message_count']);
        $this->assertSame(13, $result['total_chars']);
        $this->assertGreaterThanOrEqual(1, $result['estimated_tokens']);
    }

    #[Test]
    public function estimate_respects_limit(): void
    {
        $session = ChatSession::factory()->create();
        ChatMessage::factory()->forSession($session)->count(5)->create(['content' => 'x']);

        $estimator = new ContextUsageEstimator;
        $result = $estimator->estimate($session, 2);

        $this->assertSame(2, $result['message_count']);
    }
}
