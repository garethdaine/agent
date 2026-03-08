<?php

declare(strict_types=1);

namespace App\Support\Agent;

use App\Events\Office\AgentActivityChanged;
use App\Events\RunEventsAvailable;
use App\Jobs\Memory\MemoryWorkingBufferJob;
use App\Models\AgentJobRun;
use App\Models\AgentRunEvent;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class RunEventWriter
{
    private const OUTPUT_CAP_BYTES = 5_000_000;

    private const CHUNK_BYTES = 4096;

    private const MAX_PAYLOAD_BYTES = 8192;

    private int $nextSequence;

    private int $consecutiveWriteFailures = 0;

    private int $recentWriteFailures = 0;

    private ?int $failureWindowStartedAtMs = null;

    private bool $captureHalted = false;

    private bool $redactionNoticeEmitted = false;

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

    public function __construct(private AgentJobRun $run)
    {
        $this->nextSequence = (int) (AgentRunEvent::query()
            ->where('agent_job_run_id', $this->run->id)
            ->max('sequence') ?? 0) + 1;
    }

    public function appendOutput(string $eventType, string $rawPayload, ?string $reasoningStep = null): void
    {
        if ($rawPayload === '') {
            return;
        }

        $chunks = $this->chunkString($rawPayload, self::CHUNK_BYTES);

        foreach ($chunks as $chunk) {
            $this->appendChunk($eventType, $chunk, $reasoningStep);
        }

        $this->persistRunStats();

        // Memory integration: buffer to Working Memory (fire-and-forget)
        if (app(FeatureFlagManager::class)->enabled(FeatureFlagManager::MEMORY_ENABLED)) {
            try {
                MemoryWorkingBufferJob::dispatch(
                    $this->run->id,
                    $eventType,
                    $rawPayload
                )->onQueue('memory-working');
            } catch (\Throwable $e) {
                // Silent failure - never block the 250ms poll loop
            }
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function appendLifecycle(array $payload): void
    {
        $this->tryWrite(function () use ($payload): void {
            $this->createEvent('lifecycle', json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}');
        });
    }

    private function appendChunk(string $eventType, string $chunk, ?string $reasoningStep = null): void
    {
        if ($this->captureHalted) {
            $this->incrementTruncateBytes($eventType, strlen($chunk));

            return;
        }

        if ($this->isBinaryChunk($chunk)) {
            $chunk = sprintf('[binary output omitted: %d bytes]', strlen($chunk));
        } elseif (function_exists('mb_check_encoding') && ! mb_check_encoding($chunk, 'UTF-8')) {
            $chunk = mb_convert_encoding($chunk, 'UTF-8', 'UTF-8');
        }

        $preBytes = strlen($chunk);
        $redactionCount = 0;
        $chunk = $this->redact($chunk, $redactionCount);
        $postBytes = strlen($chunk);

        $this->incrementByteCounters($eventType, $preBytes, $postBytes);

        $metadata = (array) ($this->run->metadata_json ?? []);
        $metadata['redaction_count'] = (int) ($metadata['redaction_count'] ?? 0) + $redactionCount;

        $this->run->metadata_json = $metadata;

        if ($this->shouldSuppressAsNoise($chunk)) {
            $this->trackNoiseSuppression($eventType, $postBytes);

            return;
        }

        $mcpEndpoints = $this->extractMcpUnavailableEndpoints($chunk);
        foreach ($mcpEndpoints as $mcpEndpoint) {
            $this->markMcpServerUnavailable($mcpEndpoint, $chunk);
        }

        $isNonRuntimeSnippet = $this->isLikelyNonRuntimeSnippet($chunk);

        if (! $isNonRuntimeSnippet
            && ($eventType === 'stdout' || $eventType === 'stderr')
            && $this->shouldMarkApprovalRequired($chunk)) {
            $this->markApprovalRequired($chunk);
        }

        if (! $isNonRuntimeSnippet
            && ($eventType === 'stdout' || $eventType === 'stderr')
            && preg_match(self::PERMISSION_BLOCKER_PATTERN, $chunk) === 1) {
            $this->markPermissionBlockerDetected($chunk);
        }

        if (! $isNonRuntimeSnippet
            && ($eventType === 'stdout' || $eventType === 'stderr')
            && preg_match(self::CLARIFICATION_PATTERN, $chunk) === 1) {
            $this->markClarificationRequired($chunk);
        }

        if (! $isNonRuntimeSnippet
            && ($eventType === 'stdout' || $eventType === 'stderr')
            && $this->shouldMarkRateLimitDetected($chunk)) {
            $this->markRateLimitDetected($chunk);
        }

        if ($redactionCount > 0 && ! $this->redactionNoticeEmitted) {
            $this->redactionNoticeEmitted = true;
            $this->appendLifecycle([
                'type' => 'redaction_notice',
                'replaced_segments' => $redactionCount,
            ]);
        }

        $pieces = $this->chunkString($chunk, self::MAX_PAYLOAD_BYTES);

        foreach ($pieces as $index => $piece) {
            if ($this->wouldExceedOutputCap(strlen($piece))) {
                $this->captureHalted = true;
                $this->incrementTruncateBytes($eventType, strlen($piece));

                $metadata = (array) ($this->run->metadata_json ?? []);
                $metadata['output_truncated'] = true;
                $this->run->metadata_json = $metadata;

                $this->appendLifecycle([
                    'type' => 'truncation_notice',
                    'message' => 'Output capture halted after hitting 5MB persisted limit.',
                ]);

                return;
            }

            $payload = $piece;

            if (count($pieces) > 1) {
                $payload = json_encode([
                    'text' => $piece,
                    'continuation' => $index > 0,
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: $piece;
            }

            $this->tryWrite(function () use ($eventType, $payload, $reasoningStep): void {
                $this->createEvent($eventType, $payload, $reasoningStep);
            });
        }
    }

    private function tryWrite(callable $callback): void
    {
        try {
            $callback();

            $this->consecutiveWriteFailures = 0;

            if ($this->failureWindowStartedAtMs === null || (int) floor(microtime(true) * 1000) - $this->failureWindowStartedAtMs > 30_000) {
                $this->failureWindowStartedAtMs = (int) floor(microtime(true) * 1000);
                $this->recentWriteFailures = 0;
            }
        } catch (\Throwable $throwable) {
            $this->consecutiveWriteFailures++;
            $this->recentWriteFailures++;

            if ($this->failureWindowStartedAtMs === null) {
                $this->failureWindowStartedAtMs = (int) floor(microtime(true) * 1000);
            }

            $nowMs = (int) floor(microtime(true) * 1000);
            $windowAge = $nowMs - $this->failureWindowStartedAtMs;

            if ($windowAge > 30_000) {
                $this->failureWindowStartedAtMs = $nowMs;
                $this->recentWriteFailures = 1;
            }

            if ($this->consecutiveWriteFailures >= 5 || $this->recentWriteFailures >= 10) {
                throw $throwable;
            }
        }
    }

    private function createEvent(string $eventType, string $payload, ?string $reasoningStep = null): void
    {
        $sequence = $this->nextSequence++;

        AgentRunEvent::query()->create([
            'agent_job_run_id' => $this->run->id,
            'event_type' => $eventType,
            'sequence' => $sequence,
            'payload' => $payload,
            'reasoning_step' => $reasoningStep,
            'event_ts' => CarbonImmutable::now('UTC'),
        ]);

        $this->broadcastEventsAvailable($sequence);
    }

    private function broadcastEventsAvailable(int $sequence): void
    {
        $cacheKey = 'run_events_broadcast:'.$this->run->id;

        if (Cache::has($cacheKey)) {
            return;
        }

        Cache::put($cacheKey, true, 2);

        try {
            RunEventsAvailable::dispatch($this->run->id, $sequence);
        } catch (\Throwable) {
            // Never block the event write loop
        }

        $this->broadcastOutputSnippetToOffice($sequence);
    }

    private function broadcastOutputSnippetToOffice(int $sequence): void
    {
        $officeCacheKey = 'office_output_broadcast:'.$this->run->id;
        if (Cache::has($officeCacheKey)) {
            return;
        }

        Cache::put($officeCacheKey, true, 4);

        $snippet = $this->extractMeaningfulSnippet($sequence);
        if ($snippet === null) {
            return;
        }

        try {
            AgentActivityChanged::dispatch(
                (int) $this->run->user_id,
                'agent.output',
                [
                    'run_id' => $this->run->id,
                    'text' => $snippet,
                    'sequence' => $sequence,
                ],
            );
        } catch (\Throwable) {
            // Best-effort
        }
    }

    private function broadcastEscalation(string $reason, string $summary): void
    {
        try {
            AgentActivityChanged::dispatch(
                (int) $this->run->user_id,
                'agent.escalation',
                [
                    'run_id' => $this->run->id,
                    'job_name' => $this->run->job->name ?? 'Unknown',
                    'reason' => $reason,
                    'summary' => $summary,
                ],
            );
        } catch (\Throwable) {
            // Best-effort
        }
    }

    private function extractMeaningfulSnippet(int $upToSequence): ?string
    {
        $event = AgentRunEvent::query()
            ->where('agent_job_run_id', $this->run->id)
            ->where('event_type', 'stdout')
            ->where('sequence', '<=', $upToSequence)
            ->orderByDesc('sequence')
            ->first();

        if ($event === null) {
            return null;
        }

        $raw = $event->payload ?? '';

        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $raw = $this->extractReadableText($decoded);
        }

        $raw = trim(preg_replace('/\s+/', ' ', $raw) ?? $raw);

        // If extraction still yielded JSON-like text, suppress it
        if (str_starts_with($raw, '{') || str_starts_with($raw, '[') || str_starts_with($raw, '"type"')) {
            return null;
        }

        if (strlen($raw) < 5 || $this->isLikelyNonRuntimeSnippet($raw)) {
            return null;
        }

        return Str::limit($raw, 60);
    }

    private function extractReadableText(array $decoded): string
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

        // Claude stream-json: {"type":"content_block_delta","delta":{"type":"text_delta","text":"..."}}
        $delta = $decoded['delta'] ?? null;
        if (is_array($delta) && is_string($delta['text'] ?? null) && trim($delta['text']) !== '') {
            return trim($delta['text']);
        }

        // Claude stream-json: {"type":"stream_event","event":{...nested...}}
        $event = $decoded['event'] ?? null;
        if (is_array($event)) {
            $nested = $this->extractReadableText($event);
            if ($nested !== '' && ! str_starts_with($nested, '{')) {
                return $nested;
            }
        }

        // Claude stream-json: {"type":"message_start","message":{"content":[...]}}
        $msg = $decoded['message'] ?? null;
        if (is_array($msg)) {
            $nested = $this->extractReadableText($msg);
            if ($nested !== '' && ! str_starts_with($nested, '{')) {
                return $nested;
            }
        }

        // Claude stream-json: {"result":{"text":"..."}} or {"result":{...content...}}
        $result = $decoded['result'] ?? null;
        if (is_array($result)) {
            $nested = $this->extractReadableText($result);
            if ($nested !== '' && ! str_starts_with($nested, '{')) {
                return $nested;
            }
        }

        // If the structure has a 'type' key but no extractable text, it's a
        // machine event (message_start, ping, etc.) -- suppress it entirely.
        if (isset($decoded['type'])) {
            return '';
        }

        return '';
    }

    private function redact(string $payload, int &$redactionCount): string
    {
        $patterns = [
            '/-----BEGIN [A-Z ]*PRIVATE KEY-----[\s\S]*?-----END [A-Z ]*PRIVATE KEY-----/m' => '[REDACTED_PRIVATE_KEY]',
            '/\bBearer\s+[A-Za-z0-9._\-]+\b/i' => '[REDACTED_BEARER_TOKEN]',
            '/\bsk-[a-zA-Z0-9]{20,}\b/' => '[REDACTED_API_KEY]',
            '/\bAKIA[A-Z0-9]{16}\b/' => '[REDACTED_AWS_KEY]',
            '/\b(?:api[_-]?key|apikey)\s*[:=]\s*[^\s,;]+/i' => '[REDACTED_API_KEY]',
            '/\b(?:password|passwd|pwd)\s*[:=]\s*[^\s,;]+/i' => '[REDACTED_PASSWORD]',
            '/\b(?:secret|token|credential)\s*[:=]\s*[^\s,;]+/i' => '[REDACTED]',
            '/[\w.\-+]+@[\w.\-]+\.[a-zA-Z]{2,}/' => '[REDACTED_EMAIL]',
        ];

        foreach ($patterns as $pattern => $replacement) {
            $payload = preg_replace_callback($pattern, function () use ($replacement, &$redactionCount) {
                $redactionCount++;

                return $replacement;
            }, $payload) ?? $payload;
        }

        return $payload;
    }

    private function isBinaryChunk(string $chunk): bool
    {
        $sample = substr($chunk, 0, 1024);
        $length = strlen($sample);

        if ($length === 0) {
            return false;
        }

        $nonPrintable = preg_match_all('/[^\x09\x0A\x0D\x20-\x7E]/', $sample);

        return is_int($nonPrintable) && ($nonPrintable / $length) > 0.30;
    }

    /**
     * @return array<int, string>
     */
    private function chunkString(string $text, int $chunkSize): array
    {
        $chunks = [];
        $length = strlen($text);

        for ($offset = 0; $offset < $length; $offset += $chunkSize) {
            $chunks[] = substr($text, $offset, $chunkSize);
        }

        return $chunks;
    }

    private function incrementByteCounters(string $eventType, int $preBytes, int $postBytes): void
    {
        if ($eventType === 'stdout') {
            $this->run->stdout_bytes_pre = (int) $this->run->stdout_bytes_pre + $preBytes;
            $this->run->stdout_bytes_post = (int) $this->run->stdout_bytes_post + $postBytes;

            return;
        }

        $this->run->stderr_bytes_pre = (int) $this->run->stderr_bytes_pre + $preBytes;
        $this->run->stderr_bytes_post = (int) $this->run->stderr_bytes_post + $postBytes;
    }

    private function wouldExceedOutputCap(int $nextBytes): bool
    {
        return $this->currentPersistedBytes() + $nextBytes > self::OUTPUT_CAP_BYTES;
    }

    private function currentPersistedBytes(): int
    {
        return (int) $this->run->stdout_bytes_post + (int) $this->run->stderr_bytes_post;
    }

    private function incrementTruncateBytes(string $eventType, int $bytes): void
    {
        $metadata = (array) ($this->run->metadata_json ?? []);
        $metadata['truncate_bytes'] = (int) ($metadata['truncate_bytes'] ?? 0) + $bytes;
        $metadata['output_truncated'] = true;
        $this->run->metadata_json = $metadata;

        $this->incrementByteCounters($eventType, $bytes, 0);
        $this->persistRunStats();
    }

    private function persistRunStats(): void
    {
        $this->run->save();
    }

    private function markApprovalRequired(string $excerpt): void
    {
        $metadata = (array) ($this->run->metadata_json ?? []);

        if (($metadata['approval_required'] ?? false) === true) {
            return;
        }

        $metadata['approval_required'] = true;
        $metadata['approval_detected_at'] = CarbonImmutable::now('UTC')->toIso8601String();
        $metadata['approval_excerpt'] = substr(trim($excerpt), 0, 1000);

        $this->run->metadata_json = $metadata;

        $this->broadcastEscalation('approval_required', 'Agent needs approval to continue');
    }

    private function markPermissionBlockerDetected(string $excerpt): void
    {
        $metadata = (array) ($this->run->metadata_json ?? []);

        if (($metadata['permission_blocker_detected'] ?? false) === true) {
            return;
        }

        $now = CarbonImmutable::now('UTC')->toIso8601String();
        $metadata['permission_blocker_detected'] = true;
        $metadata['permission_blocker_detected_at'] = $now;
        $metadata['permission_blocker_excerpt'] = substr(trim($excerpt), 0, 1000);
        $this->run->metadata_json = $metadata;

        $this->appendLifecycle([
            'type' => 'permission_blocker_detected',
            'at' => $now,
        ]);

        $this->broadcastEscalation('permission_blocked', 'Agent blocked by file permission');
    }

    private function markClarificationRequired(string $excerpt): void
    {
        $metadata = (array) ($this->run->metadata_json ?? []);

        if (($metadata['clarification_required'] ?? false) === true) {
            return;
        }

        $normalizedExcerpt = $this->normalizeClarificationExcerpt($excerpt);
        $now = CarbonImmutable::now('UTC')->toIso8601String();
        $metadata['clarification_required'] = true;
        $metadata['clarification_detected_at'] = $now;
        $metadata['clarification_excerpt'] = substr($normalizedExcerpt, 0, 1000);
        $this->run->metadata_json = $metadata;

        $this->appendLifecycle([
            'type' => 'clarification_requested',
            'at' => $now,
        ]);

        $this->broadcastEscalation('clarification_required', 'Agent needs clarification to continue');
    }

    private function normalizeClarificationExcerpt(string $excerpt): string
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

    private function extractClarificationText(mixed $payload): ?string
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

    private function markRateLimitDetected(string $excerpt): void
    {
        $metadata = (array) ($this->run->metadata_json ?? []);

        if (($metadata['rate_limit_detected'] ?? false) === true) {
            return;
        }

        $metadata['rate_limit_detected'] = true;
        $metadata['rate_limit_detected_at'] = CarbonImmutable::now('UTC')->toIso8601String();
        $metadata['rate_limit_excerpt'] = substr(trim($excerpt), 0, 1000);

        $reset = $this->extractRateLimitReset($excerpt);

        if ($reset !== null) {
            $metadata['rate_limit_reset_at'] = $reset['reset_at']->toIso8601String();
            $metadata['rate_limit_reset_timezone'] = $reset['timezone'];
        }

        $this->run->metadata_json = $metadata;

        $payload = [
            'type' => 'rate_limit_detected',
            'at' => CarbonImmutable::now('UTC')->toIso8601String(),
        ];

        if (isset($metadata['rate_limit_reset_at'])) {
            $payload['reset_at'] = $metadata['rate_limit_reset_at'];
            $payload['timezone'] = $metadata['rate_limit_reset_timezone'] ?? 'UTC';
        }

        $this->appendLifecycle($payload);

        $this->broadcastEscalation('rate_limit_detected', 'Agent hit an upstream rate limit');
    }

    private function markMcpServerUnavailable(string $endpoint, string $excerpt): void
    {
        $metadata = (array) ($this->run->metadata_json ?? []);
        $issues = is_array($metadata['issues'] ?? null) ? $metadata['issues'] : [];
        $existing = is_array($issues['mcp_server_unavailable'] ?? null) ? $issues['mcp_server_unavailable'] : [];
        $count = max(0, (int) ($existing['count'] ?? 0)) + 1;
        $now = CarbonImmutable::now('UTC')->toIso8601String();

        $issues['mcp_server_unavailable'] = [
            'code' => 'MCP_SERVER_UNAVAILABLE',
            'title' => 'MCP server unavailable',
            'detail' => sprintf('Could not connect to %s (connection refused).', $endpoint),
            'suggested_action' => 'Start/restart the MCP server on port 3333 or update MCP endpoint config.',
            'endpoint' => $endpoint,
            'count' => $count,
            'last_detected_at' => $now,
            'first_detected_at' => is_string($existing['first_detected_at'] ?? null)
                ? (string) $existing['first_detected_at']
                : $now,
            'excerpt' => substr(trim($excerpt), 0, 1000),
        ];

        $metadata['issues'] = $issues;
        $metadata['mcp_connection_refused_detected'] = true;
        $this->run->metadata_json = $metadata;

        if ($count === 1) {
            $this->appendLifecycle([
                'type' => 'mcp_server_unavailable',
                'at' => $now,
                'endpoint' => $endpoint,
            ]);
        }
    }

    private function shouldMarkApprovalRequired(string $chunk): bool
    {
        if (preg_match(self::APPROVAL_PATTERN, $chunk) !== 1) {
            return false;
        }

        if (preg_match(self::APPROVAL_FALSE_POSITIVE_PATTERN, $chunk) === 1) {
            return false;
        }

        return true;
    }

    /**
     * During active execution, only detect rate limits from structured error
     * events (machine-generated JSON with type "error" / "turn.failed").
     *
     * Plain-text output is NOT scanned here because the agent frequently
     * writes code and documentation that mentions rate-limiting terminology.
     * Plain-text detection is deferred to {@see scanRecentOutputForRateLimit()}
     * which runs only after a failed process exit — the only time plain-text
     * patterns are a reliable signal.
     */
    private function shouldMarkRateLimitDetected(string $chunk): bool
    {
        return $this->isStructuredRateLimitErrorEvent($chunk);
    }

    /**
     * Post-hoc scan of the tail output events for a *failed* run.
     *
     * Call this at run finalization when the process exited non-zero and
     * no structured error event already set the rate-limit flag.  Because
     * the process actually died, plain-text rate-limit phrases in the final
     * output are a reliable signal rather than noise from code/docs.
     *
     * @return array<string, mixed>|null Metadata entries to merge, or null.
     */
    public function scanRecentOutputForRateLimit(): ?array
    {
        $events = AgentRunEvent::query()
            ->where('agent_job_run_id', $this->run->id)
            ->whereIn('event_type', ['stdout', 'stderr'])
            ->orderByDesc('sequence')
            ->limit(10)
            ->get();

        foreach ($events as $event) {
            $payload = $event->payload ?? '';

            // Expand JSON-wrapped text chunks produced by the chunker.
            $decoded = json_decode($payload, true);
            if (is_array($decoded) && is_string($decoded['text'] ?? null)) {
                $payload = $decoded['text'];
            }

            if (preg_match(self::RATE_LIMIT_PATTERN, $payload) !== 1) {
                continue;
            }

            $now = CarbonImmutable::now('UTC');
            $excerpt = substr(trim($payload), 0, 1000);

            $result = [
                'rate_limit_detected' => true,
                'rate_limit_detected_at' => $now->toIso8601String(),
                'rate_limit_excerpt' => $excerpt,
            ];

            $reset = $this->extractRateLimitReset($excerpt);
            if ($reset !== null) {
                $result['rate_limit_reset_at'] = $reset['reset_at']->toIso8601String();
                $result['rate_limit_reset_timezone'] = $reset['timezone'];
            }

            return $result;
        }

        return null;
    }

    private function isStructuredStreamEvent(string $chunk): bool // @phpstan-ignore method.unused
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

    /**
     * @return array<int, string>
     */
    private function extractMcpUnavailableEndpoints(string $chunk): array
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

    private function isLineNumberedSnippet(string $chunk): bool
    {
        return preg_match(self::LINE_NUMBERED_SNIPPET_PATTERN, $chunk) === 1;
    }

    private function isLikelyNonRuntimeSnippet(string $chunk): bool
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

    private function isStructuredMachineEventWithoutAssistantIntent(string $chunk): bool
    {
        $decoded = $this->decodeStructuredEvent($chunk);
        if (! is_array($decoded)) {
            return false;
        }

        $type = strtolower((string) ($decoded['type'] ?? ''));

        // Claude stream-json user events often contain tool_result payloads that echo source snippets.
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
    private function decodeStructuredEvent(string $chunk): ?array
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
    private function containsToolResultContent(array $decoded): bool
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

    /**
     * @return array{reset_at:CarbonImmutable,timezone:string}|null
     */
    private function extractRateLimitReset(string $excerpt): ?array
    {
        $timezone = $this->extractTimezoneFromExcerpt($excerpt)
            ?? (is_string($this->run->job?->timezone) ? $this->run->job->timezone : null)
            ?? 'UTC';

        if (! in_array($timezone, timezone_identifiers_list(), true)) {
            $timezone = 'UTC';
        }

        if (preg_match('/resets?\s+(?:at\s+)?([0-9]{4}-[0-9]{2}-[0-9]{2}[T ][^,\s]+)/i', $excerpt, $matches) === 1) {
            try {
                return [
                    'reset_at' => CarbonImmutable::parse($matches[1], $timezone)->setTimezone('UTC'),
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

            $nowTz = CarbonImmutable::now($timezone);
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

            $nowTz = CarbonImmutable::now($timezone);
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

    private function extractTimezoneFromExcerpt(string $excerpt): ?string
    {
        if (preg_match('/\(([A-Za-z_]+\/[A-Za-z_]+)\)/', $excerpt, $matches) === 1) {
            return $matches[1];
        }

        return null;
    }

    // ── Noise suppression ────────────────────────────────────────────

    private bool $noiseNoticeEmitted = false;

    private function shouldSuppressAsNoise(string $chunk): bool
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
     * Detects chunks containing ≥5 MCP tool name identifiers (mcp__server__tool pattern).
     * These are tool definition list echoes from the system prompt.
     */
    private function isMcpToolListDump(string $chunk): bool
    {
        $count = preg_match_all('/\bmcp__[a-zA-Z0-9_-]+__[a-zA-Z0-9_]+/', $chunk);

        return is_int($count) && $count >= 5;
    }

    /**
     * Detects chunks containing ≥8 comma-separated quoted lowercase-hyphenated identifiers.
     * These are skill/command name list echoes from system prompt configuration.
     */
    private function isConfigNameListDump(string $chunk): bool
    {
        $count = preg_match_all('/[",]\s*"[a-z][a-z0-9]*(?:-[a-z0-9]+)+"/', $chunk);

        return is_int($count) && $count >= 8;
    }

    /**
     * Detects stream-json metadata fragments containing session_id/uuid/parent_tool_use_id
     * with no substantial human-readable content.
     */
    private function isStreamJsonMetadataFragment(string $chunk): bool
    {
        $hasSessionId = preg_match('/"session_id"\s*:\s*"[0-9a-f-]{36}"/', $chunk) === 1;
        $hasUuid = preg_match('/"uuid"\s*:\s*"[0-9a-f-]{36}"/', $chunk) === 1;
        $hasParentToolUse = preg_match('/"parent_tool_use_id"\s*:/', $chunk) === 1;

        if (! $hasSessionId && ! $hasUuid && ! $hasParentToolUse) {
            return false;
        }

        // Only suppress when the chunk has at least two metadata markers
        // and lacks substantial readable text (prevents suppressing agent output
        // that happens to mention a session ID).
        $metadataMarkerCount = (int) $hasSessionId + (int) $hasUuid + (int) $hasParentToolUse;
        if ($metadataMarkerCount < 2) {
            return false;
        }

        // Strip JSON structural chars and hex sequences — what remains is "readable text".
        $stripped = (string) preg_replace('/["{}\[\]:,\s]|[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/', '', $chunk);

        return strlen($stripped) < 120;
    }

    /**
     * Detects tool result content echo fragments containing Read/Glob output metadata markers
     * like "numLines", "startLine", "totalLines" from the Claude CLI stream-json format.
     */
    private function isToolResultMetadataEcho(string $chunk): bool
    {
        $hasNumLines = preg_match('/"numLines"\s*:\s*\d+/', $chunk) === 1;
        $hasStartLine = preg_match('/"startLine"\s*:\s*\d+/', $chunk) === 1;
        $hasTotalLines = preg_match('/"totalLines"\s*:\s*\d+/', $chunk) === 1;

        // Require at least two of three markers to avoid false positives.
        return ((int) $hasNumLines + (int) $hasStartLine + (int) $hasTotalLines) >= 2;
    }

    private function trackNoiseSuppression(string $eventType, int $bytes): void
    {
        $this->incrementByteCounters($eventType, $bytes, 0);

        $metadata = (array) ($this->run->metadata_json ?? []);
        $metadata['noise_suppressed_chunks'] = (int) ($metadata['noise_suppressed_chunks'] ?? 0) + 1;
        $metadata['noise_suppressed_bytes'] = (int) ($metadata['noise_suppressed_bytes'] ?? 0) + $bytes;
        $this->run->metadata_json = $metadata;

        if (! $this->noiseNoticeEmitted) {
            $this->noiseNoticeEmitted = true;
            $this->appendLifecycle([
                'type' => 'noise_filtering_active',
                'at' => CarbonImmutable::now('UTC')->toIso8601String(),
            ]);
        }
    }
}
