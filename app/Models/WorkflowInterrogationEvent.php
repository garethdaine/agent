<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkflowInterrogationEvent extends Model
{
    use HasFactory;

    public const TYPE_SYSTEM = 'system';

    public const TYPE_QUESTION_BATCH = 'question_batch';

    public const TYPE_ANSWER_BATCH = 'answer_batch';

    public const TYPE_SUMMARY = 'summary';

    public const TYPE_ACTION_PLAN = 'action_plan';

    public const TYPE_ERROR = 'error';

    protected $fillable = [
        'workflow_interrogation_session_id',
        'event_type',
        'sequence',
        'payload',
        'event_ts',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'sequence' => 'integer',
            'event_ts' => 'datetime',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(WorkflowInterrogationSession::class, 'workflow_interrogation_session_id');
    }
}
