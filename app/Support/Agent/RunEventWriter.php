<?php

declare(strict_types=1);

namespace App\Support\Agent;

use App\Models\AgentJobRun;
use App\Models\AgentRunEvent;
use Carbon\CarbonImmutable;

class RunEventWriter
{
    private const OUTPUT_CAP_BYTES = 5_000_000;

    private const CHUNK_BYTES = 4096;

    private const MAX_PAYLOAD_BYTES = 8192;

    public const RATE_LIMIT_PATTERN = EventPatternMatcher::RATE_LIMIT_PATTERN;

    private int $nextSequence;

    private int $consecutiveWriteFailures = 0;

    private int $recentWriteFailures = 0;

    private ?int $failureWindowStartedAtMs = null;

    private bool $captureHalted = false;

    private bool $redactionNoticeEmitted = false;

    private bool $noiseNoticeEmitted = false;

    private EventPatternMatcher $patternMatcher;

    private OutputRedactor $redactor;

    private EventBroadcaster $broadcaster;

    public function __construct(private AgentJobRun $run)
    {
        $this->nextSequence = (int) (AgentRunEvent::query()
            ->where('agent_job_run_id', $this->run->id)
            ->max('sequence') ?? 0) + 1;

        $this->patternMatcher = new EventPatternMatcher;
        $this->redactor = new OutputRedactor;
        $this->broadcaster = new EventBroadcaster($this->run, $this->patternMatcher);
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

        $this->broadcaster->dispatchMemoryBuffer($eventType, $rawPayload);
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

    /**
     * Post-hoc scan of the tail output events for a *failed* run.
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

            $decoded = json_decode($payload, true);
            if (is_array($decoded) && is_string($decoded['text'] ?? null)) {
                $payload = $decoded['text'];
            }

            if (! $this->patternMatcher->matchesRateLimitPattern($payload)) {
                continue;
            }

            $now = CarbonImmutable::now('UTC');
            $excerpt = substr(trim($payload), 0, 1000);

            $result = [
                'rate_limit_detected' => true,
                'rate_limit_detected_at' => $now->toIso8601String(),
                'rate_limit_excerpt' => $excerpt,
            ];

            $fallbackTimezone = is_string($this->run->job?->timezone) ? $this->run->job->timezone : null;
            $reset = $this->patternMatcher->extractRateLimitReset($excerpt, $fallbackTimezone);
            if ($reset !== null) {
                $result['rate_limit_reset_at'] = $reset['reset_at']->toIso8601String();
                $result['rate_limit_reset_timezone'] = $reset['timezone'];
            }

            return $result;
        }

        return null;
    }

    // ── Private orchestration ───────────────────────────────────────

    private function appendChunk(string $eventType, string $chunk, ?string $reasoningStep = null): void
    {
        if ($this->captureHalted) {
            $this->incrementTruncateBytes($eventType, strlen($chunk));

            return;
        }

        if ($this->redactor->isBinaryChunk($chunk)) {
            $chunk = sprintf('[binary output omitted: %d bytes]', strlen($chunk));
        } elseif (function_exists('mb_check_encoding') && ! mb_check_encoding($chunk, 'UTF-8')) {
            $chunk = mb_convert_encoding($chunk, 'UTF-8', 'UTF-8');
        }

        $preBytes = strlen($chunk);
        $redactionCount = 0;
        $chunk = $this->redactor->redact($chunk, $redactionCount);
        $postBytes = strlen($chunk);

        $this->incrementByteCounters($eventType, $preBytes, $postBytes);

        $metadata = (array) ($this->run->metadata_json ?? []);
        $metadata['redaction_count'] = (int) ($metadata['redaction_count'] ?? 0) + $redactionCount;

        $this->run->metadata_json = $metadata;

        if ($this->patternMatcher->shouldSuppressAsNoise($chunk)) {
            $this->trackNoiseSuppression($eventType, $postBytes);

            return;
        }

        $mcpEndpoints = $this->patternMatcher->extractMcpUnavailableEndpoints($chunk);
        foreach ($mcpEndpoints as $mcpEndpoint) {
            $this->markMcpServerUnavailable($mcpEndpoint, $chunk);
        }

        $isNonRuntimeSnippet = $this->patternMatcher->isLikelyNonRuntimeSnippet($chunk);

        if (! $isNonRuntimeSnippet
            && ($eventType === 'stdout' || $eventType === 'stderr')
            && $this->patternMatcher->shouldMarkApprovalRequired($chunk)) {
            $this->markApprovalRequired($chunk);
        }

        if (! $isNonRuntimeSnippet
            && ($eventType === 'stdout' || $eventType === 'stderr')
            && $this->patternMatcher->matchesPermissionBlockerPattern($chunk)) {
            $this->markPermissionBlockerDetected($chunk);
        }

        if (! $isNonRuntimeSnippet
            && ($eventType === 'stdout' || $eventType === 'stderr')
            && $this->patternMatcher->matchesClarificationPattern($chunk)) {
            $this->markClarificationRequired($chunk);
        }

        if (! $isNonRuntimeSnippet
            && ($eventType === 'stdout' || $eventType === 'stderr')
            && $this->patternMatcher->shouldMarkRateLimitDetected($chunk)) {
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

        $this->broadcaster->broadcastEventsAvailable($sequence);
    }

    // ── Metadata markers ────────────────────────────────────────────

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

        $this->broadcaster->broadcastEscalation('approval_required', 'Agent needs approval to continue');
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

        $this->broadcaster->broadcastEscalation('permission_blocked', 'Agent blocked by file permission');
    }

    private function markClarificationRequired(string $excerpt): void
    {
        $metadata = (array) ($this->run->metadata_json ?? []);

        if (($metadata['clarification_required'] ?? false) === true) {
            return;
        }

        $normalizedExcerpt = $this->patternMatcher->normalizeClarificationExcerpt($excerpt);
        $now = CarbonImmutable::now('UTC')->toIso8601String();
        $metadata['clarification_required'] = true;
        $metadata['clarification_detected_at'] = $now;
        $metadata['clarification_excerpt'] = substr($normalizedExcerpt, 0, 1000);
        $this->run->metadata_json = $metadata;

        $this->appendLifecycle([
            'type' => 'clarification_requested',
            'at' => $now,
        ]);

        $this->broadcaster->broadcastEscalation('clarification_required', 'Agent needs clarification to continue');
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

        $fallbackTimezone = is_string($this->run->job?->timezone) ? $this->run->job->timezone : null;
        $reset = $this->patternMatcher->extractRateLimitReset($excerpt, $fallbackTimezone);

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

        $this->broadcaster->broadcastEscalation('rate_limit_detected', 'Agent hit an upstream rate limit');
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

    // ── Byte accounting ─────────────────────────────────────────────

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
