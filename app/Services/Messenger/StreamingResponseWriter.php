<?php

declare(strict_types=1);

namespace App\Services\Messenger;

use App\Contracts\Messenger\ConnectorAdapterInterface;
use App\DTOs\Messenger\StreamingConfig;
use App\Models\ChatSession;
use App\Support\Messenger\MarkdownSafeContent;
use Illuminate\Support\Facades\Log;

/**
 * Progressive message editing for streaming AI responses.
 *
 * Inspired by OpenClaw's draft-stream-loop pattern:
 * - Per-channel config (max chars, throttle, min initial chars)
 * - Markdown-aware truncation with code fence safety
 * - Min-chars threshold before first preview (avoids flashing single words)
 * - Coalescing: only the latest accumulated state is sent per flush
 */
class StreamingResponseWriter
{
    private string $accumulated = '';

    private float $lastEditAt = 0;

    private int $editCount = 0;

    private bool $inFlight = false;

    private readonly StreamingConfig $config;

    public function __construct(
        private readonly ConnectorAdapterInterface $adapter,
        private readonly ChatSession $session,
        private readonly string $messageId,
        ?StreamingConfig $config = null,
    ) {
        $this->config = $config ?? $adapter->getStreamingConfig();
    }

    public function append(string $chunk): void
    {
        $this->accumulated .= $chunk;
        $this->maybeFlush();
    }

    public function finalize(): void
    {
        $content = MarkdownSafeContent::truncate(
            $this->accumulated,
            $this->config->maxMessageChars
        );

        if ($content === '') {
            return;
        }

        $this->doEdit($content);
    }

    public function forceWrite(string $content): void
    {
        $this->accumulated = $content;
        $this->doEdit(MarkdownSafeContent::truncate($content, $this->config->maxMessageChars));
    }

    public function getContent(): string
    {
        return $this->accumulated;
    }

    public function getEditCount(): int
    {
        return $this->editCount;
    }

    public function getConfig(): StreamingConfig
    {
        return $this->config;
    }

    private function maybeFlush(): void
    {
        if ($this->inFlight) {
            return;
        }

        $trimmed = trim($this->accumulated);

        if ($trimmed === '') {
            return;
        }

        // Don't send first preview until enough text accumulates
        if ($this->editCount === 0 && mb_strlen($trimmed) < $this->config->minInitialChars) {
            return;
        }

        $now = microtime(true);
        $elapsed = $now - $this->lastEditAt;

        if ($elapsed < $this->config->throttleSeconds()) {
            return;
        }

        $display = MarkdownSafeContent::prepareForPreview(
            $this->accumulated,
            $this->config->maxMessageChars,
            $this->config->cursor
        );

        $this->doEdit($display);
    }

    private function doEdit(string $content): void
    {
        $this->inFlight = true;

        try {
            $this->adapter->editMessage($this->session, $this->messageId, $content);
            $this->lastEditAt = microtime(true);
            $this->editCount++;
        } catch (\Throwable $e) {
            Log::warning('StreamingResponseWriter: Edit failed', [
                'message_id' => $this->messageId,
                'edit_count' => $this->editCount,
                'error' => $e->getMessage(),
            ]);
        } finally {
            $this->inFlight = false;
        }
    }
}
