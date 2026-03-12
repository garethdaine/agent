<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $resource_type
 * @property string $resource_id
 * @property int $holder_delegation_task_id
 * @property \Carbon\CarbonInterface $acquired_at
 * @property \Carbon\CarbonInterface $expires_at
 * @property string $lock_type
 * @property \Carbon\CarbonInterface|null $created_at
 * @property \Carbon\CarbonInterface|null $updated_at
 * @property-read \App\Models\DelegationTask|null $holderTask
 *
 * @mixin \Illuminate\Database\Eloquent\Builder
 */
class DelegationCoordinationLock extends Model
{
    use HasFactory;

    protected $fillable = [
        'resource_type',
        'resource_id',
        'holder_delegation_task_id',
        'acquired_at',
        'expires_at',
        'lock_type',
    ];

    public const LOCK_EXCLUSIVE = 'exclusive';

    public const LOCK_SHARED = 'shared';

    protected function casts(): array
    {
        return [
            'acquired_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function holderTask(): BelongsTo
    {
        return $this->belongsTo(DelegationTask::class, 'holder_delegation_task_id');
    }

    /**
     * Determine whether this lock has expired.
     */
    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /**
     * Scope to only active (non-expired) locks.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('expires_at', '>', now());
    }

    /**
     * Scope to locks for a specific resource.
     */
    public function scopeForResource(Builder $query, string $resourceType, string $resourceId): Builder
    {
        return $query->where('resource_type', $resourceType)
            ->where('resource_id', $resourceId);
    }
}
