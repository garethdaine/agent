<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin Builder
 */
class DelegationAttempt extends Model
{
    use HasFactory;

    protected $fillable = [
        'delegation_task_id',
        'delegatee_profile_id',
        'agent_job_run_id',
        'attempt_number',
        'status',
        'started_at',
        'finished_at',
        'duration_ms',
        'error_code',
        'error_summary',
        'metadata_json',
    ];

    public const STATUS_RUNNING = 'running';

    public const STATUS_SUCCEEDED = 'succeeded';

    public const STATUS_FAILED = 'failed';

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'duration_ms' => 'integer',
            'attempt_number' => 'integer',
            'metadata_json' => 'array',
        ];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(DelegationTask::class, 'delegation_task_id');
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(DelegateeProfile::class, 'delegatee_profile_id');
    }

    public function agentJobRun(): BelongsTo
    {
        return $this->belongsTo(AgentJobRun::class, 'agent_job_run_id');
    }
}
