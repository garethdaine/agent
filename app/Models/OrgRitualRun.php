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
 * @property string $ritual_template_id
 * @property int $user_id
 * @property string $state
 * @property int|null $delegation_graph_id
 * @property array|null $phase_outputs
 * @property \Carbon\CarbonInterface|null $started_at
 * @property \Carbon\CarbonInterface|null $completed_at
 * @property string $correlation_id
 * @property \Carbon\CarbonInterface|null $created_at
 * @property \Carbon\CarbonInterface|null $updated_at
 * @property-read \App\Models\OrgRitualTemplate|null $template
 * @property-read \App\Models\User|null $user
 * @property-read \App\Models\DelegationGraph|null $delegationGraph
 *
 * @mixin \Illuminate\Database\Eloquent\Builder
 */
class OrgRitualRun extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'ritual_template_id',
        'user_id',
        'state',
        'delegation_graph_id',
        'phase_outputs',
        'started_at',
        'completed_at',
        'correlation_id',
    ];

    public const STATE_DRAFT = 'draft';

    public const STATE_SCHEDULED = 'scheduled';

    public const STATE_QUEUED = 'queued';

    public const STATE_RUNNING = 'running';

    public const STATE_WAITING_APPROVAL = 'waiting_approval';

    public const STATE_REVIEWING = 'reviewing';

    public const STATE_SUCCEEDED = 'succeeded';

    public const STATE_FAILED = 'failed';

    public const STATE_CANCELLED = 'cancelled';

    public const STATE_PARTIAL = 'partial';

    public const ACTIVE_STATES = [
        self::STATE_QUEUED,
        self::STATE_RUNNING,
        self::STATE_WAITING_APPROVAL,
        self::STATE_REVIEWING,
    ];

    public const TERMINAL_STATES = [
        self::STATE_SUCCEEDED,
        self::STATE_FAILED,
        self::STATE_CANCELLED,
        self::STATE_PARTIAL,
    ];

    protected function casts(): array
    {
        return [
            'phase_outputs' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(OrgRitualTemplate::class, 'ritual_template_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function delegationGraph(): BelongsTo
    {
        return $this->belongsTo(DelegationGraph::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('state', self::ACTIVE_STATES);
    }

    public function scopeTerminal(Builder $query): Builder
    {
        return $query->whereIn('state', self::TERMINAL_STATES);
    }

    public function scopeForUser(Builder $query, int|string $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    public function scopeForTemplate(Builder $query, string $templateId): Builder
    {
        return $query->where('ritual_template_id', $templateId);
    }
}
