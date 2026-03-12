<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $delegation_task_id
 * @property int $delegatee_profile_id
 * @property int|null $agent_job_run_id
 * @property int $attempt_number
 * @property string $status
 * @property \Carbon\CarbonInterface|null $started_at
 * @property \Carbon\CarbonInterface|null $finished_at
 * @property int $duration_ms
 * @property string|null $error_code
 * @property string|null $error_summary
 * @property array|null $metadata_json
 * @property \Carbon\CarbonInterface|null $created_at
 * @property \Carbon\CarbonInterface|null $updated_at
 * @property-read \App\Models\DelegationTask|null $task
 * @property-read \App\Models\DelegateeProfile|null $profile
 * @property-read \App\Models\AgentJobRun|null $agentJobRun
 *
 * @mixin \Illuminate\Database\Eloquent\Builder
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
