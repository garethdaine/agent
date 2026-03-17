<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class WorkflowInterrogationSession extends Model
{
    use HasFactory;
    use SoftDeletes;

    public const STATUS_SETUP = 'setup';

    public const STATUS_INTERROGATING = 'interrogating';

    public const STATUS_SUMMARY_READY = 'summary_ready';

    public const STATUS_PLANNING = 'planning';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    public const STATUS_PAUSED = 'paused';

    public const PHASE_SETUP = 0;

    public const PHASE_INTERROGATION = 1;

    public const PHASE_SUMMARY = 2;

    public const PHASE_ACTION_PLAN = 3;

    public const PHASE_COMPLETED = 4;

    public const MODE_WORKFLOW = 'workflow';

    public const MODE_GENERAL = 'general';

    protected $fillable = [
        'user_id',
        'name',
        'runner_type',
        'model',
        'project_directory',
        'interrogation_mode',
        'company_name',
        'company_description',
        'workflow_title',
        'workflow_brief',
        'target_teams_json',
        'systems_json',
        'status',
        'phase',
        'current_round',
        'cli_session_id',
        'summary_json',
        'action_plan_json',
        'metadata_json',
        'error_code',
        'error_summary',
        'started_at',
        'finished_at',
        'summary_confirmed_at',
    ];

    protected function casts(): array
    {
        return [
            'target_teams_json' => 'array',
            'systems_json' => 'array',
            'summary_json' => 'array',
            'action_plan_json' => 'array',
            'metadata_json' => 'array',
            'phase' => 'integer',
            'current_round' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'summary_confirmed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(WorkflowInterrogationEvent::class)->orderBy('sequence');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(WorkflowInterrogationAttachment::class)
            ->orderBy('created_at');
    }

    public function batches(): HasMany
    {
        return $this->hasMany(WorkflowInterrogationBatch::class)
            ->orderByDesc('round')
            ->orderByDesc('id');
    }

    public function activeBatch(): HasOne
    {
        return $this->hasOne(WorkflowInterrogationBatch::class)
            ->where('is_active', true)
            ->latestOfMany('round');
    }

    public function scopeForUser(Builder $query, int $userId): void
    {
        $query->where('user_id', $userId);
    }

    public function scopeLatest(Builder $query): void
    {
        $query->orderByDesc('updated_at');
    }
}
