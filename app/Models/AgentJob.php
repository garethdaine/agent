<?php

namespace App\Models;

use App\Support\Agent\WorkflowKey;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use InvalidArgumentException;

/**
 * @mixin Builder
 */
class AgentJob extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $guarded = [];

    protected static function booted(): void
    {
        static::saving(function (self $job): void {
            $normalized = WorkflowKey::normalize($job->workflow_key);

            if ($normalized === null) {
                $job->workflow_key = WorkflowKey::deriveFromName((string) $job->name, $job->id);

                return;
            }

            if (! WorkflowKey::isValid($normalized)) {
                throw new InvalidArgumentException('The workflow_key format is invalid.');
            }

            $job->workflow_key = $normalized;
        });
    }

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'governance_paused_at' => 'datetime',
            'max_runtime_seconds' => 'integer',
            'cooldown_seconds' => 'integer',
            'scheduled_path_failure_streak' => 'integer',
            'env_json' => 'array',
            'active_hours_config' => 'array',
            'star_preamble_enabled' => 'boolean',
            'targeted_retry_enabled' => 'boolean',
            'max_retries' => 'integer',
        ];
    }

    /**
     * Backward-compatible alias for legacy payloads/tests using `command`.
     */
    public function setCommandAttribute(?string $value): void
    {
        $this->attributes['command_template'] = $value;
    }

    public function getCommandAttribute(): ?string
    {
        return $this->attributes['command_template'] ?? null;
    }

    /**
     * Backward-compatible alias for legacy payloads/tests using `schedule`.
     */
    public function setScheduleAttribute(?string $value): void
    {
        $this->attributes['cron_expression'] = $value;
    }

    public function getScheduleAttribute(): ?string
    {
        return $this->attributes['cron_expression'] ?? null;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function runs(): HasMany
    {
        return $this->hasMany(AgentJobRun::class);
    }

    public function scopeEnabled(Builder $query): void
    {
        $query->where('is_enabled', true);
    }

    public function scopeActive(Builder $query): void
    {
        $query->whereNull('deleted_at')->where('is_enabled', true);
    }

    public function scopeLatest(Builder $query): void
    {
        $query->orderByDesc('updated_at');
    }

    public function scopeForUser(Builder $query, User $user): void
    {
        $teamIds = $user->allTeams()->pluck('id');
        $query->where(function (Builder $q) use ($user, $teamIds): void {
            $q->where('user_id', $user->id)
                ->orWhereIn('team_id', $teamIds);
        });
    }
}
