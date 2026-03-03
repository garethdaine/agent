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
 * @mixin Builder
 */
class RepoAnalysisSession extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $guarded = [];

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
