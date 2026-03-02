<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RepoAnalysisTask extends Model
{
    protected $guarded = [];

    protected $attributes = [
        'status' => 'pending',
        'attempt_count' => 0,
        'max_attempts' => 2,
        'depends_on_json' => '[]',
        'artifact_ids_json' => '[]',
        'metadata_json' => '{}',
    ];

    protected function casts(): array
    {
        return [
            'phase' => 'integer',
            'depends_on_json' => 'array',
            'artifact_ids_json' => 'array',
            'attempt_count' => 'integer',
            'max_attempts' => 'integer',
            'metadata_json' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(RepoAnalysisSession::class, 'repo_analysis_session_id');
    }

    public function artifacts(): HasMany
    {
        return $this->hasMany(RepoAnalysisArtifact::class, 'repo_analysis_task_id');
    }
}
