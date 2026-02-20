<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @mixin Builder
 */
class InterrogationSession extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    public const STATUS_SETUP = 'setup';

    public const STATUS_DISCOVERING = 'discovering';

    public const STATUS_INTERROGATING = 'interrogating';

    public const STATUS_SUMMARIZING = 'summarizing';

    public const STATUS_PLANNING = 'planning';

    public const STATUS_BUILD_RULES = 'build_rules';

    public const STATUS_BUILD_TASKS = 'build_tasks';

    public const STATUS_BUILD_EXECUTING = 'build_executing';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    public const STATUS_PAUSED = 'paused';

    public const ACTIVE_STATUSES = ['setup', 'discovering', 'interrogating', 'summarizing', 'planning', 'build_rules', 'build_tasks', 'build_executing'];

    public const TERMINAL_STATUSES = ['completed', 'failed'];

    public const RESUMABLE_STATUSES = ['paused', 'failed'];

    public const PHASE_SETUP = 0;

    public const PHASE_PROVIDER_SETUP = 1;

    public const PHASE_TECH_STACK_SETUP = 2;

    public const PHASE_DISCOVERY = 3;

    public const PHASE_INTERROGATION = 4;

    public const PHASE_SUMMARY = 5;

    public const PHASE_PLANNING = 6;

    public const PHASE_BUILD_RULES = 7;

    public const PHASE_BUILD_TASKS = 8;

    public const PHASE_BUILD_EXECUTION = 9;

    public const TYPE_FEATURE = 'feature';

    public const TYPE_GENERAL = 'general';

    protected function casts(): array
    {
        return [
            'phase' => 'integer',
            'summary_json' => 'array',
            'plan_json' => 'array',
            'annotations_json' => 'array',
            'metadata_json' => 'array',
            'approved_at' => 'datetime',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(InterrogationEvent::class);
    }

    public function buildTasks(): HasMany
    {
        return $this->hasMany(InterrogationBuildTask::class)->orderBy('sequence')->orderBy('id');
    }

    public function techStacks(): HasMany
    {
        return $this->hasMany(InterrogationTechStack::class)->orderBy('sequence')->orderBy('id');
    }

    public function providerIntegrations(): MorphMany
    {
        return $this->morphMany(ConnectedProvider::class, 'providerable');
    }

    public function scopeActive(Builder $query): void
    {
        $query->whereIn('status', self::ACTIVE_STATUSES);
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
