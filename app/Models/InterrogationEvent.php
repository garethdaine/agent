<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InterrogationEvent extends Model
{
    protected $guarded = [];

    public const TYPE_DISCOVERY_ACTIVITY = 'discovery_activity';

    public const TYPE_QUESTION = 'question';

    public const TYPE_ANSWER = 'answer';

    public const TYPE_PHASE_TRANSITION = 'phase_transition';

    public const TYPE_SUMMARY = 'summary';

    public const TYPE_PLAN = 'plan';

    public const TYPE_ERROR = 'error';

    public const TYPE_ANNOTATION = 'annotation';

    public const TYPE_SYSTEM = 'system';

    protected function casts(): array
    {
        return [
            'event_ts' => 'datetime',
            'sequence' => 'integer',
            'payload' => 'array',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(InterrogationSession::class, 'interrogation_session_id');
    }
}
