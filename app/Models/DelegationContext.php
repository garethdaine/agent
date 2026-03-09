<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $delegation_task_id
 * @property array $context_json
 * @property string $propagation_direction
 * @property int $ttl_seconds
 * @property int|null $created_by_agent_id
 * @property \Carbon\CarbonInterface|null $created_at
 * @property \Carbon\CarbonInterface|null $updated_at
 * @property-read \App\Models\DelegationTask|null $task
 *
 * @mixin \Illuminate\Database\Eloquent\Builder
 */
class DelegationContext extends Model
{
    use HasFactory;

    protected $fillable = [
        'delegation_task_id',
        'context_json',
        'propagation_direction',
        'ttl_seconds',
        'created_by_agent_id',
    ];

    protected function casts(): array
    {
        return [
            'context_json' => 'array',
            'ttl_seconds' => 'integer',
            'created_by_agent_id' => 'integer',
        ];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(DelegationTask::class, 'delegation_task_id');
    }

    /**
     * Determine whether this context has expired based on its TTL.
     */
    public function isExpired(): bool
    {
        return $this->created_at->addSeconds($this->ttl_seconds)->isPast();
    }
}
