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
 * @property string $id
 * @property int $user_id
 * @property string $name
 * @property string $role_slug
 * @property string $role_description
 * @property int $delegatee_profile_id
 * @property array $capability_bindings
 * @property array $authority_overrides
 * @property array|null $default_output_schema
 * @property string|null $parent_agent_id
 * @property \Carbon\CarbonInterface|null $archived_at
 * @property \Carbon\CarbonInterface|null $created_at
 * @property \Carbon\CarbonInterface|null $updated_at
 * @property array|null $soul_json
 * @property array|null $skill_access_profile
 * @property-read \App\Models\User|null $user
 * @property-read \App\Models\DelegateeProfile|null $delegateeProfile
 * @property-read \App\Models\OrgAgentProfile|null $parent
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\OrgAgentProfile> $children
 * @property-read \App\Models\OrgReportingEdge|null $reportingEdge
 *
 * @mixin \Illuminate\Database\Eloquent\Builder
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

    /**
     * Get the soul/identity configuration for this org agent profile.
     *
     * Matches the pattern from ConnectorAccount::getSoul() and DelegateeProfile::getSoul().
     *
     * @return array{name: string|null, personality: string|null, system_prompt: string|null, user_context: string|null}
     */
    public function getSoul(): array
    {
        $soul = $this->soul_json ?? [];

        return [
            'name' => $soul['name'] ?? null,
            'personality' => $soul['personality'] ?? null,
            'system_prompt' => $soul['system_prompt'] ?? null,
            'user_context' => $soul['user_context'] ?? null,
        ];
    }
}
