<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int|null $user_id
 * @property string $consolidation_type
 * @property int $source_count
 * @property string|null $result_summary
 * @property array|null $checkpoint_json
 * @property \Carbon\CarbonInterface|null $created_at
 * @property-read \App\Models\User|null $user
 * @property int|null $count
 * @property int|null $total_processed
 *
 * @mixin \Illuminate\Database\Eloquent\Builder
 */
class MemoryConsolidationLog extends Model
{
    use HasFactory;

    protected $table = 'memory_consolidation_log';

    protected $fillable = [
        'user_id',
        'consolidation_type',
        'source_count',
        'result_summary',
        'checkpoint_json',
        'created_at',
    ];

    /**
     * The name of the "updated at" column.
     * This table only has created_at.
     */
    public const UPDATED_AT = null;

    /**
     * Consolidation type constants.
     */
    public const TYPE_WORKING_TO_CORE = 'working_to_core';

    public const TYPE_VECTOR_DEDUP = 'vector_dedup';

    public const TYPE_FAILURE_BACKFILL = 'failure_backfill';

    public const TYPE_GRAPH_CLEANUP = 'graph_cleanup';

    public const TYPE_PRUNE = 'prune';

    /**
     * Valid consolidation types.
     *
     * @var array<string>
     */
    public static array $validTypes = [
        self::TYPE_WORKING_TO_CORE,
        self::TYPE_VECTOR_DEDUP,
        self::TYPE_FAILURE_BACKFILL,
        self::TYPE_GRAPH_CLEANUP,
        self::TYPE_PRUNE,
    ];

    protected function casts(): array
    {
        return [
            'source_count' => 'integer',
            'checkpoint_json' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope to filter by user.
     */
    public function scopeForUser(Builder $query, ?int $userId): void
    {
        $query->where('user_id', $userId);
    }

    /**
     * Scope for system-wide consolidations.
     */
    public function scopeSystemWide(Builder $query): void
    {
        $query->whereNull('user_id');
    }

    /**
     * Scope to filter by consolidation type.
     */
    public function scopeOfType(Builder $query, string $type): void
    {
        $query->where('consolidation_type', $type);
    }

    /**
     * Scope to get recent consolidations.
     */
    public function scopeRecent(Builder $query, int $days = 7): void
    {
        $query->where('created_at', '>=', now()->subDays($days));
    }

    /**
     * Get the checkpoint data.
     *
     * @return array<string, mixed>
     */
    public function getCheckpoint(): array
    {
        return $this->checkpoint_json ?? [];
    }

    /**
     * Set the checkpoint data.
     *
     * @param  array<string, mixed>  $checkpoint
     */
    public function setCheckpoint(array $checkpoint): self
    {
        $this->checkpoint_json = $checkpoint;

        return $this;
    }

    /**
     * Check if this consolidation has a checkpoint.
     */
    public function hasCheckpoint(): bool
    {
        return ! empty($this->checkpoint_json);
    }

    /**
     * Get the last checkpoint for a user and consolidation type.
     *
     * @return array<string, mixed>|null
     */
    public static function getLastCheckpoint(?int $userId, string $type): ?array
    {
        $log = static::query()
            ->forUser($userId)
            ->ofType($type)
            ->whereNotNull('checkpoint_json')
            ->orderByDesc('created_at')
            ->first();

        return $log?->checkpoint_json;
    }

    /**
     * Create a consolidation log entry.
     */
    public static function record(
        ?int $userId,
        string $type,
        int $sourceCount,
        ?string $summary = null,
        ?array $checkpoint = null
    ): self {
        return self::create([
            'user_id' => $userId,
            'consolidation_type' => $type,
            'source_count' => $sourceCount,
            'result_summary' => $summary,
            'checkpoint_json' => $checkpoint,
            'created_at' => now(),
        ]);
    }
}
