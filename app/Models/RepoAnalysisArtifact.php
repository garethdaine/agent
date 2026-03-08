<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RepoAnalysisArtifact extends Model
{
    protected $fillable = [
        'repo_analysis_session_id',
        'repo_analysis_task_id',
        'artifact_type',
        'artifact_key',
        'content_hash',
        'schema_version',
        'analyzer_version',
        'storage_disk',
        'storage_path',
        'payload_json',
        'metadata_json',
        'error_code',
        'error_summary',
    ];

    protected $attributes = [
        'payload_json' => '{}',
        'metadata_json' => '{}',
    ];

    protected function casts(): array
    {
        return [
            'payload_json' => 'array',
            'metadata_json' => 'array',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(RepoAnalysisSession::class, 'repo_analysis_session_id');
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(RepoAnalysisTask::class, 'repo_analysis_task_id');
    }
}
