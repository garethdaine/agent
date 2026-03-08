<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Notification;
use Tests\TestCase;

class NotificationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_notifications_can_be_listed_marked_as_read_and_cleared(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->sendDatabaseNotification($user, [
            'type' => 'outbound_message_failed',
            'session_id' => 'session-123',
            'reason' => 'Provider timeout',
        ]);

        $response = $this->getJson('/agent/api/v1/notifications')
            ->assertOk()
            ->assertJsonPath('data.unread_count', 1)
            ->assertJsonPath('data.notifications.0.title', 'Outbound message delivery failed');

        $notificationId = $response->json('data.notifications.0.id');
        $this->assertIsString($notificationId);

        $this->postJson("/agent/api/v1/notifications/{$notificationId}/read")
            ->assertOk()
            ->assertJsonPath('data.unread_count', 0);

        $this->sendDatabaseNotification($user, [
            'type' => 'delegation_escalation',
            'task_id' => 88,
            'task_name' => 'Investigate failed run',
            'graph_id' => 12,
            'reason' => 'Recovery chain exhausted',
        ]);

        $this->postJson('/agent/api/v1/notifications/read-all')
            ->assertOk()
            ->assertJsonPath('data.unread_count', 0);

        $this->deleteJson('/agent/api/v1/notifications')
            ->assertOk()
            ->assertJsonPath('data.unread_count', 0)
            ->assertJsonCount(0, 'data.notifications');

        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_users_cannot_mark_other_users_notifications_as_read(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();

        $this->sendDatabaseNotification($owner, [
            'type' => 'outbound_message_failed',
            'session_id' => 'session-abc',
            'reason' => 'Forbidden',
        ]);

        $notificationId = $owner->notifications()->latest()->value('id');
        $this->assertNotNull($notificationId);

        $this->actingAs($otherUser)
            ->postJson("/agent/api/v1/notifications/{$notificationId}/read")
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'NOT_FOUND');
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function sendDatabaseNotification(User $user, array $payload): void
    {
        $user->notifyNow(new class($payload) extends Notification
        {
            /**
             * @param  array<string, mixed>  $payload
             */
            public function __construct(private readonly array $payload) {}

            public function via(object $notifiable): array
            {
                return ['database'];
            }

            /**
             * @return array<string, mixed>
             */
            public function toArray(object $notifiable): array
            {
                return $this->payload;
            }
        });
    }
}
