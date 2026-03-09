<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $user_id
 * @property string|null $name
 * @property string $runner_type
 * @property string $project_directory
 * @property string $interrogation_type
 * @property string|null $feature_brief
 * @property string $status
 * @property int $phase
 * @property string|null $cli_session_id
 * @property array|null $summary_json
 * @property array|null $plan_json
 * @property array|null $annotations_json
 * @property array|null $metadata_json
 * @property string|null $error_code
 * @property string|null $error_summary
 * @property \Carbon\CarbonInterface|null $started_at
 * @property \Carbon\CarbonInterface|null $finished_at
 * @property \Carbon\CarbonInterface|null $approved_at
 * @property \Carbon\CarbonInterface|null $deleted_at
 * @property \Carbon\CarbonInterface|null $created_at
 * @property \Carbon\CarbonInterface|null $updated_at
 * @property string|null $model
 * @property-read \App\Models\User|null $user
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\InterrogationEvent> $events
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\InterrogationBuildTask> $buildTasks
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\InterrogationTechStack> $techStacks
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\ConnectedProvider> $providerIntegrations
 *
 * @mixin \Illuminate\Database\Eloquent\Builder
 */
class InterrogationSession extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'name',
        'runner_type',
        'project_directory',
        'interrogation_type',
        'feature_brief',
        'status',
        'phase',
        'cli_session_id',
        'summary_json',
        'plan_json',
        'annotations_json',
        'metadata_json',
        'error_code',
        'error_summary',
        'started_at',
        'finished_at',
        'approved_at',
    ];

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

    /** @return HasMany<InterrogationEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(InterrogationEvent::class);
    }

    /** @return HasMany<InterrogationBuildTask, $this> */
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

    /**
     * @return array{commit_enabled: bool, conventional_commits: bool, worktree_enabled: bool, branching_enabled: bool, branch_prefix: ?string, target_branch: ?string, updated_at: ?string}
     */
    public function gitSettings(): array
    {
        $metadata = is_array($this->metadata_json) ? $this->metadata_json : [];
        $git = is_array($metadata['git'] ?? null) ? $metadata['git'] : [];

        return [
            'commit_enabled' => (bool) ($git['commit_enabled'] ?? false),
            'conventional_commits' => (bool) ($git['conventional_commits'] ?? false),
            'worktree_enabled' => (bool) ($git['worktree_enabled'] ?? false),
            'branching_enabled' => (bool) ($git['branching_enabled'] ?? false),
            'branch_prefix' => is_string($git['branch_prefix'] ?? null) ? $git['branch_prefix'] : null,
            'target_branch' => is_string($git['target_branch'] ?? null) ? $git['target_branch'] : null,
            'updated_at' => $git['updated_at'] ?? null,
        ];
    }

    /**
     * @return array{auto_advance_tasks: bool, updated_at: ?string}
     */
    public function buildSettings(): array
    {
        $metadata = is_array($this->metadata_json) ? $this->metadata_json : [];
        $settings = is_array($metadata['build_settings'] ?? null) ? $metadata['build_settings'] : [];

        return [
            'auto_advance_tasks' => (bool) ($settings['auto_advance_tasks'] ?? true),
            'updated_at' => $settings['updated_at'] ?? null,
        ];
    }
}
