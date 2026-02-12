<?php

namespace App\Support\Agent;

use App\Models\AgentJobRun;
use App\Models\AgentRunEvent;
use Carbon\CarbonImmutable;

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

    private const APPROVAL_PATTERN = '/need permission|requires permission|could you approve|approval/i';

    public function __construct(private AgentJobRun $run)
    {
        $this->nextSequence = (int) (AgentRunEvent::query()
            ->where('agent_job_run_id', $this->run->id)
            ->max('sequence') ?? 0) + 1;
    }

    public function appendOutput(string $eventType, string $rawPayload): void
    {
        if ($rawPayload === '') {
            return;
        }

        $chunks = $this->chunkString($rawPayload, self::CHUNK_BYTES);

        foreach ($chunks as $chunk) {
            $this->appendChunk($eventType, $chunk);
        }

        $this->persistRunStats();
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

    private function appendChunk(string $eventType, string $chunk): void
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

        if (($eventType === 'stdout' || $eventType === 'stderr')
            && preg_match(self::APPROVAL_PATTERN, $chunk) === 1) {
            $this->markApprovalRequired($chunk);
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

            $this->tryWrite(function () use ($eventType, $payload): void {
                $this->createEvent($eventType, $payload);
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

    private function createEvent(string $eventType, string $payload): void
    {
        AgentRunEvent::query()->create([
            'agent_job_run_id' => $this->run->id,
            'event_type' => $eventType,
            'sequence' => $this->nextSequence++,
            'payload' => $payload,
            'event_ts' => CarbonImmutable::now('UTC'),
        ]);
    }

    private function redact(string $payload, int &$redactionCount): string
    {
        $patterns = [
            '/-----BEGIN [A-Z ]*PRIVATE KEY-----[\s\S]*?-----END [A-Z ]*PRIVATE KEY-----/m' => '[REDACTED_PRIVATE_KEY]',
            '/\bBearer\s+[A-Za-z0-9._\-]+\b/i' => '[REDACTED_BEARER_TOKEN]',
            '/\b(?:api[_-]?key|apikey)\s*[:=]\s*[^\s,;]+/i' => '[REDACTED_API_KEY]',
            '/\b(?:password|passwd|pwd)\s*[:=]\s*[^\s,;]+/i' => '[REDACTED_PASSWORD]',
            '/\b(?:secret|token|credential)\s*[:=]\s*[^\s,;]+/i' => '[REDACTED]',
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
    }
}
