<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @mixin Builder
 */
class OrgAgentProfile extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'name',
        'role_slug',
        'role_description',
        'delegatee_profile_id',
        'capability_bindings',
        'authority_overrides',
        'skill_access_profile',
        'default_output_schema',
        'parent_agent_id',
        'archived_at',
        'soul_json',
    ];

    protected function casts(): array
    {
        return [
            'capability_bindings' => 'array',
            'authority_overrides' => 'array',
            'skill_access_profile' => 'array',
            'default_output_schema' => 'array',
            'archived_at' => 'datetime',
            'soul_json' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function delegateeProfile(): BelongsTo
    {
        return $this->belongsTo(DelegateeProfile::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_agent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_agent_id');
    }

    public function reportingEdge(): HasOne
    {
        return $this->hasOne(OrgReportingEdge::class, 'subordinate_agent_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('archived_at');
    }

    public function scopeArchived(Builder $query): Builder
    {
        return $query->whereNotNull('archived_at');
    }

    public function scopeForUser(Builder $query, int|string $userId): Builder
    {
        return $query->where('user_id', $userId);
    }
}
