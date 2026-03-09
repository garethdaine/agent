<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $repo_analysis_session_id
 * @property string $report_version
 * @property string $report_hash
 * @property string $status
 * @property array|null $payload_json
 * @property array|null $metadata_json
 * @property string|null $markdown_export_path
 * @property string|null $json_export_path
 * @property string|null $error_code
 * @property string|null $error_summary
 * @property \Carbon\CarbonInterface|null $generated_at
 * @property \Carbon\CarbonInterface|null $created_at
 * @property \Carbon\CarbonInterface|null $updated_at
 * @property-read \App\Models\RepoAnalysisSession|null $session
 *
 * @mixin \Illuminate\Database\Eloquent\Builder
 */
class RepoAnalysisReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'repo_analysis_session_id',
        'report_version',
        'report_hash',
        'status',
        'payload_json',
        'metadata_json',
        'markdown_export_path',
        'json_export_path',
        'error_code',
        'error_summary',
        'generated_at',
    ];

    protected $attributes = [
        'status' => 'generated',
        'payload_json' => '{}',
        'metadata_json' => '{}',
    ];

    protected function casts(): array
    {
        return [
            'payload_json' => 'array',
            'metadata_json' => 'array',
            'generated_at' => 'datetime',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(RepoAnalysisSession::class, 'repo_analysis_session_id');
    }
}
