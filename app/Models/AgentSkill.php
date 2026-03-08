<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin Builder
 */
class AgentSkill extends Model
{
    use HasFactory, HasUuids;

    public $timestamps = false;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_PAUSED = 'paused';

    public const STATUS_FAILED_VALIDATION = 'failed_validation';

    public const STATUS_PENDING_REVIEW = 'pending_review';

    public const RISK_LOW = 'low';

    public const RISK_STANDARD = 'standard';

    public const RISK_ELEVATED = 'elevated';

    public const RISK_CRITICAL = 'critical';

    protected $fillable = [
        'team_id',
        'name',
        'slug',
        'description',
        'version',
        'author',
        'risk_level',
        'status',
        'industries',
        'memory_blocks',
        'mcp_dependencies',
        'tools_required',
        'trigger_keywords',
        'run_after',
        'requires_approval',
        'skill_path',
        'checksum',
        'file_hashes',
        'validation_result',
        'is_global',
        'installed_by',
        'installed_at',
        'updated_by',
        'updated_at',
        'paused_by',
        'paused_at',
        'last_invoked_at',
        'invocation_count',
    ];

    protected function casts(): array
    {
        return [
            'industries' => 'array',
            'memory_blocks' => 'array',
            'mcp_dependencies' => 'array',
            'tools_required' => 'array',
            'trigger_keywords' => 'array',
            'run_after' => 'array',
            'file_hashes' => 'array',
            'validation_result' => 'array',
            'requires_approval' => 'boolean',
            'is_global' => 'boolean',
            'installed_at' => 'datetime',
            'updated_at' => 'datetime',
            'paused_at' => 'datetime',
            'last_invoked_at' => 'datetime',
            'invocation_count' => 'integer',
        ];
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function installedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'installed_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function pausedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paused_by');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeForTeam(Builder $query, int $teamId): Builder
    {
        return $query->where('team_id', $teamId);
    }

    public function scopeGlobal(Builder $query): Builder
    {
        return $query->where('is_global', true);
    }

    public function scopeByRiskLevel(Builder $query, string $level): Builder
    {
        return $query->where('risk_level', $level);
    }
}
