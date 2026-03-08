<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RepoAnalysisReport extends Model
{
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
