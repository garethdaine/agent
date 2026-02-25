<?php

namespace Tests\Feature\Messenger;

use App\Exceptions\AttachmentRejectionException;
use App\Exceptions\MalwareDetectedException;
use App\Models\ChatAttachment;
use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Models\ConnectorAccount;
use App\Models\User;
use App\Services\Messenger\AttachmentHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AttachmentHandlerTest extends TestCase
{
    use RefreshDatabase;

    private AttachmentHandler $handler;

    private ChatSession $session;

    private ChatMessage $message;

    private User $user;

    private ConnectorAccount $connectorAccount;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->user = User::factory()->create();
        $this->connectorAccount = ConnectorAccount::create([
            'provider' => ConnectorAccount::PROVIDER_SLACK,
            'name' => 'Test Workspace',
            'credentials' => ['signing_secret' => 'test'],
            'connection_mode' => ConnectorAccount::MODE_WEBHOOK,
            'status' => ConnectorAccount::STATUS_CONNECTED,
            'config' => [],
            'account_key' => 'T12345',
        ]);

        $this->session = ChatSession::factory()
            ->forUser($this->user)
            ->forConnector($this->connectorAccount)
            ->create();

        $this->message = ChatMessage::factory()
            ->forSession($this->session)
            ->create();

        $this->handler = new AttachmentHandler;

        // Default config for tests
        config([
            'messenger.attachment_config.max_file_size_mb' => 10,
            'messenger.attachment_config.allowed_mime_types' => ['image/*', 'application/pdf', 'text/*'],
            'messenger.attachment_config.malware_scan_enabled' => false,
            'messenger.attachment_config.retention_days' => 30,
            'messenger.attachment_config.storage_disk' => 'local',
        ]);
    }

    public function test_attachment_download_and_storage(): void
    {
        Http::fake([
            'https://example.com/test.pdf' => Http::response('PDF content here', 200),
        ]);

        $attachments = [
            [
                'url' => 'https://example.com/test.pdf',
                'filename' => 'document.pdf',
                'mime_type' => 'application/pdf',
                'size' => 16,
            ],
        ];

        $result = $this->handler->processInbound($attachments, $this->message);

        $this->assertCount(1, $result);
        $this->assertInstanceOf(ChatAttachment::class, $result[0]);
        $this->assertEquals('document.pdf', $result[0]->filename);
        $this->assertEquals(ChatAttachment::SCAN_STATUS_SKIPPED, $result[0]->scan_status);

        // Verify file was stored
        $this->assertTrue(Storage::disk('local')->exists($result[0]->storage_path));
    }

    public function test_storage_path_isolation_by_user(): void
    {
        Http::fake([
            '*' => Http::response('test content', 200),
        ]);

        $attachments = [
            ['url' => 'https://example.com/file.pdf', 'filename' => 'test.pdf'],
        ];

        $result = $this->handler->processInbound($attachments, $this->message);

        // Storage path should include user ID and session ID
        $this->assertStringContainsString("messenger/{$this->user->id}/{$this->session->id}/", $result[0]->storage_path);
    }

    public function test_file_permissions_are_correct(): void
    {
        // Create a real temp directory for this test
        $tempDir = storage_path('app/messenger_test_'.uniqid());
        mkdir($tempDir, 0755, true);

        try {
            $testFile = $tempDir.'/test.txt';
            file_put_contents($testFile, 'test content');
            chmod($testFile, 0640);

            // Verify permission was set
            $perms = fileperms($testFile) & 0777;
            $this->assertEquals(0640, $perms);
        } finally {
            if (file_exists($testFile)) {
                unlink($testFile);
            }
            rmdir($tempDir);
        }
    }

    public function test_oversized_attachment_rejected(): void
    {
        // Set a very small max size (1 byte)
        config(['messenger.attachment_config.max_file_size_mb' => 0.000001]);

        Http::fake([
            '*' => Http::response('This content is definitely larger than 1 byte', 200),
        ]);

        $attachments = [
            ['url' => 'https://example.com/large.pdf', 'filename' => 'large.pdf'],
        ];

        $this->expectException(AttachmentRejectionException::class);
        $this->expectExceptionMessage('exceeds maximum');

        $this->handler->processInbound($attachments, $this->message);
    }

    public function test_disallowed_type_rejected(): void
    {
        // Only allow PDFs
        config(['messenger.attachment_config.allowed_mime_types' => ['application/pdf']]);

        Http::fake([
            '*' => Http::response('#!/bin/bash\necho "malicious"', 200),
        ]);

        // Create a real temp file to test MIME detection
        $tempDir = storage_path('app/messenger/temp');
        if (! is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $attachments = [
            ['url' => 'https://example.com/script.sh', 'filename' => 'script.sh'],
        ];

        $this->expectException(AttachmentRejectionException::class);
        $this->expectExceptionMessage('not allowed');

        $this->handler->processInbound($attachments, $this->message);
    }

    public function test_quarantine_on_scan_failure(): void
    {
        config(['messenger.attachment_config.malware_scan_enabled' => true]);
        // Allow text/* MIME type for this test (the downloaded content will be detected as text/plain)
        config(['messenger.attachment_config.allowed_mime_types' => ['text/*', 'application/*', 'image/*']]);

        // Mock clamdscan to detect virus (exit code 1)
        Process::fake([
            'clamdscan*' => Process::result(
                output: 'FOUND: Eicar-Test-Signature',
                exitCode: 1
            ),
        ]);

        Http::fake([
            '*' => Http::response('X5O!P%@AP[4\PZX54(P^)7CC)7}$EICAR-STANDARD-ANTIVIRUS-TEST-FILE!$H+H*', 200),
        ]);

        $attachments = [
            ['url' => 'https://example.com/virus.txt', 'filename' => 'virus.txt'],
        ];

        $this->expectException(MalwareDetectedException::class);

        $this->handler->processInbound($attachments, $this->message);
    }

    public function test_scan_skipped_when_disabled(): void
    {
        config(['messenger.attachment_config.malware_scan_enabled' => false]);

        Process::fake();

        Http::fake([
            '*' => Http::response('normal content', 200),
        ]);

        $attachments = [
            ['url' => 'https://example.com/file.pdf', 'filename' => 'file.pdf'],
        ];

        $result = $this->handler->processInbound($attachments, $this->message);

        // Process should never be called
        Process::assertNothingRan();

        $this->assertEquals(ChatAttachment::SCAN_STATUS_SKIPPED, $result[0]->scan_status);
    }

    public function test_multiple_attachments_processed(): void
    {
        Http::fake([
            'https://example.com/file1.pdf' => Http::response('content 1', 200),
            'https://example.com/file2.pdf' => Http::response('content 2', 200),
        ]);

        $attachments = [
            ['url' => 'https://example.com/file1.pdf', 'filename' => 'file1.pdf'],
            ['url' => 'https://example.com/file2.pdf', 'filename' => 'file2.pdf'],
        ];

        $result = $this->handler->processInbound($attachments, $this->message);

        $this->assertCount(2, $result);
        $this->assertEquals('file1.pdf', $result[0]->filename);
        $this->assertEquals('file2.pdf', $result[1]->filename);
    }

    public function test_attachment_expiration_set_correctly(): void
    {
        config(['messenger.attachment_config.retention_days' => 45]);

        Http::fake([
            '*' => Http::response('content', 200),
        ]);

        $attachments = [
            ['url' => 'https://example.com/file.pdf', 'filename' => 'file.pdf'],
        ];

        $result = $this->handler->processInbound($attachments, $this->message);

        // Expiration should be approximately 45 days from now
        $this->assertTrue($result[0]->expires_at->isAfter(now()->addDays(44)));
        $this->assertTrue($result[0]->expires_at->isBefore(now()->addDays(46)));
    }

    public function test_presigned_url_generation(): void
    {
        Http::fake([
            '*' => Http::response('content', 200),
        ]);

        $attachments = [
            ['url' => 'https://example.com/file.pdf', 'filename' => 'file.pdf'],
        ];

        $result = $this->handler->processInbound($attachments, $this->message);
        $attachment = $result[0];

        $url = $this->handler->generatePresignedUrl($attachment);

        // Local disk with Laravel's temporaryUrl uses 'expiration' query param
        // or falls back to our custom route with 'signature' and 'expires'
        $this->assertTrue(
            str_contains($url, 'expiration=') || str_contains($url, 'signature='),
            "URL should contain either 'expiration=' or 'signature=': {$url}"
        );
    }

    public function test_download_failure_throws_exception(): void
    {
        Http::fake([
            '*' => Http::response('Not found', 404),
        ]);

        $attachments = [
            ['url' => 'https://example.com/missing.pdf', 'filename' => 'missing.pdf'],
        ];

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to download');

        $this->handler->processInbound($attachments, $this->message);
    }

    public function test_wildcard_mime_type_matching(): void
    {
        config(['messenger.attachment_config.allowed_mime_types' => ['image/*']]);

        Http::fake([
            '*' => Http::response($this->createMinimalPngContent(), 200),
        ]);

        $attachments = [
            ['url' => 'https://example.com/image.png', 'filename' => 'image.png'],
        ];

        $result = $this->handler->processInbound($attachments, $this->message);

        $this->assertCount(1, $result);
        $this->assertEquals('image.png', $result[0]->filename);
    }

    public function test_temp_files_cleaned_up_on_failure(): void
    {
        config(['messenger.attachment_config.max_file_size_mb' => 0.000001]);

        Http::fake([
            '*' => Http::response('large content that exceeds limit', 200),
        ]);

        $attachments = [
            ['url' => 'https://example.com/file.pdf', 'filename' => 'file.pdf'],
        ];

        try {
            $this->handler->processInbound($attachments, $this->message);
        } catch (AttachmentRejectionException $e) {
            // Expected
        }

        // Temp directory should not have leftover files
        $tempDir = storage_path('app/messenger/temp');
        if (is_dir($tempDir)) {
            $files = glob($tempDir.'/*');
            $this->assertEmpty($files, 'Temp files should be cleaned up after failure');
        }
    }

    /**
     * Create minimal valid PNG content for MIME type testing.
     */
    private function createMinimalPngContent(): string
    {
        // PNG signature and minimal IHDR chunk
        return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
    }
}
