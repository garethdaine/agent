<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AgentJobRun extends Model
{
    public const ACTIVE_STATUSES = ['queued', 'starting', 'running', 'stopping'];

    public const TERMINAL_STATUSES = ['succeeded', 'failed', 'killed', 'timed_out', 'skipped'];

    protected $guarded = [];

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
}
