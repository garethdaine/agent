<?php

namespace App\Services\Messenger;

use App\Exceptions\AttachmentRejectionException;
use App\Exceptions\MalwareDetectedException;
use App\Models\ChatAttachment;
use App\Models\ChatMessage;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AttachmentHandler
{
    private string $tempDir;

    public function __construct()
    {
        $this->tempDir = storage_path('app/messenger/temp');

        if (! is_dir($this->tempDir)) {
            mkdir($this->tempDir, 0750, true);
        }
    }

    /**
     * Process inbound attachments from a messenger message.
     *
     * @param  array<int, array{url: string, filename?: string, mime_type?: string, size?: int}>  $attachments
     * @return array<int, ChatAttachment>
     *
     * @throws AttachmentRejectionException
     * @throws MalwareDetectedException
     */
    public function processInbound(array $attachments, ChatMessage $message): array
    {
        $processed = [];

        foreach ($attachments as $attachment) {
            $tempPath = $this->download($attachment['url'], $attachment['filename'] ?? null);

            try {
                $this->validateSize($tempPath, $attachment['filename'] ?? basename($attachment['url']));
                $this->validateMimeType($tempPath, $attachment['filename'] ?? basename($attachment['url']));
                $this->scan($tempPath);

                $stored = $this->store($tempPath, $message, $attachment);
                $processed[] = $stored;
            } finally {
                // Always clean up temp file
                if (file_exists($tempPath)) {
                    unlink($tempPath);
                }
            }
        }

        return $processed;
    }

    /**
     * Download a file from URL to temporary storage.
     */
    public function download(string $url, ?string $filename = null): string
    {
        $filename = $filename ?? basename(parse_url($url, PHP_URL_PATH) ?: Str::uuid()->toString());
        $safeName = Str::uuid()->toString().'_'.preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);
        $tempPath = $this->tempDir.'/'.$safeName;

        $response = $this->getHttpClient()->get($url);

        if (! $response->successful()) {
            throw new \RuntimeException("Failed to download attachment from {$url}: {$response->status()}");
        }

        file_put_contents($tempPath, $response->body());

        return $tempPath;
    }

    /**
     * Validate file size against configured maximum.
     *
     * @throws AttachmentRejectionException
     */
    public function validateSize(string $path, string $filename): void
    {
        $maxSize = $this->getMaxFileSize();
        $fileSize = filesize($path);

        if ($fileSize === false) {
            throw new \RuntimeException("Unable to determine file size: {$path}");
        }

        if ($fileSize > $maxSize) {
            throw AttachmentRejectionException::oversized($filename, $fileSize, $maxSize);
        }
    }

    /**
     * Validate file MIME type against allowed types.
     *
     * @throws AttachmentRejectionException
     */
    public function validateMimeType(string $path, string $filename): void
    {
        $mimeType = mime_content_type($path);

        if ($mimeType === false) {
            throw new \RuntimeException("Unable to determine MIME type: {$path}");
        }

        if (! $this->isAllowedMimeType($mimeType)) {
            throw AttachmentRejectionException::disallowedType($filename, $mimeType);
        }
    }

    /**
     * Scan file for malware using ClamAV (if enabled).
     *
     * @throws MalwareDetectedException
     */
    public function scan(string $path): void
    {
        if (! $this->isScanEnabled()) {
            return;
        }

        $result = Process::run("clamdscan --no-summary {$path}");

        // Exit code 1 means virus found
        if ($result->exitCode() === 1) {
            $this->quarantine($path);
            throw new MalwareDetectedException($path, $result->output());
        }

        // Exit code > 1 means error (scanner unavailable, etc.)
        if ($result->exitCode() > 1) {
            Log::warning('ClamAV scanner error', [
                'path' => $path,
                'exit_code' => $result->exitCode(),
                'output' => $result->output(),
                'error' => $result->errorOutput(),
            ]);
        }
    }

    /**
     * Store a downloaded file permanently.
     *
     * @param  array{url: string, filename?: string, mime_type?: string, size?: int, provider_file_id?: string}  $metadata
     */
    public function store(string $tempPath, ChatMessage $message, array $metadata): ChatAttachment
    {
        $session = $message->session;
        $userId = $session->user_id;
        $sessionId = $session->id;

        $filename = $metadata['filename'] ?? basename($metadata['url']);
        $safeName = Str::uuid()->toString().'_'.preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);
        $storagePath = "messenger/{$userId}/{$sessionId}/{$safeName}";

        $disk = $this->getStorageDisk();
        $disk->put($storagePath, file_get_contents($tempPath));

        // Set restrictive permissions on local disk
        if (config('messenger.attachment_config.storage_disk', 'local') === 'local') {
            $fullPath = $disk->path($storagePath);
            if (file_exists($fullPath)) {
                chmod($fullPath, 0640);
            }
        }

        $retentionDays = (int) config('messenger.attachment_config.retention_days', 30);

        return ChatAttachment::create([
            'id' => Str::uuid()->toString(),
            'chat_message_id' => $message->id,
            'filename' => $filename,
            'mime_type' => $metadata['mime_type'] ?? mime_content_type($tempPath),
            'size_bytes' => $metadata['size'] ?? filesize($tempPath),
            'storage_path' => $storagePath,
            'provider_file_id' => $metadata['provider_file_id'] ?? null,
            'scan_status' => $this->isScanEnabled() ? ChatAttachment::SCAN_STATUS_CLEAN : ChatAttachment::SCAN_STATUS_SKIPPED,
            'expires_at' => now()->addDays($retentionDays),
            'created_at' => now(),
        ]);
    }

    /**
     * Move a file to quarantine.
     */
    public function quarantine(string $path): void
    {
        $quarantineDir = storage_path('app/messenger/quarantine');

        if (! is_dir($quarantineDir)) {
            mkdir($quarantineDir, 0750, true);
        }

        $quarantinePath = $quarantineDir.'/'.Str::uuid()->toString().'_'.basename($path);
        rename($path, $quarantinePath);

        Log::warning('File quarantined due to malware detection', [
            'original_path' => $path,
            'quarantine_path' => $quarantinePath,
        ]);
    }

    /**
     * Generate a presigned URL for attachment access.
     */
    public function generatePresignedUrl(ChatAttachment $attachment): string
    {
        $disk = $this->getStorageDisk();
        $ttlMinutes = (int) config('messenger.attachment_config.signed_url_ttl_minutes', 15);

        if (method_exists($disk, 'temporaryUrl')) {
            return $disk->temporaryUrl($attachment->storage_path, now()->addMinutes($ttlMinutes));
        }

        // For local disk, generate a signed route
        return route('messenger.attachments.download', [
            'attachment' => $attachment->id,
            'signature' => $this->generateDownloadSignature($attachment, $ttlMinutes),
            'expires' => now()->addMinutes($ttlMinutes)->timestamp,
        ]);
    }

    /**
     * Get the storage disk instance.
     */
    public function getStorageDisk(): \Illuminate\Contracts\Filesystem\Filesystem
    {
        return Storage::disk(config('messenger.attachment_config.storage_disk', 'local'));
    }

    /**
     * Check if a MIME type is allowed.
     */
    private function isAllowedMimeType(string $mimeType): bool
    {
        $allowedTypes = config('messenger.attachment_config.allowed_mime_types', ['image/*', 'application/pdf', 'text/*']);

        foreach ($allowedTypes as $pattern) {
            if ($pattern === $mimeType) {
                return true;
            }

            // Handle wildcard patterns like 'image/*'
            if (str_ends_with($pattern, '/*')) {
                $prefix = substr($pattern, 0, -2);
                if (str_starts_with($mimeType, $prefix.'/')) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Get the maximum allowed file size in bytes.
     */
    private function getMaxFileSize(): int
    {
        return (int) config('messenger.attachment_config.max_file_size_mb', 10) * 1024 * 1024;
    }

    /**
     * Check if malware scanning is enabled.
     */
    private function isScanEnabled(): bool
    {
        return (bool) config('messenger.attachment_config.malware_scan_enabled', false);
    }

    /**
     * Get configured HTTP client for downloads.
     */
    private function getHttpClient(): PendingRequest
    {
        return Http::timeout(30)->withOptions([
            'verify' => true,
        ]);
    }

    /**
     * Generate a download signature for local file access.
     */
    private function generateDownloadSignature(ChatAttachment $attachment, int $ttlMinutes): string
    {
        $expires = now()->addMinutes($ttlMinutes)->timestamp;
        $data = $attachment->id.$expires.config('app.key');

        return hash_hmac('sha256', $data, config('app.key'));
    }
}
