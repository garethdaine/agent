<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $repo_analysis_session_id
 * @property int|null $repo_analysis_task_id
 * @property string $artifact_type
 * @property string $artifact_key
 * @property string $content_hash
 * @property string|null $schema_version
 * @property string|null $analyzer_version
 * @property string|null $storage_disk
 * @property string|null $storage_path
 * @property array|null $payload_json
 * @property array|null $metadata_json
 * @property string|null $error_code
 * @property string|null $error_summary
 * @property \Carbon\CarbonInterface|null $created_at
 * @property \Carbon\CarbonInterface|null $updated_at
 * @property-read \App\Models\RepoAnalysisSession|null $session
 * @property-read \App\Models\RepoAnalysisTask|null $task
 *
 * @mixin \Illuminate\Database\Eloquent\Builder
 */
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
