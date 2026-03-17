<?php

declare(strict_types=1);

namespace App\Support\WorkflowInterrogator;

use App\Models\WorkflowInterrogationAttachment;
use App\Models\WorkflowInterrogationSession;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class WorkflowInterrogationAttachmentStore
{
    /**
     * @param  array<int, UploadedFile>  $uploads
     * @return array<int, WorkflowInterrogationAttachment>
     */
    public function storeUploads(WorkflowInterrogationSession $session, array $uploads): array
    {
        $stored = [];
        $disk = Storage::disk('local');

        try {
            foreach ($uploads as $upload) {
                $stored[] = $this->storeSingleUpload($session, $upload, $disk);
            }
        } catch (\Throwable $throwable) {
            foreach ($stored as $attachment) {
                if ($attachment->storage_path !== '' && $disk->exists($attachment->storage_path)) {
                    $disk->delete($attachment->storage_path);
                }

                $attachment->delete();
            }

            throw $throwable;
        }

        return $stored;
    }

    private function storeSingleUpload(
        WorkflowInterrogationSession $session,
        UploadedFile $upload,
        \Illuminate\Contracts\Filesystem\Filesystem $disk,
    ): WorkflowInterrogationAttachment {
        $originalName = trim((string) $upload->getClientOriginalName());
        $filename = $originalName !== '' ? $originalName : $upload->hashName();
        $safeName = Str::uuid()->toString().'_'.preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);
        $storagePath = sprintf('workflow-interrogator/%d/%d/%s', (int) $session->user_id, (int) $session->id, $safeName);

        $stream = fopen($upload->getRealPath(), 'rb');
        if ($stream === false) {
            throw ValidationException::withMessages([
                'attachments' => sprintf('Could not read uploaded file "%s".', $filename),
            ]);
        }

        try {
            $disk->put($storagePath, $stream);
        } finally {
            fclose($stream);
        }

        $mimeType = (string) ($upload->getMimeType() ?: 'application/octet-stream');
        $attachment = WorkflowInterrogationAttachment::query()->create([
            'workflow_interrogation_session_id' => (int) $session->id,
            'filename' => $filename,
            'mime_type' => $mimeType,
            'size_bytes' => (int) $upload->getSize(),
            'storage_disk' => 'local',
            'storage_path' => $storagePath,
            'extracted_text' => $this->extractText($upload, $filename, $mimeType),
        ]);

        if (method_exists($disk, 'path')) {
            $path = $disk->path($storagePath);
            if (is_string($path) && $path !== '' && file_exists($path)) {
                @chmod($path, 0640);
            }
        }

        return $attachment;
    }

    private function extractText(UploadedFile $upload, string $filename, string $mimeType): ?string
    {
        if (! $this->isTextLike($filename, $mimeType)) {
            return null;
        }

        $content = file_get_contents($upload->getRealPath());
        if (! is_string($content)) {
            throw ValidationException::withMessages([
                'attachments' => sprintf('Could not read uploaded file "%s".', $filename),
            ]);
        }

        if (! mb_check_encoding($content, 'UTF-8')) {
            throw ValidationException::withMessages([
                'attachments' => sprintf('Uploaded file "%s" must be UTF-8 encoded.', $filename),
            ]);
        }

        $content = trim($content);
        if ($content === '') {
            return null;
        }

        return mb_substr($content, 0, 20000);
    }

    private function isTextLike(string $filename, string $mimeType): bool
    {
        if (str_starts_with($mimeType, 'text/')) {
            return true;
        }

        $extension = strtolower((string) pathinfo($filename, PATHINFO_EXTENSION));

        return in_array($extension, ['md', 'markdown', 'txt', 'json', 'yaml', 'yml', 'csv'], true);
    }
}
