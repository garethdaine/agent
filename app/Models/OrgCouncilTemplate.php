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
class OrgCouncilTemplate extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'name',
        'description',
        'member_list',
        'evidence_payload_schema',
        'member_response_schema',
        'synthesis_mode',
        'use_model_synthesis',
        'report_sections',
        'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'member_list' => 'array',
            'evidence_payload_schema' => 'array',
            'member_response_schema' => 'array',
            'report_sections' => 'array',
            'use_model_synthesis' => 'boolean',
            'archived_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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
     * Get the chair member from the member list (if synthesis_mode is chair_decides).
     */
    public function getChairMember(): ?array
    {
        foreach ($this->member_list ?? [] as $member) {
            if (! empty($member['is_chair'])) {
                return $member;
            }
        }

        return null;
    }

    /**
     * Get a specific member by agent ID.
     */
    public function getMemberByAgentId(string $agentId): ?array
    {
        foreach ($this->member_list ?? [] as $member) {
            if (($member['agent_id'] ?? null) === $agentId) {
                return $member;
            }
        }

        return null;
    }
}
