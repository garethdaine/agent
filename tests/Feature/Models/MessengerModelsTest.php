<?php

namespace Tests\Feature\Models;

use App\Models\AccountLinkToken;
use App\Models\ChatAction;
use App\Models\ChatAttachment;
use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Models\ConnectorAccount;
use App\Models\MessengerIdentityLink;
use App\Models\PendingConfirmation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class MessengerModelsTest extends TestCase
{
    use RefreshDatabase;

    public function test_connector_account_can_be_created_with_encrypted_credentials(): void
    {
        $connector = ConnectorAccount::create([
            'id' => Str::uuid()->toString(),
            'provider' => 'slack',
            'name' => 'Test Workspace',
            'credentials' => ['bot_token' => 'xoxb-secret-token'],
            'webhook_secret' => 'webhook_secret_123',
            'connection_mode' => 'local',
            'status' => 'connected',
            'config' => ['confirmation_required' => true],
            'account_key' => 'workspace-123',
        ]);

        $this->assertNotNull($connector->id);
        $this->assertSame('slack', $connector->provider);

        $fresh = ConnectorAccount::find($connector->id);
        $this->assertIsArray($fresh->credentials);
        $this->assertSame('xoxb-secret-token', $fresh->credentials['bot_token']);
    }

    public function test_connector_account_credentials_are_encrypted_in_database(): void
    {
        $connector = ConnectorAccount::create([
            'id' => Str::uuid()->toString(),
            'provider' => 'telegram',
            'name' => 'Test Bot',
            'credentials' => ['bot_token' => 'sensitive-api-key'],
            'connection_mode' => 'local',
            'status' => 'disconnected',
            'account_key' => 'bot-456',
        ]);

        $rawValue = \DB::table('connector_accounts')
            ->where('id', $connector->id)
            ->value('credentials');

        $this->assertNotSame('sensitive-api-key', $rawValue);
        $this->assertNotSame(json_encode(['bot_token' => 'sensitive-api-key']), $rawValue);
    }

    public function test_chat_session_belongs_to_user_and_connector(): void
    {
        $user = User::factory()->create();
        $connector = ConnectorAccount::create([
            'id' => Str::uuid()->toString(),
            'provider' => 'slack',
            'name' => 'Test Workspace',
            'credentials' => [],
            'connection_mode' => 'local',
            'status' => 'connected',
            'account_key' => 'ws-test',
        ]);

        $session = ChatSession::create([
            'id' => Str::uuid()->toString(),
            'user_id' => $user->id,
            'connector_account_id' => $connector->id,
            'provider' => 'slack',
            'channel_id' => 'C12345',
            'thread_id' => '1234567890.123456',
            'status' => 'active',
        ]);

        $this->assertTrue($session->user->is($user));
        $this->assertTrue($session->connectorAccount->is($connector));
        $this->assertSame('active', $session->status);
    }

    public function test_chat_message_idempotency_key_uniqueness_constraint(): void
    {
        $connector = ConnectorAccount::create([
            'id' => Str::uuid()->toString(),
            'provider' => 'slack',
            'name' => 'Test',
            'credentials' => [],
            'connection_mode' => 'local',
            'status' => 'connected',
            'account_key' => 'ws-idem',
        ]);

        $user = User::factory()->create();
        $session = ChatSession::create([
            'id' => Str::uuid()->toString(),
            'user_id' => $user->id,
            'connector_account_id' => $connector->id,
            'provider' => 'slack',
            'channel_id' => 'C12345',
            'status' => 'active',
        ]);

        $idempotencyKey = 'unique-key-123';

        ChatMessage::create([
            'id' => Str::uuid()->toString(),
            'chat_session_id' => $session->id,
            'connector_account_id' => $connector->id,
            'direction' => 'inbound',
            'content' => 'First message',
            'idempotency_key' => $idempotencyKey,
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        ChatMessage::create([
            'id' => Str::uuid()->toString(),
            'chat_session_id' => $session->id,
            'connector_account_id' => $connector->id,
            'direction' => 'inbound',
            'content' => 'Duplicate message',
            'idempotency_key' => $idempotencyKey,
        ]);
    }

    public function test_chat_message_has_many_actions(): void
    {
        $connector = ConnectorAccount::create([
            'id' => Str::uuid()->toString(),
            'provider' => 'telegram',
            'name' => 'Test Bot',
            'credentials' => [],
            'connection_mode' => 'local',
            'status' => 'connected',
            'account_key' => 'bot-rel',
        ]);

        $user = User::factory()->create();
        $session = ChatSession::create([
            'id' => Str::uuid()->toString(),
            'user_id' => $user->id,
            'connector_account_id' => $connector->id,
            'provider' => 'telegram',
            'channel_id' => '123456789',
            'status' => 'active',
        ]);

        $message = ChatMessage::create([
            'id' => Str::uuid()->toString(),
            'chat_session_id' => $session->id,
            'connector_account_id' => $connector->id,
            'direction' => 'inbound',
            'content' => 'Create a new job',
            'idempotency_key' => 'msg-action-test',
        ]);

        $action = ChatAction::create([
            'id' => Str::uuid()->toString(),
            'chat_message_id' => $message->id,
            'action_type' => 'jobs.create',
            'parameters' => ['name' => 'Test Job'],
            'status' => 'pending',
            'requires_confirmation' => false,
        ]);

        $this->assertCount(1, $message->actions);
        $this->assertTrue($message->actions->first()->is($action));
        $this->assertTrue($action->message->is($message));
    }

    public function test_chat_action_status_transitions(): void
    {
        $connector = ConnectorAccount::create([
            'id' => Str::uuid()->toString(),
            'provider' => 'slack',
            'name' => 'Test',
            'credentials' => [],
            'connection_mode' => 'local',
            'status' => 'connected',
            'account_key' => 'ws-status',
        ]);

        $user = User::factory()->create();
        $session = ChatSession::create([
            'id' => Str::uuid()->toString(),
            'user_id' => $user->id,
            'connector_account_id' => $connector->id,
            'provider' => 'slack',
            'channel_id' => 'C99999',
            'status' => 'active',
        ]);

        $message = ChatMessage::create([
            'id' => Str::uuid()->toString(),
            'chat_session_id' => $session->id,
            'connector_account_id' => $connector->id,
            'direction' => 'inbound',
            'content' => 'Stop the run',
            'idempotency_key' => 'msg-status-test',
        ]);

        $action = ChatAction::create([
            'id' => Str::uuid()->toString(),
            'chat_message_id' => $message->id,
            'action_type' => 'runs.stop',
            'parameters' => ['run_id' => 'uuid-here'],
            'status' => 'pending',
            'requires_confirmation' => true,
        ]);

        $this->assertTrue($action->isPending());
        $this->assertFalse($action->isTerminal());

        $action->update(['status' => 'executing']);
        $this->assertFalse($action->isPending());
        $this->assertFalse($action->isTerminal());

        $action->update(['status' => 'completed']);
        $this->assertTrue($action->isTerminal());

        $action->update(['status' => 'failed']);
        $this->assertTrue($action->isTerminal());

        $action->update(['status' => 'cancelled']);
        $this->assertTrue($action->isTerminal());

        $action->update(['status' => 'timeout']);
        $this->assertTrue($action->isTerminal());
    }

    public function test_messenger_identity_link_uniqueness_constraint(): void
    {
        $user = User::factory()->create();
        $connector = ConnectorAccount::create([
            'id' => Str::uuid()->toString(),
            'provider' => 'slack',
            'name' => 'Test Workspace',
            'credentials' => [],
            'connection_mode' => 'local',
            'status' => 'connected',
            'account_key' => 'ws-link',
        ]);

        MessengerIdentityLink::create([
            'id' => Str::uuid()->toString(),
            'user_id' => $user->id,
            'connector_account_id' => $connector->id,
            'provider_user_id' => 'U12345678',
            'provider_username' => 'john.doe',
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        MessengerIdentityLink::create([
            'id' => Str::uuid()->toString(),
            'user_id' => $user->id,
            'connector_account_id' => $connector->id,
            'provider_user_id' => 'U12345678',
            'provider_username' => 'different.user',
        ]);
    }

    public function test_account_link_token_expiration_logic(): void
    {
        $connector = ConnectorAccount::create([
            'id' => Str::uuid()->toString(),
            'provider' => 'telegram',
            'name' => 'Test Bot',
            'credentials' => [],
            'connection_mode' => 'local',
            'status' => 'connected',
            'account_key' => 'bot-token-test',
        ]);

        $validToken = AccountLinkToken::create([
            'token_hash' => hash('sha256', 'valid-token'),
            'connector_account_id' => $connector->id,
            'provider_user_id' => '123456789',
            'issued_at' => now(),
            'expires_at' => now()->addMinutes(15),
        ]);

        $expiredToken = AccountLinkToken::create([
            'token_hash' => hash('sha256', 'expired-token'),
            'connector_account_id' => $connector->id,
            'provider_user_id' => '987654321',
            'issued_at' => now()->subMinutes(20),
            'expires_at' => now()->subMinutes(5),
        ]);

        $consumedToken = AccountLinkToken::create([
            'token_hash' => hash('sha256', 'consumed-token'),
            'connector_account_id' => $connector->id,
            'provider_user_id' => '555555555',
            'issued_at' => now(),
            'expires_at' => now()->addMinutes(15),
            'consumed_at' => now()->subMinute(),
        ]);

        $this->assertTrue($validToken->isValid());
        $this->assertFalse($expiredToken->isValid());
        $this->assertFalse($consumedToken->isValid());

        $this->assertFalse($validToken->isConsumed());
        $this->assertTrue($consumedToken->isConsumed());

        $this->assertFalse($validToken->isExpired());
        $this->assertTrue($expiredToken->isExpired());
    }

    public function test_account_link_token_atomic_consumption(): void
    {
        $connector = ConnectorAccount::create([
            'id' => Str::uuid()->toString(),
            'provider' => 'slack',
            'name' => 'Test',
            'credentials' => [],
            'connection_mode' => 'local',
            'status' => 'connected',
            'account_key' => 'ws-atomic',
        ]);

        $token = AccountLinkToken::create([
            'token_hash' => hash('sha256', 'atomic-test-token'),
            'connector_account_id' => $connector->id,
            'provider_user_id' => 'U99999999',
            'issued_at' => now(),
            'expires_at' => now()->addMinutes(15),
        ]);

        $firstConsumption = $token->consume();
        $this->assertTrue($firstConsumption);

        $token->refresh();
        $this->assertNotNull($token->consumed_at);

        $secondConsumption = $token->consume();
        $this->assertFalse($secondConsumption);
    }

    public function test_chat_attachment_relationships(): void
    {
        $connector = ConnectorAccount::create([
            'id' => Str::uuid()->toString(),
            'provider' => 'slack',
            'name' => 'Test',
            'credentials' => [],
            'connection_mode' => 'local',
            'status' => 'connected',
            'account_key' => 'ws-attach',
        ]);

        $user = User::factory()->create();
        $session = ChatSession::create([
            'id' => Str::uuid()->toString(),
            'user_id' => $user->id,
            'connector_account_id' => $connector->id,
            'provider' => 'slack',
            'channel_id' => 'C77777',
            'status' => 'active',
        ]);

        $message = ChatMessage::create([
            'id' => Str::uuid()->toString(),
            'chat_session_id' => $session->id,
            'connector_account_id' => $connector->id,
            'direction' => 'inbound',
            'content' => 'Here is a file',
            'idempotency_key' => 'msg-attach-test',
        ]);

        $attachment = ChatAttachment::create([
            'id' => Str::uuid()->toString(),
            'chat_message_id' => $message->id,
            'filename' => 'screenshot.png',
            'mime_type' => 'image/png',
            'size_bytes' => 102400,
            'storage_path' => 'attachments/2026/02/screenshot.png',
            'scan_status' => 'clean',
            'expires_at' => now()->addDays(30),
        ]);

        $this->assertTrue($attachment->message->is($message));
        $this->assertCount(1, $message->attachments);
        $this->assertTrue($message->attachments->first()->is($attachment));
    }

    public function test_pending_confirmation_relationships(): void
    {
        $connector = ConnectorAccount::create([
            'id' => Str::uuid()->toString(),
            'provider' => 'slack',
            'name' => 'Test',
            'credentials' => [],
            'connection_mode' => 'local',
            'status' => 'connected',
            'account_key' => 'ws-confirm',
        ]);

        $user = User::factory()->create();
        $session = ChatSession::create([
            'id' => Str::uuid()->toString(),
            'user_id' => $user->id,
            'connector_account_id' => $connector->id,
            'provider' => 'slack',
            'channel_id' => 'C88888',
            'status' => 'active',
        ]);

        $message = ChatMessage::create([
            'id' => Str::uuid()->toString(),
            'chat_session_id' => $session->id,
            'connector_account_id' => $connector->id,
            'direction' => 'inbound',
            'content' => 'Delete job X',
            'idempotency_key' => 'msg-confirm-test',
        ]);

        $action = ChatAction::create([
            'id' => Str::uuid()->toString(),
            'chat_message_id' => $message->id,
            'action_type' => 'jobs.delete',
            'parameters' => ['job_id' => 'uuid-123'],
            'status' => 'pending',
            'requires_confirmation' => true,
        ]);

        $confirmation = PendingConfirmation::create([
            'id' => Str::uuid()->toString(),
            'chat_action_id' => $action->id,
            'chat_session_id' => $session->id,
            'connector_account_id' => $connector->id,
            'confirmation_token' => Str::random(64),
            'provider_message_id' => '1234567890.123456',
            'callback_data' => ['action' => 'confirm_delete'],
            'expires_at' => now()->addMinutes(5),
        ]);

        $this->assertTrue($confirmation->action->is($action));
        $this->assertTrue($confirmation->session->is($session));
        $this->assertTrue($confirmation->connectorAccount->is($connector));
    }

    public function test_connector_account_has_many_sessions(): void
    {
        $connector = ConnectorAccount::create([
            'id' => Str::uuid()->toString(),
            'provider' => 'telegram',
            'name' => 'Test Bot',
            'credentials' => [],
            'connection_mode' => 'local',
            'status' => 'connected',
            'account_key' => 'bot-sessions',
        ]);

        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        ChatSession::create([
            'id' => Str::uuid()->toString(),
            'user_id' => $user1->id,
            'connector_account_id' => $connector->id,
            'provider' => 'telegram',
            'channel_id' => '111111',
            'status' => 'active',
        ]);

        ChatSession::create([
            'id' => Str::uuid()->toString(),
            'user_id' => $user2->id,
            'connector_account_id' => $connector->id,
            'provider' => 'telegram',
            'channel_id' => '222222',
            'status' => 'active',
        ]);

        $this->assertCount(2, $connector->sessions);
    }

    public function test_connector_account_unique_provider_account_key_constraint(): void
    {
        ConnectorAccount::create([
            'id' => Str::uuid()->toString(),
            'provider' => 'slack',
            'name' => 'Workspace One',
            'credentials' => [],
            'connection_mode' => 'local',
            'status' => 'connected',
            'account_key' => 'unique-workspace',
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        ConnectorAccount::create([
            'id' => Str::uuid()->toString(),
            'provider' => 'slack',
            'name' => 'Workspace Two',
            'credentials' => [],
            'connection_mode' => 'local',
            'status' => 'connected',
            'account_key' => 'unique-workspace',
        ]);
    }
}
