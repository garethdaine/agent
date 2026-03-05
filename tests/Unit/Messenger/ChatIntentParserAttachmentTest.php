<?php

namespace Tests\Unit\Messenger;

use App\Models\ChatAttachment;
use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Models\ConnectorAccount;
use App\Models\User;
use App\Services\Messenger\ChatIntentParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class ChatIntentParserAttachmentTest extends TestCase
{
    use RefreshDatabase;

    private ChatIntentParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new ChatIntentParser;
    }

    public function test_build_attachment_context_includes_text_file_content(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $account = ConnectorAccount::factory()->create([
            'provider' => ConnectorAccount::PROVIDER_DISCORD,
            'status' => ConnectorAccount::STATUS_CONNECTED,
        ]);
        $session = ChatSession::factory()->create([
            'connector_account_id' => $account->id,
            'user_id' => $user->id,
        ]);

        $storagePath = "messenger/{$user->id}/{$session->id}/test-file.txt";
        Storage::disk('local')->put($storagePath, 'Hello, this is a test file content.');

        $message = ChatMessage::create([
            'chat_session_id' => $session->id,
            'connector_account_id' => $account->id,
            'direction' => ChatMessage::DIRECTION_INBOUND,
            'content' => 'Check this file',
            'idempotency_key' => hash('sha256', Str::uuid()->toString()),
            'created_at' => now(),
        ]);

        ChatAttachment::create([
            'id' => Str::uuid()->toString(),
            'chat_message_id' => $message->id,
            'filename' => 'test-file.txt',
            'mime_type' => 'text/plain',
            'size_bytes' => 35,
            'storage_path' => $storagePath,
            'scan_status' => ChatAttachment::SCAN_STATUS_SKIPPED,
            'expires_at' => now()->addDays(30),
            'created_at' => now(),
        ]);

        config(['messenger.attachment_config.storage_disk' => 'local']);

        $reflection = new \ReflectionClass($this->parser);
        $method = $reflection->getMethod('buildAttachmentContext');
        $method->setAccessible(true);

        $context = $method->invoke($this->parser, $message);

        $this->assertStringContainsString('test-file.txt', $context);
        $this->assertStringContainsString('text/plain', $context);
        $this->assertStringContainsString('Hello, this is a test file content.', $context);
    }

    public function test_build_attachment_context_excludes_binary_file_content(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $account = ConnectorAccount::factory()->create([
            'provider' => ConnectorAccount::PROVIDER_DISCORD,
            'status' => ConnectorAccount::STATUS_CONNECTED,
        ]);
        $session = ChatSession::factory()->create([
            'connector_account_id' => $account->id,
            'user_id' => $user->id,
        ]);

        $storagePath = "messenger/{$user->id}/{$session->id}/image.png";
        Storage::disk('local')->put($storagePath, 'binary-data');

        $message = ChatMessage::create([
            'chat_session_id' => $session->id,
            'connector_account_id' => $account->id,
            'direction' => ChatMessage::DIRECTION_INBOUND,
            'content' => 'Check this image',
            'idempotency_key' => hash('sha256', Str::uuid()->toString()),
            'created_at' => now(),
        ]);

        ChatAttachment::create([
            'id' => Str::uuid()->toString(),
            'chat_message_id' => $message->id,
            'filename' => 'image.png',
            'mime_type' => 'image/png',
            'size_bytes' => 50000,
            'storage_path' => $storagePath,
            'scan_status' => ChatAttachment::SCAN_STATUS_SKIPPED,
            'expires_at' => now()->addDays(30),
            'created_at' => now(),
        ]);

        config(['messenger.attachment_config.storage_disk' => 'local']);

        $reflection = new \ReflectionClass($this->parser);
        $method = $reflection->getMethod('buildAttachmentContext');
        $method->setAccessible(true);

        $context = $method->invoke($this->parser, $message);

        $this->assertStringContainsString('image.png', $context);
        $this->assertStringContainsString('image/png', $context);
        $this->assertStringNotContainsString('binary-data', $context);
        $this->assertStringNotContainsString('```', $context);
    }

    public function test_build_attachment_context_returns_empty_for_no_attachments(): void
    {
        $user = User::factory()->create();
        $account = ConnectorAccount::factory()->create([
            'provider' => ConnectorAccount::PROVIDER_DISCORD,
            'status' => ConnectorAccount::STATUS_CONNECTED,
        ]);
        $session = ChatSession::factory()->create([
            'connector_account_id' => $account->id,
            'user_id' => $user->id,
        ]);

        $message = ChatMessage::create([
            'chat_session_id' => $session->id,
            'connector_account_id' => $account->id,
            'direction' => ChatMessage::DIRECTION_INBOUND,
            'content' => 'No files here',
            'idempotency_key' => hash('sha256', Str::uuid()->toString()),
            'created_at' => now(),
        ]);

        $reflection = new \ReflectionClass($this->parser);
        $method = $reflection->getMethod('buildAttachmentContext');
        $method->setAccessible(true);

        $context = $method->invoke($this->parser, $message);

        $this->assertSame('', $context);
    }

    public function test_build_attachment_context_includes_json_file_content(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $account = ConnectorAccount::factory()->create([
            'provider' => ConnectorAccount::PROVIDER_DISCORD,
            'status' => ConnectorAccount::STATUS_CONNECTED,
        ]);
        $session = ChatSession::factory()->create([
            'connector_account_id' => $account->id,
            'user_id' => $user->id,
        ]);

        $jsonContent = '{"name": "test", "version": "1.0"}';
        $storagePath = "messenger/{$user->id}/{$session->id}/config.json";
        Storage::disk('local')->put($storagePath, $jsonContent);

        $message = ChatMessage::create([
            'chat_session_id' => $session->id,
            'connector_account_id' => $account->id,
            'direction' => ChatMessage::DIRECTION_INBOUND,
            'content' => 'Check this config',
            'idempotency_key' => hash('sha256', Str::uuid()->toString()),
            'created_at' => now(),
        ]);

        ChatAttachment::create([
            'id' => Str::uuid()->toString(),
            'chat_message_id' => $message->id,
            'filename' => 'config.json',
            'mime_type' => 'application/json',
            'size_bytes' => strlen($jsonContent),
            'storage_path' => $storagePath,
            'scan_status' => ChatAttachment::SCAN_STATUS_SKIPPED,
            'expires_at' => now()->addDays(30),
            'created_at' => now(),
        ]);

        config(['messenger.attachment_config.storage_disk' => 'local']);

        $reflection = new \ReflectionClass($this->parser);
        $method = $reflection->getMethod('buildAttachmentContext');
        $method->setAccessible(true);

        $context = $method->invoke($this->parser, $message);

        $this->assertStringContainsString('config.json', $context);
        $this->assertStringContainsString($jsonContent, $context);
    }

    public function test_build_attachment_context_skips_oversized_text_files(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $account = ConnectorAccount::factory()->create([
            'provider' => ConnectorAccount::PROVIDER_DISCORD,
            'status' => ConnectorAccount::STATUS_CONNECTED,
        ]);
        $session = ChatSession::factory()->create([
            'connector_account_id' => $account->id,
            'user_id' => $user->id,
        ]);

        $storagePath = "messenger/{$user->id}/{$session->id}/big.txt";
        Storage::disk('local')->put($storagePath, str_repeat('x', 100_000));

        $message = ChatMessage::create([
            'chat_session_id' => $session->id,
            'connector_account_id' => $account->id,
            'direction' => ChatMessage::DIRECTION_INBOUND,
            'content' => 'Check this file',
            'idempotency_key' => hash('sha256', Str::uuid()->toString()),
            'created_at' => now(),
        ]);

        ChatAttachment::create([
            'id' => Str::uuid()->toString(),
            'chat_message_id' => $message->id,
            'filename' => 'big.txt',
            'mime_type' => 'text/plain',
            'size_bytes' => 100_000,
            'storage_path' => $storagePath,
            'scan_status' => ChatAttachment::SCAN_STATUS_SKIPPED,
            'expires_at' => now()->addDays(30),
            'created_at' => now(),
        ]);

        config(['messenger.attachment_config.storage_disk' => 'local']);

        $reflection = new \ReflectionClass($this->parser);
        $method = $reflection->getMethod('buildAttachmentContext');
        $method->setAccessible(true);

        $context = $method->invoke($this->parser, $message);

        $this->assertStringContainsString('big.txt', $context);
        $this->assertStringContainsString('text/plain', $context);
        $this->assertStringNotContainsString('```', $context);
    }
}
