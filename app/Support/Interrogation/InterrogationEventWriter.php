<?php

namespace App\Support\Interrogation;

use App\Events\InterrogationPhaseChanged;
use App\Events\InterrogationSessionUpdated;
use App\Models\InterrogationEvent;
use App\Models\InterrogationSession;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use JsonSerializable;
use Stringable;

class InterrogationEventWriter
{
    private int $nextSequence;

    public function __construct(private InterrogationSession $session)
    {
        $this->nextSequence = (int) (InterrogationEvent::query()
            ->where('interrogation_session_id', $this->session->id)
            ->max('sequence') ?? 0) + 1;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function appendDiscoveryActivity(array $payload): InterrogationEvent
    {
        return $this->append(InterrogationEvent::TYPE_DISCOVERY_ACTIVITY, $payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function appendQuestion(array $payload): InterrogationEvent
    {
        return $this->append(InterrogationEvent::TYPE_QUESTION, $payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function appendAnswer(array $payload): InterrogationEvent
    {
        return $this->append(InterrogationEvent::TYPE_ANSWER, $payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function appendPhaseTransition(int $fromPhase, int $toPhase, string $status, array $payload = []): InterrogationEvent
    {
        $event = $this->append(InterrogationEvent::TYPE_PHASE_TRANSITION, array_merge($payload, [
            'from_phase' => $fromPhase,
            'to_phase' => $toPhase,
            'status' => $status,
        ]));

        event(new InterrogationPhaseChanged((int) $this->session->id, $fromPhase, $toPhase, $status));

        return $event;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function appendSummary(array $payload): InterrogationEvent
    {
        return $this->append(InterrogationEvent::TYPE_SUMMARY, $payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function appendPlan(array $payload): InterrogationEvent
    {
        return $this->append(InterrogationEvent::TYPE_PLAN, $payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function appendError(array $payload): InterrogationEvent
    {
        return $this->append(InterrogationEvent::TYPE_ERROR, $payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function appendAnnotation(array $payload): InterrogationEvent
    {
        return $this->append(InterrogationEvent::TYPE_ANNOTATION, $payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function appendSystem(array $payload): InterrogationEvent
    {
        return $this->append(InterrogationEvent::TYPE_SYSTEM, $payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function append(string $eventType, array $payload): InterrogationEvent
    {
        $payload = $this->redactPayload($payload);
        $payload = $this->normalizePayloadForStorage($payload);

        $event = DB::transaction(function () use ($eventType, $payload): InterrogationEvent {
            InterrogationSession::query()
                ->whereKey($this->session->id)
                ->lockForUpdate()
                ->first();

            $sequence = (int) (InterrogationEvent::query()
                ->where('interrogation_session_id', $this->session->id)
                ->max('sequence') ?? 0) + 1;

            $this->nextSequence = $sequence + 1;

            return InterrogationEvent::query()->create([
                'interrogation_session_id' => $this->session->id,
                'event_type' => $eventType,
                'sequence' => $sequence,
                'payload' => $payload,
                'event_ts' => CarbonImmutable::now('UTC'),
            ]);
        }, 3);

        event(new InterrogationSessionUpdated(
            (int) $this->session->id,
            $eventType,
            $payload,
            (int) $event->sequence,
        ));

        return $event;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function redactPayload(array $payload): array
    {
        $redact = function ($value) use (&$redact) {
            if (is_array($value)) {
                $next = [];
                foreach ($value as $key => $item) {
                    $normalizedKey = is_string($key) ? $this->normalizeUtf8($key) : $key;
                    if (! is_int($normalizedKey) && ! is_string($normalizedKey)) {
                        $normalizedKey = (string) $normalizedKey;
                    }

                    $next[$normalizedKey] = $redact($item);
                }

                return $next;
            }

            if ($value instanceof JsonSerializable) {
                return $redact($value->jsonSerialize());
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

            $value = $this->normalizeUtf8($value);

            return preg_replace([
                '/\b(?:api[_-]?key|token|password|secret)\s*[:=]\s*[^\s,;]+/i',
                '/\bBearer\s+[A-Za-z0-9._\-]+/i',
            ], ['[REDACTED]', '[REDACTED_BEARER_TOKEN]'], $value) ?? $value;
        };

        return $redact($payload);
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
        $payload = $this->normalizePayloadValue($payload);

        $encoded = json_encode(
            $payload,
            JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR
        );

        if (! is_string($encoded)) {
            return ['message' => 'Event payload encoding failed.'];
        }

        $decoded = json_decode($encoded, true);

        return is_array($decoded) ? $decoded : ['message' => 'Event payload decoding failed.'];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function normalizePayloadValue(array $payload): array
    {
        $normalize = function ($value) use (&$normalize) {
            if (is_array($value)) {
                $next = [];
                foreach ($value as $key => $item) {
                    $normalizedKey = is_string($key) ? $this->normalizeUtf8($key) : $key;
                    if (! is_int($normalizedKey) && ! is_string($normalizedKey)) {
                        $normalizedKey = (string) $normalizedKey;
                    }

                    $next[$normalizedKey] = $normalize($item);
                }

                return $next;
            }

            if ($value instanceof JsonSerializable) {
                return $normalize($value->jsonSerialize());
            }

            if ($value instanceof Stringable || (is_object($value) && method_exists($value, '__toString'))) {
                return $this->normalizeUtf8((string) $value);
            }

            if (is_resource($value)) {
                return '[resource]';
            }

            if (is_object($value)) {
                return '[object '.get_class($value).']';
            }

            if (is_string($value)) {
                return $this->normalizeUtf8($value);
            }

            return $value;
        };

        $normalized = $normalize($payload);

        return is_array($normalized) ? $normalized : ['message' => 'Event payload normalization failed.'];
    }
}
