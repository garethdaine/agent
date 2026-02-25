<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @mixin Builder
 */
class DelegationTask extends Model
{
    use HasFactory;

    protected $guarded = [];

    public const STATUS_PENDING = 'pending';

    public const STATUS_BLOCKED = 'blocked';

    public const STATUS_READY = 'ready';

    public const STATUS_ASSIGNED = 'assigned';

    public const STATUS_RUNNING = 'running';

    public const STATUS_VERIFYING = 'verifying';

    public const STATUS_SUCCEEDED = 'succeeded';

    public const STATUS_FAILED = 'failed';

    public const STATUS_CANCELLED = 'cancelled';

    protected function casts(): array
    {
        return [
            'contract_json' => 'array',
            'assignment_reason_json' => 'array',
            'metadata_json' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'sequence_order' => 'integer',
        ];
    }

    public function graph(): BelongsTo
    {
        return $this->belongsTo(DelegationGraph::class, 'delegation_graph_id');
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(DelegationAttempt::class);
    }

    public function verificationResults(): HasMany
    {
        return $this->hasMany(DelegationVerificationResult::class);
    }

    public function assignedProfile(): BelongsTo
    {
        return $this->belongsTo(DelegateeProfile::class, 'assigned_delegatee_profile_id');
    }

    /**
     * Tasks that this task depends on (must complete before this task can start).
     */
    public function dependencies(): BelongsToMany
    {
        return $this->belongsToMany(
            self::class,
            'delegation_task_dependencies',
            'task_id',
            'depends_on_task_id'
        );
    }

    /**
     * Tasks that depend on this task (cannot start until this task completes).
     */
    public function dependents(): BelongsToMany
    {
        return $this->belongsToMany(
            self::class,
            'delegation_task_dependencies',
            'depends_on_task_id',
            'task_id'
        );
    }
}
