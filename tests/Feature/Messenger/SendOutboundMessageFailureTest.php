<?php

namespace Tests\Feature\Messenger;

use App\Jobs\Messenger\SendOutboundMessage;
use App\Messenger\Reliability\DeadLetterManager;
use App\Models\ChatSession;
use App\Models\ConnectorAccount;
use App\Models\User;
use App\Models\UserNotificationSetting;
use App\Notifications\OutboundMessageFailedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SendOutboundMessageFailureTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_stores_to_dead_letter_on_terminal_failure(): void
    {
        $deadLetter = $this->mock(DeadLetterManager::class);
        $deadLetter->shouldReceive('moveToDeadLetter')->once();

        $user = User::factory()->create();
        $account = ConnectorAccount::factory()->create([
            'status' => ConnectorAccount::STATUS_CONNECTED,
        ]);
        $session = ChatSession::factory()->create([
            'user_id' => $user->id,
            'connector_account_id' => $account->id,
        ]);

        $sendJob = new SendOutboundMessage(
            sessionId: $session->id,
            content: 'Test message',
        );

        $sendJob->failed(new \Exception('Terminal failure'));

        // DeadLetterManager::moveToDeadLetter should have been called
    }

    #[Test]
    public function it_notifies_owner_on_terminal_failure(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        UserNotificationSetting::create(['user_id' => $user->id, 'channel' => 'email']);
        $account = ConnectorAccount::factory()->create([
            'status' => ConnectorAccount::STATUS_CONNECTED,
        ]);
        $session = ChatSession::factory()->create([
            'user_id' => $user->id,
            'connector_account_id' => $account->id,
        ]);

        $this->mock(DeadLetterManager::class)->shouldReceive('moveToDeadLetter');

        $sendJob = new SendOutboundMessage(
            sessionId: $session->id,
            content: 'Test message',
        );

        $sendJob->failed(new \Exception('Terminal failure'));

        Notification::assertSentTo($user, OutboundMessageFailedNotification::class);
    }

    #[Test]
    public function it_does_not_notify_if_session_not_found(): void
    {
        Notification::fake();

        $this->mock(DeadLetterManager::class)->shouldReceive('moveToDeadLetter')->never();

        $sendJob = new SendOutboundMessage(
            sessionId: 'non-existent-session',
            content: 'Test message',
        );

        $sendJob->failed(new \Exception('Terminal failure'));

        Notification::assertNothingSent();
    }

    #[Test]
    public function it_has_correct_retry_configuration(): void
    {
        $sendJob = new SendOutboundMessage(
            sessionId: 'test-session',
            content: 'Test message',
        );

        $this->assertEquals(3, $sendJob->tries);
        $this->assertEquals([30, 120, 480], $sendJob->backoff);
    }

    #[Test]
    public function it_respects_user_notification_channel_preference(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        UserNotificationSetting::create(['user_id' => $user->id, 'channel' => 'in_app']);
        $account = ConnectorAccount::factory()->create([
            'status' => ConnectorAccount::STATUS_CONNECTED,
        ]);
        $session = ChatSession::factory()->create([
            'user_id' => $user->id,
            'connector_account_id' => $account->id,
        ]);

        $this->mock(DeadLetterManager::class)->shouldReceive('moveToDeadLetter');

        $sendJob = new SendOutboundMessage(
            sessionId: $session->id,
            content: 'Test message',
        );

        $sendJob->failed(new \Exception('Terminal failure'));

        Notification::assertSentTo($user, OutboundMessageFailedNotification::class, function ($notification, $channels) {
            return in_array('database', $channels);
        });
    }
}
