<?php

declare(strict_types=1);

namespace Tests\Unit\Messenger;

use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Models\ConnectorAccount;
use App\Models\User;
use App\Services\Messenger\CompactionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompactionServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_is_compaction_needed_returns_false_when_under_thresholds(): void
    {
        $this->app['config']->set('messenger.compaction.enabled', true);
        $this->app['config']->set('messenger.compaction.trigger_message_count', 30);
        $this->app['config']->set('messenger.compaction.trigger_estimated_tokens', 15_000);
        $this->app['config']->set('messenger.compaction.target_message_count', 10);

        $user = User::factory()->create();
        $account = ConnectorAccount::factory()->create();
        $session = ChatSession::factory()->create([
            'user_id' => $user->id,
            'connector_account_id' => $account->id,
        ]);

        for ($i = 0; $i < 5; $i++) {
            ChatMessage::create([
                'chat_session_id' => $session->id,
                'connector_account_id' => $account->id,
                'direction' => $i % 2 === 0 ? ChatMessage::DIRECTION_INBOUND : ChatMessage::DIRECTION_OUTBOUND,
                'content' => "Message {$i}",
                'idempotency_key' => hash('sha256', uniqid((string) $i)),
                'provider_timestamp' => now(),
            ]);
        }

        $service = app(CompactionService::class);

        $this->assertFalse($service->isCompactionNeeded($session));
    }

    public function test_is_compaction_needed_returns_true_when_over_message_threshold(): void
    {
        $this->app['config']->set('messenger.compaction.enabled', true);
        $this->app['config']->set('messenger.compaction.trigger_message_count', 5);
        $this->app['config']->set('messenger.compaction.trigger_estimated_tokens', 15_000);
        $this->app['config']->set('messenger.compaction.target_message_count', 2);

        $user = User::factory()->create();
        $account = ConnectorAccount::factory()->create();
        $session = ChatSession::factory()->create([
            'user_id' => $user->id,
            'connector_account_id' => $account->id,
        ]);

        for ($i = 0; $i < 10; $i++) {
            ChatMessage::create([
                'chat_session_id' => $session->id,
                'connector_account_id' => $account->id,
                'direction' => $i % 2 === 0 ? ChatMessage::DIRECTION_INBOUND : ChatMessage::DIRECTION_OUTBOUND,
                'content' => "Message {$i} content here",
                'idempotency_key' => hash('sha256', uniqid((string) $i)),
                'provider_timestamp' => now(),
            ]);
        }

        $service = app(CompactionService::class);

        $this->assertTrue($service->isCompactionNeeded($session));
    }

    public function test_is_compaction_needed_returns_false_when_compaction_disabled(): void
    {
        $this->app['config']->set('messenger.compaction.enabled', false);
        $this->app['config']->set('messenger.compaction.trigger_message_count', 2);
        $this->app['config']->set('messenger.compaction.target_message_count', 1);

        $user = User::factory()->create();
        $account = ConnectorAccount::factory()->create();
        $session = ChatSession::factory()->create([
            'user_id' => $user->id,
            'connector_account_id' => $account->id,
        ]);

        for ($i = 0; $i < 5; $i++) {
            ChatMessage::create([
                'chat_session_id' => $session->id,
                'connector_account_id' => $account->id,
                'direction' => ChatMessage::DIRECTION_INBOUND,
                'content' => "Message {$i}",
                'idempotency_key' => hash('sha256', uniqid((string) $i)),
                'provider_timestamp' => now(),
            ]);
        }

        $service = app(CompactionService::class);

        $this->assertFalse($service->isCompactionNeeded($session));
    }
}
