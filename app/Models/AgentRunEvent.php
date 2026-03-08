<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $agent_job_run_id
 * @property string $event_type
 * @property int $sequence
 * @property string $payload
 * @property string|null $reasoning_step
 * @property \Carbon\CarbonInterface|null $event_ts
 * @property \Carbon\CarbonInterface|null $created_at
 * @property \Carbon\CarbonInterface|null $updated_at
 * @property-read \App\Models\AgentJobRun|null $run
 *
 * @mixin \Illuminate\Database\Eloquent\Builder
 */
class AgentRunEvent extends Model
{
    public const REASONING_STEPS = ['situation', 'task', 'action', 'result'];

    protected $fillable = [
        'agent_job_run_id',
        'event_type',
        'sequence',
        'payload',
        'reasoning_step',
        'event_ts',
    ];

    protected function casts(): array
    {
        return [
            'event_ts' => 'datetime',
            'sequence' => 'integer',
        ];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(AgentJobRun::class, 'agent_job_run_id');
    }
}
