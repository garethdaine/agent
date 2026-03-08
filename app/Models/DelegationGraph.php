<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @mixin Builder
 */
class DelegationGraph extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $guarded = [];

    public const STATUS_DRAFT = 'draft';

    public const STATUS_VALIDATING = 'validating';

    public const STATUS_READY = 'ready';

    public const STATUS_RUNNING = 'running';

    public const STATUS_SUCCEEDED = 'succeeded';

    public const STATUS_FAILED = 'failed';

    public const STATUS_PARTIAL = 'partial';

    public const STATUS_CANCELLED = 'cancelled';

    public const ACTIVE_STATUSES = [self::STATUS_RUNNING];

    public const TERMINAL_STATUSES = [
        self::STATUS_SUCCEEDED,
        self::STATUS_FAILED,
        self::STATUS_PARTIAL,
        self::STATUS_CANCELLED,
    ];

    protected function casts(): array
    {
        return [
            'metadata_json' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'max_parallel_tasks' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(DelegationTask::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(DelegationEvent::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', self::ACTIVE_STATUSES);
    }

    public function scopeTerminal(Builder $query): Builder
    {
        return $query->whereIn('status', self::TERMINAL_STATUSES);
    }
}
