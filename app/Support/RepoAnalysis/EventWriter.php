<?php

declare(strict_types=1);

namespace App\Support\RepoAnalysis;

use App\Events\RepoAnalysisSessionUpdated;
use App\Models\RepoAnalysisEvent;
use App\Models\RepoAnalysisSession;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use JsonSerializable;
use Stringable;

class EventWriter
{
    /**
     * @param  callable(array<string, mixed>, RepoAnalysisEvent): void|null  $broadcastHook
     */
    public function __construct(
        private readonly RepoAnalysisSession $session,
        private readonly mixed $broadcastHook = null,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @param  array{phase?: int|null,status?: string|null,error_code?: string|null,error_summary?: string|null,metadata_json?: array<string, mixed>|null,event_ts?: \DateTimeInterface|null}  $attributes
     */
    public function append(string $eventType, array $payload = [], array $attributes = []): RepoAnalysisEvent
    {
        $payload = $this->normalizePayloadForStorage($this->redactPayload($payload));
        $metadata = $attributes['metadata_json'] ?? [];
        $metadata = is_array($metadata)
            ? $this->normalizePayloadForStorage($this->redactPayload($metadata))
            : [];

        $event = DB::transaction(function () use ($eventType, $payload, $metadata, $attributes): RepoAnalysisEvent {
            RepoAnalysisSession::query()
                ->whereKey($this->session->id)
                ->lockForUpdate()
                ->first();

            $sequence = (int) (RepoAnalysisEvent::query()
                ->forSession((int) $this->session->id)
                ->max('sequence') ?? 0) + 1;

            return RepoAnalysisEvent::query()->create([
                'repo_analysis_session_id' => $this->session->id,
                'sequence' => $sequence,
                'event_type' => $eventType,
                'payload_json' => $payload,
                'event_ts' => $attributes['event_ts'] ?? CarbonImmutable::now('UTC'),
                'phase' => $attributes['phase'] ?? null,
                'status' => isset($attributes['status']) ? $this->normalizeUtf8((string) $attributes['status']) : null,
                'error_code' => isset($attributes['error_code']) ? $this->normalizeUtf8((string) $attributes['error_code']) : null,
                'error_summary' => isset($attributes['error_summary'])
                    ? $this->redactString($this->normalizeUtf8((string) $attributes['error_summary']))
                    : null,
                'metadata_json' => $metadata,
            ]);
        }, 3);

        if (is_callable($this->broadcastHook)) {
            ($this->broadcastHook)($this->toEnvelope($event), $event);
        }

        event(new RepoAnalysisSessionUpdated(
            (int) $event->repo_analysis_session_id,
            $this->toEnvelope($event),
        ));

        return $event;
    }

    /**
     * @return Collection<int, RepoAnalysisEvent>
     */
    public static function readSinceSequence(int $sessionId, int $sinceSequence = 0, int $limit = 100): Collection
    {
        $sinceSequence = max(0, $sinceSequence);
        $limit = min(500, max(1, $limit));

        return RepoAnalysisEvent::query()
            ->forSession($sessionId)
            ->sinceSequence($sinceSequence)
            ->orderedSequence()
            ->limit($limit)
            ->get();
    }

    /**
     * @return array<string, mixed>
     */
    private function toEnvelope(RepoAnalysisEvent $event): array
    {
        return [
            'session_id' => (int) $event->repo_analysis_session_id,
            'sequence' => (int) $event->sequence,
            'event_type' => (string) $event->event_type,
            'event_ts' => $event->event_ts?->toIso8601String(),
            'payload' => $event->payload_json ?? [],
            'phase' => $event->phase !== null ? (int) $event->phase : null,
            'status' => $event->status,
            'error_code' => $event->error_code,
            'error_summary' => $event->error_summary,
            'metadata' => $event->metadata_json ?? [],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function redactPayload(array $payload): array
    {
        $redact = function ($value, ?string $key = null) use (&$redact) {
            if ($this->isSecretKey($key)) {
                return '[REDACTED]';
            }

            if (is_array($value)) {
                $normalized = [];

                foreach ($value as $itemKey => $itemValue) {
                    $normalizedKey = is_string($itemKey)
                        ? $this->normalizeUtf8($itemKey)
                        : (is_int($itemKey) ? $itemKey : (string) $itemKey);

                    $normalized[$normalizedKey] = $redact($itemValue, is_string($normalizedKey) ? $normalizedKey : null);
                }

                return $normalized;
            }

            if ($value instanceof JsonSerializable) {
                return $redact($value->jsonSerialize(), $key);
            }

            if ($value instanceof Stringable || (is_object($value) && method_exists($value, '__toString'))) {
                $value = (string) $value;
            }

            if (is_resource($value)) {
                return '[resource]';
            }

            if (is_object($value)) {
                return '[object '.get_class($value).']';
            }

            if (! is_string($value)) {
                return $value;
            }

            return $this->redactString($this->normalizeUtf8($value));
        };

        /** @var array<string, mixed> $normalized */
        $normalized = $redact($payload);

        return $normalized;
    }

    private function isSecretKey(?string $key): bool
    {
        if ($key === null) {
            return false;
        }

        return preg_match('/(?:^|[_\-.])(api[_-]?key|token|password|secret)(?:$|[_\-.])/i', $key) === 1;
    }

    private function redactString(string $value): string
    {
        return preg_replace([
            '/\b(?:api[_-]?key|token|password|secret)\s*[:=]\s*[^\s,;]+/i',
            '/\bBearer\s+[A-Za-z0-9._\-]+/i',
        ], ['[REDACTED]', '[REDACTED_BEARER_TOKEN]'], $value) ?? $value;
    }

    private function normalizeUtf8(string $value): string
    {
        if (! mb_check_encoding($value, 'UTF-8')) {
            $converted = @iconv('UTF-8', 'UTF-8//IGNORE', $value);

            if (is_string($converted) && $converted !== '') {
                $value = $converted;
            } else {
                $value = mb_convert_encoding($value, 'UTF-8', 'UTF-8');
            }
        }

        $sanitized = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $value);

        return is_string($sanitized) ? $sanitized : $value;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function normalizePayloadForStorage(array $payload): array
    {
        return json_decode(
            json_encode($payload, JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}',
            true,
            flags: JSON_INVALID_UTF8_SUBSTITUTE
        ) ?: [];
    }
}
