<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InterrogationBuildTask extends Model
{
    protected $guarded = [];

    public const STATUS_PENDING = 'pending';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_BLOCKED = 'blocked';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    public const STATUS_SKIPPED = 'skipped';

    public const ACTIVE_STATUSES = [
        self::STATUS_IN_PROGRESS,
    ];

    public const TERMINAL_STATUSES = [
        self::STATUS_COMPLETED,
        self::STATUS_FAILED,
        self::STATUS_SKIPPED,
    ];

    protected function casts(): array
    {
        return [
            'sequence' => 'integer',
            'attempt_count' => 'integer',
            'metadata_json' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(InterrogationSession::class, 'interrogation_session_id');
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(AgentJobRun::class, 'agent_job_run_id');
    }

    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sequence')->orderBy('id');
    }
}
