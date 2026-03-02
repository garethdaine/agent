<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin Builder
 */
class OrgCostLedger extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'org_agent_id',
        'ritual_run_id',
        'token_count',
        'runtime_ms',
        'estimated_cost_usd',
        'recorded_at',
    ];

    protected function casts(): array
    {
        return [
            'token_count' => 'integer',
            'runtime_ms' => 'integer',
            'estimated_cost_usd' => 'decimal:6',
            'recorded_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(OrgAgentProfile::class, 'org_agent_id');
    }

    public function ritualRun(): BelongsTo
    {
        return $this->belongsTo(OrgRitualRun::class, 'ritual_run_id');
    }

    public function scopeForUser(Builder $query, int|string $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    public function scopeForAgent(Builder $query, string $agentId): Builder
    {
        return $query->where('org_agent_id', $agentId);
    }

    public function scopeForRun(Builder $query, string $runId): Builder
    {
        return $query->where('ritual_run_id', $runId);
    }

    public function scopeRecordedBetween(Builder $query, $start, $end): Builder
    {
        return $query->whereBetween('recorded_at', [$start, $end]);
    }
}
