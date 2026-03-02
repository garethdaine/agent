<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Formation failure record for memory formation retry.
 *
 * Stores failed formation attempts with serialized payload for backfill retry.
 */
class MemoryFormationFailure extends Model
{
    protected $guarded = [];

    /**
     * The name of the "updated at" column.
     * This table only has created_at.
     */
    public const UPDATED_AT = null;

    /**
     * Failure type constants.
     */
    public const TYPE_EXTRACTION = 'extraction';

    public const TYPE_EMBEDDING = 'embedding';

    public const TYPE_GRAPH = 'graph';

    public const TYPE_PROVIDER_ERROR = 'provider_error';

    public const TYPE_TIMEOUT = 'timeout';

    /**
     * Maximum backfill attempts before marking permanently unrecoverable.
     */
    public const MAX_BACKFILL_ATTEMPTS = 5;

    /**
     * Valid failure types.
     *
     * @var array<string>
     */
    public static array $validTypes = [
        self::TYPE_EXTRACTION,
        self::TYPE_EMBEDDING,
        self::TYPE_GRAPH,
        self::TYPE_PROVIDER_ERROR,
        self::TYPE_TIMEOUT,
    ];

    protected function casts(): array
    {
        return [
            'attempts' => 'integer',
            'backfilled_at' => 'datetime',
            'payload_json' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(AgentJobRun::class, 'run_id');
    }

    /**
     * Scope for pending (not yet backfilled) failures.
     */
    public function scopePending(Builder $query): void
    {
        $query->whereNull('backfilled_at');
    }

    /**
     * Scope for backfilled failures.
     */
    public function scopeBackfilled(Builder $query): void
    {
        $query->whereNotNull('backfilled_at');
    }

    /**
     * Scope for retriable failures (pending and under max attempts).
     */
    public function scopeRetriable(Builder $query): void
    {
        $query->pending()
            ->where('attempts', '<', self::MAX_BACKFILL_ATTEMPTS);
    }

    /**
     * Scope for permanently failed (exceeded max attempts).
     */
    public function scopePermanentlyFailed(Builder $query): void
    {
        $query->pending()
            ->where('attempts', '>=', self::MAX_BACKFILL_ATTEMPTS);
    }

    /**
     * Scope to filter by user.
     */
    public function scopeForUser(Builder $query, int $userId): void
    {
        $query->where('user_id', $userId);
    }

    /**
     * Scope to filter by failure type.
     */
    public function scopeOfType(Builder $query, string $type): void
    {
        $query->where('failure_type', $type);
    }

    /**
     * Get the serialized payload.
     *
     * @return array<string, mixed>
     */
    public function getPayload(): array
    {
        return $this->payload_json ?? [];
    }

    /**
     * Check if this failure can be retried.
     */
    public function canRetry(): bool
    {
        return $this->backfilled_at === null
            && $this->attempts < self::MAX_BACKFILL_ATTEMPTS;
    }

    /**
     * Check if this failure is permanently unrecoverable.
     */
    public function isPermanentlyFailed(): bool
    {
        return $this->backfilled_at === null
            && $this->attempts >= self::MAX_BACKFILL_ATTEMPTS;
    }

    /**
     * Increment the attempt count.
     */
    public function incrementAttempts(): self
    {
        $this->increment('attempts');

        return $this;
    }

    /**
     * Mark as successfully backfilled.
     */
    public function markBackfilled(): self
    {
        $this->update(['backfilled_at' => now()]);

        return $this;
    }

    /**
     * Record a formation failure.
     */
    public static function record(
        int $runId,
        int $userId,
        string $failureType,
        string $errorMessage,
        array $payload
    ): static {
        return static::create([
            'run_id' => $runId,
            'user_id' => $userId,
            'failure_type' => $failureType,
            'error_message' => $errorMessage,
            'payload_json' => $payload,
            'created_at' => now(),
        ]);
    }
}
