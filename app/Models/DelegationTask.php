<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $delegation_graph_id
 * @property string $name
 * @property string $status
 * @property int $sequence_order
 * @property array $contract_json
 * @property int|null $assigned_delegatee_profile_id
 * @property array|null $assignment_reason_json
 * @property array|null $metadata_json
 * @property string|null $error_code
 * @property string|null $error_summary
 * @property \Carbon\CarbonInterface|null $started_at
 * @property \Carbon\CarbonInterface|null $finished_at
 * @property \Carbon\CarbonInterface|null $created_at
 * @property \Carbon\CarbonInterface|null $updated_at
 * @property-read \App\Models\DelegationGraph|null $graph
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\DelegationAttempt> $attempts
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\DelegationVerificationResult> $verificationResults
 * @property-read \App\Models\DelegateeProfile|null $assignedProfile
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\DelegationTask> $dependencies
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\DelegationTask> $dependents
 *
 * @mixin \Illuminate\Database\Eloquent\Builder
 */
class DelegationTask extends Model
{
    use HasFactory;

    protected $fillable = [
        'delegation_graph_id',
        'name',
        'status',
        'sequence_order',
        'contract_json',
        'assigned_delegatee_profile_id',
        'assignment_reason_json',
        'metadata_json',
        'error_code',
        'error_summary',
        'started_at',
        'finished_at',
    ];

    public const STATUS_PENDING = 'pending';

    public const STATUS_BLOCKED = 'blocked';

    public const STATUS_READY = 'ready';

    public const STATUS_ASSIGNED = 'assigned';

    public const STATUS_RUNNING = 'running';

    public const STATUS_VERIFYING = 'verifying';

    public const STATUS_SUCCEEDED = 'succeeded';

    public const STATUS_FAILED = 'failed';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_PENDING_APPROVAL = 'pending_approval';

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
