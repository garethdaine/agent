<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @mixin Builder
 */
class OrgRitualTemplate extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'name',
        'description',
        'cron_expression',
        'timezone',
        'nl_source_metadata',
        'phase_graph',
        'phase_role_mappings',
        'context_inputs',
        'verification_strategy',
        'delivery_targets',
        'required_skills',
        'escalation_timeout_seconds',
        'notification_level',
        'is_paused',
        'archived_at',
    ];

    public const NOTIFICATION_LEVEL_ESCALATIONS_ONLY = 'escalations_only';

    public const NOTIFICATION_LEVEL_LIFECYCLE = 'lifecycle';

    public const NOTIFICATION_LEVEL_VERBOSE = 'verbose';

    protected function casts(): array
    {
        return [
            'phase_graph' => 'array',
            'phase_role_mappings' => 'array',
            'context_inputs' => 'array',
            'verification_strategy' => 'array',
            'delivery_targets' => 'array',
            'required_skills' => 'array',
            'escalation_timeout_seconds' => 'integer',
            'is_paused' => 'boolean',
            'archived_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function runs(): HasMany
    {
        return $this->hasMany(OrgRitualRun::class, 'ritual_template_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('archived_at');
    }

    public function scopeArchived(Builder $query): Builder
    {
        return $query->whereNotNull('archived_at');
    }

    public function scopeScheduled(Builder $query): Builder
    {
        return $query->where('is_paused', false)->whereNull('archived_at');
    }

    public function scopeForUser(Builder $query, int|string $userId): Builder
    {
        return $query->where('user_id', $userId);
    }
}
