<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentRunEvent extends Model
{
    public const REASONING_STEPS = ['situation', 'task', 'action', 'result'];

    protected $guarded = [];

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
