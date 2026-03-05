<?php

declare(strict_types=1);

namespace App\Support\Messenger;

/**
 * Markdown-aware content truncation for streaming previews.
 *
 * Inspired by OpenClaw's EmbeddedBlockChunker, this ensures streamed content
 * is never split inside code fences, and prefers clean break points
 * (paragraph > newline > whitespace > hard break).
 */
class MarkdownSafeContent
{
    private const FENCE_PATTERN = '/^( {0,3})(`{3,}|~{3,})/m';

    /**
     * Truncate content to a maximum length at a safe break point.
     *
     * Prefers: paragraph > newline > whitespace > hard break.
     * Never splits inside an open code fence — closes it and appends ellipsis.
     */
    public static function truncate(string $content, int $maxChars): string
    {
        if (mb_strlen($content) <= $maxChars) {
            return $content;
        }

        // Reserve space for ellipsis and possible fence closure
        $window = mb_substr($content, 0, $maxChars - 1);
        $breakIndex = self::findSafeBreak($window);

        $truncated = mb_substr($window, 0, $breakIndex);

        if (self::hasUnclosedFence($truncated)) {
            $truncated .= "\n```";
        }

        return $truncated.'…';
    }

    /**
     * Prepare content for an intermediate streaming edit.
     * Closes any open code fences so the preview renders correctly.
     */
    public static function prepareForPreview(string $content, int $maxChars, string $cursor = ' ▍'): string
    {
        $truncated = self::truncate($content, $maxChars - mb_strlen($cursor));

        if (self::hasUnclosedFence($truncated)) {
            return $truncated."\n```".$cursor;
        }

        return $truncated.$cursor;
    }

    /**
     * Find the best break index within the window, preferring clean boundaries.
     *
     * Priority: paragraph break > newline > whitespace > hard limit.
     */
    private static function findSafeBreak(string $window): int
    {
        $limit = mb_strlen($window);
        $fenceSpans = self::parseFenceSpans($window);

        // Search backwards from the limit for the best break point
        $lastParagraph = self::findLastBreak($window, "\n\n", $fenceSpans);
        if ($lastParagraph > (int) ($limit * 0.5)) {
            return $lastParagraph;
        }

        $lastNewline = self::findLastBreak($window, "\n", $fenceSpans);
        if ($lastNewline > (int) ($limit * 0.5)) {
            return $lastNewline;
        }

        $lastSpace = self::findLastBreak($window, ' ', $fenceSpans);
        if ($lastSpace > (int) ($limit * 0.5)) {
            return $lastSpace;
        }

        return $limit;
    }

    /**
     * Find the last occurrence of a separator that is NOT inside a code fence.
     *
     * @param  array<int, array{start: int, end: int}>  $fenceSpans
     */
    private static function findLastBreak(string $window, string $separator, array $fenceSpans): int
    {
        $pos = mb_strrpos($window, $separator);

        while ($pos !== false && $pos > 0) {
            if (! self::isInsideFence($pos, $fenceSpans)) {
                return $pos;
            }
            $pos = mb_strrpos(mb_substr($window, 0, $pos), $separator);
        }

        return 0;
    }

    /**
     * Parse code fence spans (start/end byte offsets) in the content.
     *
     * @return array<int, array{start: int, end: int}>
     */
    private static function parseFenceSpans(string $content): array
    {
        $spans = [];
        $lines = explode("\n", $content);
        $offset = 0;
        $openFenceStart = null;
        $openFenceMarker = null;

        foreach ($lines as $line) {
            if (preg_match(self::FENCE_PATTERN, $line, $matches)) {
                $marker = $matches[2][0]; // ` or ~
                $markerLen = strlen($matches[2]);

                if ($openFenceStart === null) {
                    $openFenceStart = $offset;
                    $openFenceMarker = $marker;
                } elseif ($marker === $openFenceMarker && $markerLen >= 3) {
                    $spans[] = ['start' => $openFenceStart, 'end' => $offset + strlen($line)];
                    $openFenceStart = null;
                    $openFenceMarker = null;
                }
            }

            $offset += strlen($line) + 1; // +1 for newline
        }

        // If there's an unclosed fence, extend the span to the end
        if ($openFenceStart !== null) {
            $spans[] = ['start' => $openFenceStart, 'end' => strlen($content)];
        }

        return $spans;
    }

    /**
     * Check if a position is inside any code fence span.
     *
     * @param  array<int, array{start: int, end: int}>  $fenceSpans
     */
    private static function isInsideFence(int $position, array $fenceSpans): bool
    {
        foreach ($fenceSpans as $span) {
            if ($position >= $span['start'] && $position <= $span['end']) {
                return true;
            }
        }

        return false;
    }

    /**
     * Detect whether the content has an unclosed code fence.
     */
    public static function hasUnclosedFence(string $content): bool
    {
        $lines = explode("\n", $content);
        $open = false;
        $openMarker = null;

        foreach ($lines as $line) {
            if (preg_match(self::FENCE_PATTERN, $line, $matches)) {
                $marker = $matches[2][0];
                $markerLen = strlen($matches[2]);

                if (! $open) {
                    $open = true;
                    $openMarker = $marker;
                } elseif ($marker === $openMarker && $markerLen >= 3) {
                    $open = false;
                    $openMarker = null;
                }
            }
        }

        return $open;
    }
}
