<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\ChatAction;
use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Models\ConnectorAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_list_sessions_returns_user_sessions_only(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $connector = ConnectorAccount::factory()->create();

        $userSession = ChatSession::factory()->create([
            'user_id' => $user->id,
            'connector_account_id' => $connector->id,
            'provider' => 'slack',
        ]);

        $otherSession = ChatSession::factory()->create([
            'user_id' => $otherUser->id,
            'connector_account_id' => $connector->id,
            'provider' => 'slack',
        ]);

        $this->actingAs($user);

        $response = $this->getJson('/agent/api/v1/chat/sessions');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $userSession->id)
            ->assertJsonMissing(['id' => $otherSession->id]);
    }

    public function test_show_session_returns_session_with_details(): void
    {
        $user = User::factory()->create();
        $connector = ConnectorAccount::factory()->create();

        $session = ChatSession::factory()->create([
            'user_id' => $user->id,
            'connector_account_id' => $connector->id,
            'provider' => 'telegram',
            'channel_id' => 'test-channel-123',
            'status' => ChatSession::STATUS_ACTIVE,
        ]);

        $this->actingAs($user);

        $response = $this->getJson("/agent/api/v1/chat/sessions/{$session->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $session->id)
            ->assertJsonPath('data.provider', 'telegram')
            ->assertJsonPath('data.channel_id', 'test-channel-123')
            ->assertJsonPath('data.status', 'active');
    }

    public function test_show_session_returns_404_for_other_users_session(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $connector = ConnectorAccount::factory()->create();

        $otherSession = ChatSession::factory()->create([
            'user_id' => $otherUser->id,
            'connector_account_id' => $connector->id,
        ]);

        $this->actingAs($user);

        $this->getJson("/agent/api/v1/chat/sessions/{$otherSession->id}")
            ->assertNotFound();
    }

    public function test_session_messages_returns_messages(): void
    {
        $user = User::factory()->create();
        $connector = ConnectorAccount::factory()->create();

        $session = ChatSession::factory()->create([
            'user_id' => $user->id,
            'connector_account_id' => $connector->id,
        ]);

        $message1 = ChatMessage::factory()->create([
            'chat_session_id' => $session->id,
            'connector_account_id' => $connector->id,
            'direction' => 'inbound',
            'content' => 'Hello world',
        ]);

        $message2 = ChatMessage::factory()->create([
            'chat_session_id' => $session->id,
            'connector_account_id' => $connector->id,
            'direction' => 'outbound',
            'content' => 'Hi there!',
        ]);

        $this->actingAs($user);

        $response = $this->getJson("/agent/api/v1/chat/sessions/{$session->id}/messages");

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.session_id', $session->id);
    }

    public function test_action_confirm_transitions_state(): void
    {
        $user = User::factory()->create();
        $connector = ConnectorAccount::factory()->create();

        $session = ChatSession::factory()->create([
            'user_id' => $user->id,
            'connector_account_id' => $connector->id,
        ]);

        $message = ChatMessage::factory()->create([
            'chat_session_id' => $session->id,
            'connector_account_id' => $connector->id,
        ]);

        $action = ChatAction::factory()->create([
            'chat_message_id' => $message->id,
            'action_type' => ChatAction::ACTION_RUNS_STOP,
            'status' => ChatAction::STATUS_PENDING,
            'requires_confirmation' => true,
            'confirmed_at' => null,
        ]);

        $this->actingAs($user);

        $response = $this->postJson("/agent/api/v1/chat/actions/{$action->id}/confirm");

        $response->assertOk()
            ->assertJsonPath('data.id', $action->id)
            ->assertJsonPath('data.status', 'pending');

        $action->refresh();
        $this->assertNotNull($action->confirmed_at);
    }

    public function test_action_cancel_transitions_state(): void
    {
        $user = User::factory()->create();
        $connector = ConnectorAccount::factory()->create();

        $session = ChatSession::factory()->create([
            'user_id' => $user->id,
            'connector_account_id' => $connector->id,
        ]);

        $message = ChatMessage::factory()->create([
            'chat_session_id' => $session->id,
            'connector_account_id' => $connector->id,
        ]);

        $action = ChatAction::factory()->create([
            'chat_message_id' => $message->id,
            'action_type' => ChatAction::ACTION_JOBS_DELETE,
            'status' => ChatAction::STATUS_PENDING,
            'requires_confirmation' => true,
        ]);

        $this->actingAs($user);

        $response = $this->postJson("/agent/api/v1/chat/actions/{$action->id}/cancel");

        $response->assertOk()
            ->assertJsonPath('data.id', $action->id)
            ->assertJsonPath('data.status', 'cancelled');

        $action->refresh();
        $this->assertSame(ChatAction::STATUS_CANCELLED, $action->status);
    }

    public function test_unauthorized_access_blocked(): void
    {
        $this->getJson('/agent/api/v1/chat/sessions')
            ->assertUnauthorized();

        $this->getJson('/agent/api/v1/chat/actions/some-uuid')
            ->assertUnauthorized();
    }

    public function test_action_confirm_fails_for_non_pending_action(): void
    {
        $user = User::factory()->create();
        $connector = ConnectorAccount::factory()->create();

        $session = ChatSession::factory()->create([
            'user_id' => $user->id,
            'connector_account_id' => $connector->id,
        ]);

        $message = ChatMessage::factory()->create([
            'chat_session_id' => $session->id,
            'connector_account_id' => $connector->id,
        ]);

        $action = ChatAction::factory()->create([
            'chat_message_id' => $message->id,
            'status' => ChatAction::STATUS_COMPLETED,
            'requires_confirmation' => true,
        ]);

        $this->actingAs($user);

        $this->postJson("/agent/api/v1/chat/actions/{$action->id}/confirm")
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'ACTION_STATE_CONFLICT');
    }

    public function test_action_cancel_fails_for_terminal_action(): void
    {
        $user = User::factory()->create();
        $connector = ConnectorAccount::factory()->create();

        $session = ChatSession::factory()->create([
            'user_id' => $user->id,
            'connector_account_id' => $connector->id,
        ]);

        $message = ChatMessage::factory()->create([
            'chat_session_id' => $session->id,
            'connector_account_id' => $connector->id,
        ]);

        $action = ChatAction::factory()->create([
            'chat_message_id' => $message->id,
            'status' => ChatAction::STATUS_FAILED,
        ]);

        $this->actingAs($user);

        $this->postJson("/agent/api/v1/chat/actions/{$action->id}/cancel")
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'ACTION_ALREADY_TERMINAL');
    }

    public function test_list_sessions_filters_by_status(): void
    {
        $user = User::factory()->create();
        $connector = ConnectorAccount::factory()->create();

        $activeSession = ChatSession::factory()->create([
            'user_id' => $user->id,
            'connector_account_id' => $connector->id,
            'status' => ChatSession::STATUS_ACTIVE,
        ]);

        $archivedSession = ChatSession::factory()->create([
            'user_id' => $user->id,
            'connector_account_id' => $connector->id,
            'status' => ChatSession::STATUS_ARCHIVED,
        ]);

        $this->actingAs($user);

        $this->getJson('/agent/api/v1/chat/sessions?status=active')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $activeSession->id);

        $this->getJson('/agent/api/v1/chat/sessions?status=archived')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $archivedSession->id);
    }

    public function test_list_sessions_filters_by_provider(): void
    {
        $user = User::factory()->create();
        $slackConnector = ConnectorAccount::factory()->create(['provider' => 'slack']);
        $telegramConnector = ConnectorAccount::factory()->create(['provider' => 'telegram']);

        $slackSession = ChatSession::factory()->create([
            'user_id' => $user->id,
            'connector_account_id' => $slackConnector->id,
            'provider' => 'slack',
        ]);

        $telegramSession = ChatSession::factory()->create([
            'user_id' => $user->id,
            'connector_account_id' => $telegramConnector->id,
            'provider' => 'telegram',
        ]);

        $this->actingAs($user);

        $this->getJson('/agent/api/v1/chat/sessions?provider=slack')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.provider', 'slack');
    }

    public function test_session_actions_returns_actions_for_session(): void
    {
        $user = User::factory()->create();
        $connector = ConnectorAccount::factory()->create();

        $session = ChatSession::factory()->create([
            'user_id' => $user->id,
            'connector_account_id' => $connector->id,
        ]);

        $message = ChatMessage::factory()->create([
            'chat_session_id' => $session->id,
            'connector_account_id' => $connector->id,
        ]);

        $action1 = ChatAction::factory()->create([
            'chat_message_id' => $message->id,
            'action_type' => ChatAction::ACTION_JOBS_LIST,
            'status' => ChatAction::STATUS_COMPLETED,
        ]);

        $action2 = ChatAction::factory()->create([
            'chat_message_id' => $message->id,
            'action_type' => ChatAction::ACTION_RUNS_STOP,
            'status' => ChatAction::STATUS_PENDING,
        ]);

        $this->actingAs($user);

        $response = $this->getJson("/agent/api/v1/chat/sessions/{$session->id}/actions");

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.session_id', $session->id);
    }

    public function test_action_status_endpoint_returns_minimal_status(): void
    {
        $user = User::factory()->create();
        $connector = ConnectorAccount::factory()->create();

        $session = ChatSession::factory()->create([
            'user_id' => $user->id,
            'connector_account_id' => $connector->id,
        ]);

        $message = ChatMessage::factory()->create([
            'chat_session_id' => $session->id,
            'connector_account_id' => $connector->id,
        ]);

        $action = ChatAction::factory()->create([
            'chat_message_id' => $message->id,
            'status' => ChatAction::STATUS_EXECUTING,
        ]);

        $this->actingAs($user);

        $response = $this->getJson("/agent/api/v1/chat/actions/{$action->id}/status");

        $response->assertOk()
            ->assertJsonPath('data.id', $action->id)
            ->assertJsonPath('data.status', 'executing')
            ->assertJsonPath('data.is_executing', true)
            ->assertJsonPath('data.is_pending', false)
            ->assertJsonPath('data.is_terminal', false);
    }
}
