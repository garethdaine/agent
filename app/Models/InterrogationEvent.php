<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use JsonSerializable;
use Stringable;

/**
 * @property int $id
 * @property int $interrogation_session_id
 * @property string $event_type
 * @property int $sequence
 * @property array $payload
 * @property \Carbon\CarbonInterface|null $event_ts
 * @property \Carbon\CarbonInterface|null $created_at
 * @property \Carbon\CarbonInterface|null $updated_at
 * @property-read \App\Models\InterrogationSession|null $session
 *
 * @mixin \Illuminate\Database\Eloquent\Builder
 */
class InterrogationEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'interrogation_session_id',
        'event_type',
        'sequence',
        'payload',
        'event_ts',
    ];

    public const TYPE_DISCOVERY_ACTIVITY = 'discovery_activity';

    public const TYPE_QUESTION = 'question';

    public const TYPE_ANSWER = 'answer';

    public const TYPE_PHASE_TRANSITION = 'phase_transition';

    public const TYPE_SUMMARY = 'summary';

    public const TYPE_PLAN = 'plan';

    public const TYPE_ERROR = 'error';

    public const TYPE_ANNOTATION = 'annotation';

    public const TYPE_SYSTEM = 'system';

    public const TYPE_SUMMARY_REVIEW = 'summary_review';

    public const TYPE_PLAN_REVIEW = 'plan_review';

    protected function casts(): array
    {
        return [
            'event_ts' => 'datetime',
            'sequence' => 'integer',
        ];
    }

    protected function payload(): Attribute
    {
        return Attribute::make(
            get: function (mixed $value): array {
                if (is_array($value)) {
                    return $value;
                }

                if (! is_string($value) || trim($value) === '') {
                    return [];
                }

                $decoded = json_decode($value, true);

                return is_array($decoded) ? $decoded : [];
            },
            set: fn (mixed $value): string => $this->encodePayloadForStorage($value),
        );
    }

    private function encodePayloadForStorage(mixed $value): string
    {
        $normalized = $this->normalizePayloadValue($value);

        if (! is_array($normalized)) {
            $normalized = ['message' => is_string($normalized) ? $normalized : 'Event payload normalization failed.'];
        }

        $encoded = json_encode(
            $normalized,
            JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR
        );

        return is_string($encoded) ? $encoded : '{"message":"Event payload encoding failed."}';
    }

    private function normalizePayloadValue(mixed $value): mixed
    {
        if (is_array($value)) {
            $normalized = [];

            foreach ($value as $key => $item) {
                $normalizedKey = is_string($key) ? $this->normalizeUtf8($key) : $key;
                if (! is_int($normalizedKey) && ! is_string($normalizedKey)) { // @phpstan-ignore function.alreadyNarrowedType, booleanAnd.alwaysFalse
                    $normalizedKey = (string) $normalizedKey;
                }

                $normalized[$normalizedKey] = $this->normalizePayloadValue($item);
            }

            return $normalized;
        }

        if (is_string($value)) {
            return $this->normalizeUtf8($value);
        }

        if (is_int($value) || is_float($value) || is_bool($value) || $value === null) {
            return $value;
        }

        if ($value instanceof JsonSerializable) {
            return $this->normalizePayloadValue($value->jsonSerialize());
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

        return $this->normalizeUtf8((string) $value);
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

    public function session(): BelongsTo
    {
        return $this->belongsTo(InterrogationSession::class, 'interrogation_session_id');
    }
}
