<?php

declare(strict_types=1);

namespace App\Support\Agent;

use Illuminate\Support\Str;

class EventPatternMatcher
{
    private const APPROVAL_PATTERN = '/\b(?:need|needs|required|requires)\s+(?:your\s+)?permission\b|\bcould you approve\b|\bplease approve\b|\bapproval required\b/i';

    private const APPROVAL_FALSE_POSITIVE_PATTERN = '/\bapproval likely required in active run output\b|\|\s*approval required\s*\||\bskill access by risk level\b/i';

    private const PERMISSION_BLOCKER_PATTERN = '/\b(?:need|needs|required|requires)\s+(?:your\s+)?(?:file\s+)?write\s+permissions?\b|\bgrant\s+(?:file\s+)?write\s+permissions?\b|\bwrite\s+permissions?\s+(?:have\s+not\s+been|haven\'t\s+been|were\s+not|are\s+not)\s+granted\b|\b(?:all\s+)?file\s+write\s+operations?\s+are\s+denied\b|\bcannot\s+(?:create|write)\s+(?:any\s+)?(?:new\s+)?files?\b|\bpermission\s+(?:loop|wall)\b/i';

    private const CLARIFICATION_PATTERN = '/\b(?:could|can)\s+you\s+clarify\b|\b(?:i|we)\s+need\s+(?:your\s+)?clarification\b|\bneed\s+clarification\s+from\s+you\b|\bplease\s+clarify\b|\bquestion\s+for\s+you\b|\bcan\s+you\s+confirm\b|\bshould\s+i\s+(?:proceed|continue|use|do)\b/i';

    public const RATE_LIMIT_PATTERN = '/\bhit(?:ting)?\s+(?:your\s+)?limit\b|\brate[-\s]?limited\b|\btoo many requests\b|\bquota exceeded\b|\b(?:status|code|error|http)\s*[:=]?\s*429\b|\bretry[-\s]?after\b/i';

    private const LINE_NUMBERED_SNIPPET_PATTERN = '/(?:^|\n|(?:\\\\)+n)\s*\d+\s*(?:→|->|=>)/u';

    private const ESCAPED_NEWLINE_PATTERN = '/(?:\\\\)+n/';

    private const CODE_LIKE_SNIPPET_PATTERN = '/\b(?:public|protected|private)\s+function\b|\bfile_put_contents\s*\(|\$\w+->\w+\s*\(|\bassert(?:Same|True|False|Null|NotEmpty|ArrayHasKey|StringContainsString)\s*\(|\bconfig\(\)->set\s*\(|\breturn\s+\$[A-Za-z_][A-Za-z0-9_]*\b/i';

    private const INLINE_CODE_TOKENS_PATTERN = '/<\s*\/?\s*[A-Za-z][^>]*>|\b(?:const|let|var|function|return|if|foreach)\b|=>|->|::|class=|v-if=|\$[A-Za-z_][A-Za-z0-9_]*/';

    private const MCP_CONNECTION_REFUSED_PATTERN = '/rmcp::transport::worker[\s\S]{0,1800}?transport channel closed[\s\S]{0,1800}?(https?:\/\/[^\s"\']+\/mcp)[\s\S]{0,1800}?connection refused/i';

    public function shouldMarkApprovalRequired(string $chunk): bool
    {
        if (preg_match(self::APPROVAL_PATTERN, $chunk) !== 1) {
            return false;
        }

        if (preg_match(self::APPROVAL_FALSE_POSITIVE_PATTERN, $chunk) === 1) {
            return false;
        }

        return true;
    }

    public function matchesPermissionBlockerPattern(string $chunk): bool
    {
        return preg_match(self::PERMISSION_BLOCKER_PATTERN, $chunk) === 1;
    }

    public function matchesClarificationPattern(string $chunk): bool
    {
        return preg_match(self::CLARIFICATION_PATTERN, $chunk) === 1;
    }

    public function shouldMarkRateLimitDetected(string $chunk): bool
    {
        return $this->isStructuredRateLimitErrorEvent($chunk);
    }

    public function matchesRateLimitPattern(string $text): bool
    {
        return preg_match(self::RATE_LIMIT_PATTERN, $text) === 1;
    }

    public function isLikelyNonRuntimeSnippet(string $chunk): bool
    {
        if ($this->isStructuredMachineEventWithoutAssistantIntent($chunk)) {
            return true;
        }

        if ($this->isLineNumberedSnippet($chunk)) {
            return true;
        }

        if ($this->isInlineCodeSnippet($chunk)) {
            return true;
        }

        if (preg_match(self::ESCAPED_NEWLINE_PATTERN, $chunk) !== 1) {
            return false;
        }

        return preg_match(self::CODE_LIKE_SNIPPET_PATTERN, $chunk) === 1;
    }

    public function shouldSuppressAsNoise(string $chunk): bool
    {
        if (! config('agent.log_filtering.suppress_machine_noise', true)) {
            return false;
        }

        return $this->isMcpToolListDump($chunk)
            || $this->isConfigNameListDump($chunk)
            || $this->isStreamJsonMetadataFragment($chunk)
            || $this->isToolResultMetadataEcho($chunk);
    }

    /**
     * @return array<int, string>
     */
    public function extractMcpUnavailableEndpoints(string $chunk): array
    {
        $matchCount = preg_match_all(self::MCP_CONNECTION_REFUSED_PATTERN, $chunk, $matches);
        if (! is_int($matchCount) || $matchCount < 1) {
            return [];
        }

        $endpoints = [];
        $captured = $matches[1] ?? []; // @phpstan-ignore nullCoalesce.offset

        if (! is_array($captured)) { // @phpstan-ignore function.alreadyNarrowedType
            return [];
        }

        foreach ($captured as $candidate) {
            $endpoint = trim((string) $candidate);
            if ($endpoint === '') {
                continue;
            }

            $endpoints[] = $endpoint;
        }

        return $endpoints;
    }

    /**
     * @return array{reset_at:\Carbon\CarbonImmutable,timezone:string}|null
     */
    public function extractRateLimitReset(string $excerpt, ?string $fallbackTimezone = null): ?array
    {
        $timezone = $this->extractTimezoneFromExcerpt($excerpt)
            ?? $fallbackTimezone
            ?? 'UTC';

        if (! in_array($timezone, timezone_identifiers_list(), true)) {
            $timezone = 'UTC';
        }

        if (preg_match('/resets?\s+(?:at\s+)?([0-9]{4}-[0-9]{2}-[0-9]{2}[T ][^,\s]+)/i', $excerpt, $matches) === 1) {
            try {
                return [
                    'reset_at' => \Carbon\CarbonImmutable::parse($matches[1], $timezone)->setTimezone('UTC'),
                    'timezone' => $timezone,
                ];
            } catch (\Throwable) {
                return null;
            }
        }

        if (preg_match('/resets?\s+(?:at\s+)?([0-9]{1,2})(?::([0-9]{2}))?\s*(am|pm)\b/i', $excerpt, $matches) === 1) {
            $hour = (int) $matches[1];
            $minute = isset($matches[2]) ? (int) $matches[2] : 0; // @phpstan-ignore isset.offset
            $meridiem = strtolower((string) $matches[3]);

            if ($hour < 1 || $hour > 12 || $minute < 0 || $minute > 59) {
                return null;
            }

            if ($meridiem === 'pm' && $hour !== 12) {
                $hour += 12;
            }
            if ($meridiem === 'am' && $hour === 12) {
                $hour = 0;
            }

            $nowTz = \Carbon\CarbonImmutable::now($timezone);
            $candidate = $nowTz->setTime($hour, $minute, 0);

            if ($candidate->lessThanOrEqualTo($nowTz)) {
                $candidate = $candidate->addDay();
            }

            return [
                'reset_at' => $candidate->setTimezone('UTC'),
                'timezone' => $timezone,
            ];
        }

        if (preg_match('/resets?\s+(?:at\s+)?([01]?[0-9]|2[0-3]):([0-5][0-9])\b/i', $excerpt, $matches) === 1) {
            $hour = (int) $matches[1];
            $minute = (int) $matches[2];

            $nowTz = \Carbon\CarbonImmutable::now($timezone);
            $candidate = $nowTz->setTime($hour, $minute, 0);

            if ($candidate->lessThanOrEqualTo($nowTz)) {
                $candidate = $candidate->addDay();
            }

            return [
                'reset_at' => $candidate->setTimezone('UTC'),
                'timezone' => $timezone,
            ];
        }

        return null;
    }

    public function normalizeClarificationExcerpt(string $excerpt): string
    {
        $normalized = trim($excerpt);
        if ($normalized === '') {
            return '';
        }

        $decoded = null;
        if (Str::startsWith($normalized, '{') || Str::startsWith($normalized, '[')) {
            $decoded = json_decode($normalized, true);
        }

        if (is_array($decoded)) {
            $candidate = $this->extractClarificationText($decoded);
            if ($candidate !== null) {
                $normalized = $candidate;
            }
        }

        if (preg_match('/\\\\n/', $normalized) === 1 && ! str_contains($normalized, "\n")) {
            $normalized = str_replace('\\n', "\n", $normalized);
        }

        return trim($normalized);
    }

    public function extractClarificationText(mixed $payload): ?string
    {
        if (is_string($payload)) {
            $value = trim($payload);

            return $value === '' ? null : $value;
        }

        if (! is_array($payload)) {
            return null;
        }

        foreach (['text', 'message', 'excerpt', 'question', 'prompt', 'detail', 'content'] as $key) {
            if (is_string($payload[$key] ?? null) && trim((string) $payload[$key]) !== '') {
                return trim((string) $payload[$key]);
            }
        }

        foreach (['item', 'payload', 'data', 'result', 'response', 'error'] as $key) {
            $candidate = $this->extractClarificationText($payload[$key] ?? null);
            if ($candidate !== null) {
                return $candidate;
            }
        }

        foreach ($payload as $key => $child) {
            if (in_array((string) $key, ['id', 'type', 'status', 'event_type', 'role'], true)) {
                continue;
            }

            $candidate = $this->extractClarificationText($child);
            if ($candidate !== null && preg_match('/\s/', $candidate) === 1) {
                return $candidate;
            }
        }

        return null;
    }

    // ── Private helpers ─────────────────────────────────────────────────

    private function isStructuredRateLimitErrorEvent(string $chunk): bool
    {
        $decoded = $this->decodeStructuredEvent($chunk);
        if (! is_array($decoded)) {
            return false;
        }

        $type = strtolower((string) ($decoded['type'] ?? ''));

        if (! in_array($type, ['error', 'turn.failed', 'result'], true)) {
            return false;
        }

        $blob = json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (! is_string($blob) || $blob === '') { // @phpstan-ignore identical.alwaysFalse
            return false;
        }

        return preg_match('/\brate[-\s]?limit(?:ed|ing|s)?\b|\btoo many requests\b|\bquota exceeded\b|\bretry[-\s]?after\b|\b429\b/i', $blob) === 1;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function decodeStructuredEvent(string $chunk): ?array
    {
        $trimmed = trim($chunk);
        if ($trimmed === '') {
            return null;
        }

        $decoded = json_decode($trimmed, true);

        if (is_array($decoded)) {
            return $decoded;
        }

        if (is_string($decoded)) {
            $nested = json_decode($decoded, true);
            if (is_array($nested)) {
                return $nested;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $decoded
     */
    public function containsToolResultContent(array $decoded): bool
    {
        $message = $decoded['message'] ?? null;
        if (! is_array($message)) {
            return false;
        }

        $content = $message['content'] ?? null;
        if (! is_array($content)) {
            return false;
        }

        foreach ($content as $block) {
            if (! is_array($block)) {
                continue;
            }

            if (strtolower((string) ($block['type'] ?? '')) === 'tool_result') {
                return true;
            }
        }

        return false;
    }

    public function extractReadableText(array $decoded): string
    {
        foreach (['text', 'message', 'content', 'detail', 'summary'] as $key) {
            if (is_string($decoded[$key] ?? null) && trim((string) $decoded[$key]) !== '') {
                return trim((string) $decoded[$key]);
            }
        }

        if (is_array($decoded['content'] ?? null)) {
            foreach ($decoded['content'] as $block) {
                if (is_array($block) && ($block['type'] ?? '') === 'text' && is_string($block['text'] ?? null)) {
                    return trim($block['text']);
                }
            }
        }

        $delta = $decoded['delta'] ?? null;
        if (is_array($delta) && is_string($delta['text'] ?? null) && trim($delta['text']) !== '') {
            return trim($delta['text']);
        }

        $event = $decoded['event'] ?? null;
        if (is_array($event)) {
            $nested = $this->extractReadableText($event);
            if ($nested !== '' && ! str_starts_with($nested, '{')) {
                return $nested;
            }
        }

        $msg = $decoded['message'] ?? null;
        if (is_array($msg)) {
            $nested = $this->extractReadableText($msg);
            if ($nested !== '' && ! str_starts_with($nested, '{')) {
                return $nested;
            }
        }

        $result = $decoded['result'] ?? null;
        if (is_array($result)) {
            $nested = $this->extractReadableText($result);
            if ($nested !== '' && ! str_starts_with($nested, '{')) {
                return $nested;
            }
        }

        if (isset($decoded['type'])) {
            return '';
        }

        return '';
    }

    private function isStructuredMachineEventWithoutAssistantIntent(string $chunk): bool
    {
        $decoded = $this->decodeStructuredEvent($chunk);
        if (! is_array($decoded)) {
            return false;
        }

        $type = strtolower((string) ($decoded['type'] ?? ''));

        if ($type === 'user' && $this->containsToolResultContent($decoded)) {
            return true;
        }

        if (in_array($type, ['thread.started', 'turn.started', 'turn.completed', 'thread.completed'], true)) {
            return true;
        }

        if ($type === 'item' || str_starts_with($type, 'item.')) {
            $itemType = strtolower((string) (($decoded['item']['type'] ?? null) ?? ''));

            return ! in_array($itemType, ['agent_message', 'assistant_message', 'message'], true);
        }

        return false;
    }

    private function isLineNumberedSnippet(string $chunk): bool
    {
        return preg_match(self::LINE_NUMBERED_SNIPPET_PATTERN, $chunk) === 1;
    }

    private function isInlineCodeSnippet(string $chunk): bool
    {
        if (preg_match('/\n|(?:\\\\)+n/', $chunk) !== 1) {
            return false;
        }

        $signalCount = 0;

        if (preg_match('/<\s*\/?\s*[A-Za-z][^>]*>/', $chunk) === 1) {
            $signalCount++;
        }

        if (preg_match('/\b(?:const|let|var|function|return|if|foreach|public|protected|private)\b/', $chunk) === 1) {
            $signalCount++;
        }

        if (preg_match('/=>|->|::|class=|v-if=|\$[A-Za-z_][A-Za-z0-9_]*/', $chunk) === 1) {
            $signalCount++;
        }

        if ($signalCount < 2) {
            return false;
        }

        return preg_match(self::INLINE_CODE_TOKENS_PATTERN, $chunk) === 1;
    }

    public function isStructuredStreamEvent(string $chunk): bool
    {
        $decoded = $this->decodeStructuredEvent($chunk);
        if (! is_array($decoded)) {
            return false;
        }

        $type = strtolower((string) ($decoded['type'] ?? ''));

        $nonErrorStreamTypes = [
            'stream_event', 'content_block_delta', 'content_block_start',
            'content_block_stop', 'message_start', 'message_delta',
            'message_stop', 'ping', 'input_json_delta',
        ];

        if (in_array($type, $nonErrorStreamTypes, true)) {
            return true;
        }

        $nestedType = strtolower((string) ($decoded['event']['type'] ?? ''));
        if ($nestedType !== '' && in_array($nestedType, $nonErrorStreamTypes, true)) {
            return true;
        }

        return false;
    }

    private function isMcpToolListDump(string $chunk): bool
    {
        $count = preg_match_all('/\bmcp__[a-zA-Z0-9_-]+__[a-zA-Z0-9_]+/', $chunk);

        return is_int($count) && $count >= 5;
    }

    private function isConfigNameListDump(string $chunk): bool
    {
        $count = preg_match_all('/[",]\s*"[a-z][a-z0-9]*(?:-[a-z0-9]+)+"/', $chunk);

        return is_int($count) && $count >= 8;
    }

    private function isStreamJsonMetadataFragment(string $chunk): bool
    {
        $hasSessionId = preg_match('/"session_id"\s*:\s*"[0-9a-f-]{36}"/', $chunk) === 1;
        $hasUuid = preg_match('/"uuid"\s*:\s*"[0-9a-f-]{36}"/', $chunk) === 1;
        $hasParentToolUse = preg_match('/"parent_tool_use_id"\s*:/', $chunk) === 1;

        if (! $hasSessionId && ! $hasUuid && ! $hasParentToolUse) {
            return false;
        }

        $metadataMarkerCount = (int) $hasSessionId + (int) $hasUuid + (int) $hasParentToolUse;
        if ($metadataMarkerCount < 2) {
            return false;
        }

        $stripped = (string) preg_replace('/["{}\[\]:,\s]|[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/', '', $chunk);

        return strlen($stripped) < 120;
    }

    private function isToolResultMetadataEcho(string $chunk): bool
    {
        $hasNumLines = preg_match('/"numLines"\s*:\s*\d+/', $chunk) === 1;
        $hasStartLine = preg_match('/"startLine"\s*:\s*\d+/', $chunk) === 1;
        $hasTotalLines = preg_match('/"totalLines"\s*:\s*\d+/', $chunk) === 1;

        return ((int) $hasNumLines + (int) $hasStartLine + (int) $hasTotalLines) >= 2;
    }

    private function extractTimezoneFromExcerpt(string $excerpt): ?string
    {
        if (preg_match('/\(([A-Za-z_]+\/[A-Za-z_]+)\)/', $excerpt, $matches) === 1) {
            return $matches[1];
        }

        return null;
    }
}
