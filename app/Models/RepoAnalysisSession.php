<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $user_id
 * @property string|null $name
 * @property string $project_directory
 * @property string $analyzer_profile
 * @property string $runner_type
 * @property string $status
 * @property int $phase
 * @property string|null $snapshot_hash
 * @property array|null $manifest_stats_json
 * @property array|null $report_summary_json
 * @property array|null $metadata_json
 * @property string|null $error_code
 * @property string|null $error_summary
 * @property \Carbon\CarbonInterface|null $started_at
 * @property \Carbon\CarbonInterface|null $finished_at
 * @property \Carbon\CarbonInterface|null $deleted_at
 * @property \Carbon\CarbonInterface|null $created_at
 * @property \Carbon\CarbonInterface|null $updated_at
 * @property-read \App\Models\User|null $user
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\RepoAnalysisEvent> $events
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\RepoAnalysisTask> $tasks
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\RepoAnalysisArtifact> $artifacts
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\RepoAnalysisReport> $reports
 *
 * @mixin \Illuminate\Database\Eloquent\Builder
 */
class RepoAnalysisSession extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'name',
        'project_directory',
        'analyzer_profile',
        'runner_type',
        'status',
        'phase',
        'snapshot_hash',
        'manifest_stats_json',
        'report_summary_json',
        'metadata_json',
        'error_code',
        'error_summary',
        'started_at',
        'finished_at',
    ];

    protected $attributes = [
        'analyzer_profile' => 'default',
        'runner_type' => 'claude',
        'status' => 'setup',
        'phase' => 0,
        'manifest_stats_json' => '{}',
        'report_summary_json' => '{}',
        'metadata_json' => '{}',
    ];

    protected function casts(): array
    {
        return [
            'phase' => 'integer',
            'manifest_stats_json' => 'array',
            'report_summary_json' => 'array',
            'metadata_json' => 'array',
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
        return $this->hasMany(RepoAnalysisEvent::class)->orderBy('sequence')->orderBy('id');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(RepoAnalysisTask::class)->orderBy('id');
    }

    public function artifacts(): HasMany
    {
        return $this->hasMany(RepoAnalysisArtifact::class)->orderBy('id');
    }

    public function reports(): HasMany
    {
        return $this->hasMany(RepoAnalysisReport::class)->orderByDesc('created_at');
    }

    public function scopeForUser(Builder $query, int $userId): void
    {
        $query->where('user_id', $userId);
    }
}
