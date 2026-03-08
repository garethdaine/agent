<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $ritual_run_id
 * @property string $escalation_type
 * @property string $escalated_to_agent_id
 * @property string $state
 * @property \Carbon\CarbonInterface|null $timeout_at
 * @property string|null $resolved_by
 * @property \Carbon\CarbonInterface|null $resolved_at
 * @property string|null $resolution_notes
 * @property \Carbon\CarbonInterface|null $created_at
 * @property \Carbon\CarbonInterface|null $updated_at
 * @property-read \App\Models\OrgRitualRun|null $ritualRun
 * @property-read \App\Models\OrgAgentProfile|null $escalatedToAgent
 *
 * @mixin \Illuminate\Database\Eloquent\Builder
 */
class OrgEscalation extends Model
{
    use HasFactory, HasUuids;

    public const TYPE_BUDGET = 'budget';

    public const TYPE_VERIFICATION = 'verification';

    public const TYPE_RISK = 'risk';

    public const TYPE_APPROVAL = 'approval';

    public const STATE_PENDING = 'pending';

    public const STATE_APPROVED = 'approved';

    public const STATE_REJECTED = 'rejected';

    public const STATE_TIMED_OUT = 'timed_out';

    public const TYPES = [
        self::TYPE_BUDGET,
        self::TYPE_VERIFICATION,
        self::TYPE_RISK,
        self::TYPE_APPROVAL,
    ];

    public const STATES = [
        self::STATE_PENDING,
        self::STATE_APPROVED,
        self::STATE_REJECTED,
        self::STATE_TIMED_OUT,
    ];

    protected $fillable = [
        'ritual_run_id',
        'escalation_type',
        'escalated_to_agent_id',
        'state',
        'timeout_at',
        'resolved_by',
        'resolved_at',
        'resolution_notes',
    ];

    protected function casts(): array
    {
        return [
            'timeout_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function ritualRun(): BelongsTo
    {
        return $this->belongsTo(OrgRitualRun::class, 'ritual_run_id');
    }

    public function escalatedToAgent(): BelongsTo
    {
        return $this->belongsTo(OrgAgentProfile::class, 'escalated_to_agent_id');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('state', self::STATE_PENDING);
    }

    public function scopeExpired(Builder $query): Builder
    {
        return $query->where('state', self::STATE_PENDING)
            ->whereNotNull('timeout_at')
            ->where('timeout_at', '<=', now());
    }

    public function scopeForRun(Builder $query, string $runId): Builder
    {
        return $query->where('ritual_run_id', $runId);
    }

    public function isPending(): bool
    {
        return $this->state === self::STATE_PENDING;
    }

    public function isExpired(): bool
    {
        return $this->isPending() && $this->timeout_at && $this->timeout_at->isPast();
    }
}
