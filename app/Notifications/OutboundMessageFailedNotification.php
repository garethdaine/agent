<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OutboundMessageFailedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $sessionId,
        public string $reason
    ) {}

    /**
     * Determine notification channels based on user preference.
     *
     * Accepts either a User object (when called by Laravel's notification system)
     * or a string channel preference (for direct testing/configuration).
     *
     * @param  User|string  $notifiable
     * @return array<string>
     */
    public function via(mixed $notifiable): array
    {
        $channel = $notifiable instanceof User
            ? $notifiable->getNotificationChannel()
            : $notifiable;

        return match ($channel) {
            'email' => ['mail'],
            'in_app' => ['database'],
            'both' => ['mail', 'database'],
            default => ['mail'],
        };
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Outbound Message Delivery Failed')
            ->line('A message failed to deliver after 3 attempts.')
            ->line("Reason: {$this->reason}")
            ->line("Session ID: {$this->sessionId}")
            ->action('View Dead Letter Queue', url('/messenger/dead-letters'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'outbound_message_failed',
            'session_id' => $this->sessionId,
            'reason' => $this->reason,
        ];
    }
}
