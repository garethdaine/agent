<?php

declare(strict_types=1);

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
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property string $workflow_key
 * @property string|null $description
 * @property string $cron_expression
 * @property string $timezone
 * @property array|null $active_hours_config
 * @property bool|null $star_preamble_enabled
 * @property bool|null $targeted_retry_enabled
 * @property int|null $max_retries
 * @property bool $is_enabled
 * @property int $max_runtime_seconds
 * @property int $cooldown_seconds
 * @property string $runner_type
 * @property string $command_template
 * @property string $task_markdown_path
 * @property string $working_directory
 * @property array|null $env_json
 * @property string|null $last_validated_executable_path
 * @property int $scheduled_path_failure_streak
 * @property \Carbon\CarbonInterface|null $deleted_at
 * @property \Carbon\CarbonInterface|null $created_at
 * @property \Carbon\CarbonInterface|null $updated_at
 * @property \Carbon\CarbonInterface|null $governance_paused_at
 * @property string|null $governance_pause_reason
 * @property string|null $governance_paused_by
 * @property int|null $team_id
 * @property-read \App\Models\User|null $user
 * @property-read \App\Models\Team|null $team
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\AgentJobRun> $runs
 *
 * @mixin \Illuminate\Database\Eloquent\Builder
 */
class AgentJob extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'name',
        'workflow_key',
        'description',
        'cron_expression',
        'timezone',
        'active_hours_config',
        'star_preamble_enabled',
        'targeted_retry_enabled',
        'max_retries',
        'is_enabled',
        'max_runtime_seconds',
        'cooldown_seconds',
        'runner_type',
        'command_template',
        'task_markdown_path',
        'working_directory',
        'env_json',
        'last_validated_executable_path',
        'scheduled_path_failure_streak',
    ];

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
