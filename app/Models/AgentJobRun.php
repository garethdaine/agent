<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $agent_job_id
 * @property int $user_id
 * @property int|null $initiated_by_user_id
 * @property string $trigger_type
 * @property \Carbon\CarbonInterface|null $due_window_utc_minute
 * @property string $status
 * @property int|null $pid
 * @property string|null $resolved_executable_path
 * @property \Carbon\CarbonInterface|null $started_at
 * @property \Carbon\CarbonInterface|null $finished_at
 * @property int|null $exit_code
 * @property string|null $signal
 * @property int $duration_ms
 * @property int $stdout_bytes_pre
 * @property int $stdout_bytes_post
 * @property int $stderr_bytes_pre
 * @property int $stderr_bytes_post
 * @property string|null $error_summary
 * @property string|null $error_code
 * @property array|null $metadata_json
 * @property string|null $star_ab_group
 * @property \Carbon\CarbonInterface|null $created_at
 * @property \Carbon\CarbonInterface|null $updated_at
 * @property int|null $team_id
 * @property-read \App\Models\AgentJob|null $job
 * @property-read \App\Models\Team|null $team
 * @property-read \App\Models\User|null $user
 * @property-read \App\Models\User|null $initiatedBy
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\AgentRunEvent> $events
 * @property string|null $trigger
 *
 * @mixin \Illuminate\Database\Eloquent\Builder
 */
class AgentJobRun extends Model
{
    use HasFactory;

    public const STATUS_QUEUED = 'queued';

    public const STATUS_STARTING = 'starting';

    public const STATUS_RUNNING = 'running';

    public const STATUS_STOPPING = 'stopping';

    public const STATUS_SUCCEEDED = 'succeeded';

    public const STATUS_FAILED = 'failed';

    public const STATUS_KILLED = 'killed';

    public const STATUS_TIMED_OUT = 'timed_out';

    public const STATUS_SKIPPED = 'skipped';

    public const TRIGGER_SCHEDULE = 'schedule';

    public const TRIGGER_MANUAL = 'manual';

    public const ACTIVE_STATUSES = ['queued', 'starting', 'running', 'stopping'];

    public const TERMINAL_STATUSES = ['succeeded', 'failed', 'killed', 'timed_out', 'skipped'];

    protected $fillable = [
        'agent_job_id',
        'user_id',
        'initiated_by_user_id',
        'trigger_type',
        'due_window_utc_minute',
        'status',
        'pid',
        'resolved_executable_path',
        'started_at',
        'finished_at',
        'exit_code',
        'signal',
        'duration_ms',
        'stdout_bytes_pre',
        'stdout_bytes_post',
        'stderr_bytes_pre',
        'stderr_bytes_post',
        'error_summary',
        'error_code',
        'metadata_json',
        'star_ab_group',
    ];

    protected function casts(): array
    {
        return [
            'due_window_utc_minute' => 'datetime',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'metadata_json' => 'array',
            'duration_ms' => 'integer',
            'stdout_bytes_pre' => 'integer',
            'stdout_bytes_post' => 'integer',
            'stderr_bytes_pre' => 'integer',
            'stderr_bytes_post' => 'integer',
        ];
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(AgentJob::class, 'agent_job_id');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function initiatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by_user_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(AgentRunEvent::class);
    }

    public function scopeLatest(Builder $query): void
    {
        $query->orderByDesc('created_at');
    }

    public function scopeActive(Builder $query): void
    {
        $query->whereIn('status', self::ACTIVE_STATUSES);
    }

    public function scopeForUser(Builder $query, \App\Models\User $user): void
    {
        $teamIds = $user->allTeams()->pluck('id');
        $jobIds = AgentJob::query()->forUser($user)->pluck('id');
        $query->where(function (Builder $q) use ($user, $teamIds, $jobIds): void {
            $q->where('user_id', $user->id)
                ->orWhereIn('team_id', $teamIds)
                ->orWhereIn('agent_job_id', $jobIds);
        });
    }
}
