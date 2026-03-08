<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $repo_analysis_session_id
 * @property string $task_key
 * @property string $task_type
 * @property string $status
 * @property int|null $phase
 * @property array|null $depends_on_json
 * @property array|null $artifact_ids_json
 * @property string|null $input_hash
 * @property string|null $output_hash
 * @property string|null $analyzer_name
 * @property string|null $analyzer_version
 * @property int $attempt_count
 * @property int $max_attempts
 * @property string|null $error_code
 * @property string|null $error_summary
 * @property array|null $metadata_json
 * @property \Carbon\CarbonInterface|null $started_at
 * @property \Carbon\CarbonInterface|null $finished_at
 * @property \Carbon\CarbonInterface|null $created_at
 * @property \Carbon\CarbonInterface|null $updated_at
 * @property-read \App\Models\RepoAnalysisSession|null $session
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\RepoAnalysisArtifact> $artifacts
 *
 * @mixin \Illuminate\Database\Eloquent\Builder
 */
class RepoAnalysisTask extends Model
{
    protected $fillable = [
        'repo_analysis_session_id',
        'task_key',
        'task_type',
        'status',
        'phase',
        'depends_on_json',
        'artifact_ids_json',
        'input_hash',
        'output_hash',
        'analyzer_name',
        'analyzer_version',
        'attempt_count',
        'max_attempts',
        'error_code',
        'error_summary',
        'metadata_json',
        'started_at',
        'finished_at',
    ];

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
