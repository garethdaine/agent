<?php

namespace Tests\Unit\Notifications;

use App\Models\User;
use App\Notifications\OutboundMessageFailedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Messages\MailMessage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OutboundMessageFailedNotificationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_sends_via_email_when_preference_is_email(): void
    {
        $notification = new OutboundMessageFailedNotification(
            sessionId: 'test-session-123',
            reason: 'Connection timeout'
        );

        $channels = $notification->via('email');

        $this->assertContains('mail', $channels);
    }

    #[Test]
    public function it_sends_via_database_when_preference_is_in_app(): void
    {
        $notification = new OutboundMessageFailedNotification(
            sessionId: 'test-session-123',
            reason: 'Connection timeout'
        );

        $channels = $notification->via('in_app');

        $this->assertContains('database', $channels);
    }

    #[Test]
    public function it_sends_via_both_when_preference_is_both(): void
    {
        $notification = new OutboundMessageFailedNotification(
            sessionId: 'test-session-123',
            reason: 'Connection timeout'
        );

        $channels = $notification->via('both');

        $this->assertContains('mail', $channels);
        $this->assertContains('database', $channels);
    }

    #[Test]
    public function it_includes_reason_in_mail(): void
    {
        $notification = new OutboundMessageFailedNotification(
            sessionId: 'test-session-123',
            reason: 'Connection timeout'
        );

        $mail = $notification->toMail(new User);

        $this->assertInstanceOf(MailMessage::class, $mail);
    }

    #[Test]
    public function it_includes_session_and_reason_in_array(): void
    {
        $notification = new OutboundMessageFailedNotification(
            sessionId: 'test-session-123',
            reason: 'Connection timeout'
        );

        $data = $notification->toArray(new User);

        $this->assertArrayHasKey('type', $data);
        $this->assertArrayHasKey('session_id', $data);
        $this->assertArrayHasKey('reason', $data);
        $this->assertEquals('outbound_message_failed', $data['type']);
        $this->assertEquals('test-session-123', $data['session_id']);
        $this->assertEquals('Connection timeout', $data['reason']);
    }

    #[Test]
    public function it_defaults_to_mail_for_unknown_channel(): void
    {
        $notification = new OutboundMessageFailedNotification(
            sessionId: 'test-session-123',
            reason: 'Connection timeout'
        );

        $channels = $notification->via('unknown');

        $this->assertContains('mail', $channels);
        $this->assertNotContains('database', $channels);
    }
}
