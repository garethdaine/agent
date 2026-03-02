<?php

namespace Tests\Feature\Jobs;

use App\Jobs\Messenger\ProcessInboundMessage;
use App\Models\ChatAttachment;
use App\Models\ConnectorAccount;
use App\Models\MessengerIdentityLink;
use App\Models\User;
use App\Services\Messenger\ChatSessionManager;
use App\Support\Messenger\ConnectorManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProcessInboundMessageAttachmentTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private ConnectorAccount $account;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Queue::fake();

        $this->user = User::factory()->create();

        $this->account = ConnectorAccount::factory()->slack()->create([
            'status' => ConnectorAccount::STATUS_CONNECTED,
        ]);

        // Create identity link for the user
        MessengerIdentityLink::factory()->create([
            'user_id' => $this->user->id,
            'connector_account_id' => $this->account->id,
            'provider_user_id' => 'U12345',
        ]);

        // Default config for tests
        config([
            'messenger.attachment_config.max_file_size_mb' => 10,
            'messenger.attachment_config.allowed_mime_types' => ['image/*', 'application/pdf', 'text/*'],
            'messenger.attachment_config.malware_scan_enabled' => false,
            'messenger.attachment_config.retention_days' => 30,
            'messenger.attachment_config.storage_disk' => 'local',
        ]);
    }

    #[Test]
    public function it_downloads_and_stores_attachments(): void
    {
        Http::fake([
            'https://files.slack.com/files-pri/T12345-F123456/document.pdf' => Http::response('PDF content here', 200),
        ]);

        $payload = [
            'event' => [
                'type' => 'message',
                'user' => 'U12345',
                'channel' => 'C12345',
                'text' => 'Here is a file',
                'ts' => '1234567890.123456',
                'files' => [
                    [
                        'id' => 'F123456',
                        'name' => 'document.pdf',
                        'mimetype' => 'application/pdf',
                        'size' => 16,
                        'url_private_download' => 'https://files.slack.com/files-pri/T12345-F123456/document.pdf',
                    ],
                ],
            ],
            'event_id' => 'Ev12345',
        ];

        $job = new ProcessInboundMessage($this->account->id, 'slack', $payload);
        $job->handle(
            app(ConnectorManager::class),
            app(ChatSessionManager::class)
        );

        $this->assertDatabaseHas('chat_attachments', [
            'provider_file_id' => 'F123456',
            'filename' => 'document.pdf',
            'mime_type' => 'application/pdf',
        ]);

        // Verify the attachment is linked to a message
        $attachment = ChatAttachment::where('provider_file_id', 'F123456')->first();
        $this->assertNotNull($attachment);
        $this->assertNotNull($attachment->chat_message_id);
        $this->assertEquals(ChatAttachment::SCAN_STATUS_SKIPPED, $attachment->scan_status);
    }

    #[Test]
    public function it_creates_failed_record_on_download_error(): void
    {
        Http::fake([
            '*' => Http::response('Not found', 404),
        ]);

        $payload = [
            'event' => [
                'type' => 'message',
                'user' => 'U12345',
                'channel' => 'C12345',
                'text' => 'Here is a file',
                'ts' => '1234567891.123456',
                'files' => [
                    [
                        'id' => 'F123457',
                        'name' => 'file.txt',
                        'mimetype' => 'text/plain',
                        'size' => 100,
                        'url_private_download' => 'https://files.slack.com/files-pri/T12345-F123457/file.txt',
                    ],
                ],
            ],
            'event_id' => 'Ev12346',
        ];

        $job = new ProcessInboundMessage($this->account->id, 'slack', $payload);
        $job->handle(
            app(ConnectorManager::class),
            app(ChatSessionManager::class)
        );

        // Message should still be created (attachment failure doesn't block message)
        $this->assertDatabaseHas('chat_messages', [
            'connector_account_id' => $this->account->id,
            'content' => 'Here is a file',
        ]);

        // No attachment record should exist for failed download
        $this->assertDatabaseMissing('chat_attachments', [
            'provider_file_id' => 'F123457',
        ]);
    }

    #[Test]
    public function it_rejects_files_exceeding_max_size(): void
    {
        config(['messenger.attachment_config.max_file_size_mb' => 0.0001]); // ~100 bytes

        Http::fake([
            '*' => Http::response(str_repeat('x', 200), 200), // 200 bytes - exceeds limit
        ]);

        $payload = [
            'event' => [
                'type' => 'message',
                'user' => 'U12345',
                'channel' => 'C12345',
                'text' => 'Big file',
                'ts' => '1234567892.123456',
                'files' => [
                    [
                        'id' => 'F123458',
                        'name' => 'big.txt',
                        'mimetype' => 'text/plain',
                        'size' => 200,
                        'url_private_download' => 'https://files.slack.com/files-pri/T12345-F123458/big.txt',
                    ],
                ],
            ],
            'event_id' => 'Ev12347',
        ];

        $job = new ProcessInboundMessage($this->account->id, 'slack', $payload);
        $job->handle(
            app(ConnectorManager::class),
            app(ChatSessionManager::class)
        );

        // Message should still be created
        $this->assertDatabaseHas('chat_messages', [
            'connector_account_id' => $this->account->id,
            'content' => 'Big file',
        ]);

        // No attachment record should exist for rejected file
        $this->assertDatabaseMissing('chat_attachments', [
            'provider_file_id' => 'F123458',
        ]);
    }

    #[Test]
    public function it_processes_multiple_attachments_independently(): void
    {
        Http::fake([
            'https://files.slack.com/files-pri/T12345-F100/file1.pdf' => Http::response('PDF 1', 200),
            'https://files.slack.com/files-pri/T12345-F200/file2.pdf' => Http::response('PDF 2', 200),
            'https://files.slack.com/files-pri/T12345-F300/file3.pdf' => Http::response('Not found', 404),
        ]);

        $payload = [
            'event' => [
                'type' => 'message',
                'user' => 'U12345',
                'channel' => 'C12345',
                'text' => 'Multiple files',
                'ts' => '1234567893.123456',
                'files' => [
                    [
                        'id' => 'F100',
                        'name' => 'file1.pdf',
                        'mimetype' => 'application/pdf',
                        'size' => 5,
                        'url_private_download' => 'https://files.slack.com/files-pri/T12345-F100/file1.pdf',
                    ],
                    [
                        'id' => 'F200',
                        'name' => 'file2.pdf',
                        'mimetype' => 'application/pdf',
                        'size' => 5,
                        'url_private_download' => 'https://files.slack.com/files-pri/T12345-F200/file2.pdf',
                    ],
                    [
                        'id' => 'F300',
                        'name' => 'file3.pdf',
                        'mimetype' => 'application/pdf',
                        'size' => 5,
                        'url_private_download' => 'https://files.slack.com/files-pri/T12345-F300/file3.pdf',
                    ],
                ],
            ],
            'event_id' => 'Ev12348',
        ];

        $job = new ProcessInboundMessage($this->account->id, 'slack', $payload);
        $job->handle(
            app(ConnectorManager::class),
            app(ChatSessionManager::class)
        );

        // Two attachments should be stored (F100 and F200 succeeded, F300 failed)
        $this->assertDatabaseHas('chat_attachments', ['provider_file_id' => 'F100']);
        $this->assertDatabaseHas('chat_attachments', ['provider_file_id' => 'F200']);
        $this->assertDatabaseMissing('chat_attachments', ['provider_file_id' => 'F300']);
    }

    #[Test]
    public function it_handles_messages_without_attachments(): void
    {
        $payload = [
            'event' => [
                'type' => 'message',
                'user' => 'U12345',
                'channel' => 'C12345',
                'text' => 'No attachments',
                'ts' => '1234567894.123456',
            ],
            'event_id' => 'Ev12349',
        ];

        $job = new ProcessInboundMessage($this->account->id, 'slack', $payload);
        $job->handle(
            app(ConnectorManager::class),
            app(ChatSessionManager::class)
        );

        // Message should be created
        $this->assertDatabaseHas('chat_messages', [
            'connector_account_id' => $this->account->id,
            'content' => 'No attachments',
        ]);

        // No attachments should exist
        $this->assertDatabaseCount('chat_attachments', 0);
    }

    #[Test]
    public function it_stores_attachment_metadata_correctly(): void
    {
        Http::fake([
            '*' => Http::response('File content for testing', 200),
        ]);

        $payload = [
            'event' => [
                'type' => 'message',
                'user' => 'U12345',
                'channel' => 'C12345',
                'text' => 'Metadata test',
                'ts' => '1234567895.123456',
                'files' => [
                    [
                        'id' => 'F999999',
                        'name' => 'test-file.pdf',
                        'mimetype' => 'application/pdf',
                        'size' => 24,
                        'url_private_download' => 'https://files.slack.com/files-pri/T12345-F999999/test-file.pdf',
                    ],
                ],
            ],
            'event_id' => 'Ev99999',
        ];

        $job = new ProcessInboundMessage($this->account->id, 'slack', $payload);
        $job->handle(
            app(ConnectorManager::class),
            app(ChatSessionManager::class)
        );

        $attachment = ChatAttachment::where('provider_file_id', 'F999999')->first();

        $this->assertNotNull($attachment);
        $this->assertEquals('test-file.pdf', $attachment->filename);
        $this->assertNotNull($attachment->storage_path);
        $this->assertNotNull($attachment->expires_at);
        $this->assertEquals(ChatAttachment::SCAN_STATUS_SKIPPED, $attachment->scan_status);
    }
}
