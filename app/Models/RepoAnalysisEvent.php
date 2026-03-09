<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $repo_analysis_session_id
 * @property int $sequence
 * @property string $event_type
 * @property array|null $payload_json
 * @property \Carbon\CarbonInterface|null $event_ts
 * @property int|null $phase
 * @property string|null $status
 * @property string|null $error_code
 * @property string|null $error_summary
 * @property array|null $metadata_json
 * @property \Carbon\CarbonInterface|null $created_at
 * @property \Carbon\CarbonInterface|null $updated_at
 * @property-read \App\Models\RepoAnalysisSession|null $session
 *
 * @mixin \Illuminate\Database\Eloquent\Builder
 */
class RepoAnalysisEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'repo_analysis_session_id',
        'sequence',
        'event_type',
        'payload_json',
        'event_ts',
        'phase',
        'status',
        'error_code',
        'error_summary',
        'metadata_json',
    ];

    protected $attributes = [
        'payload_json' => '{}',
        'metadata_json' => '{}',
    ];

    protected function casts(): array
    {
        return [
            'sequence' => 'integer',
            'phase' => 'integer',
            'payload_json' => 'array',
            'metadata_json' => 'array',
            'event_ts' => 'datetime',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(RepoAnalysisSession::class, 'repo_analysis_session_id');
    }

    public function scopeForSession(Builder $query, int $sessionId): void
    {
        $query->where('repo_analysis_session_id', $sessionId);
    }

    public function scopeSinceSequence(Builder $query, int $sinceSequence): void
    {
        $query->where('sequence', '>', max(0, $sinceSequence));
    }

    public function scopeOrderedSequence(Builder $query): void
    {
        $query->orderBy('sequence')->orderBy('id');
    }
}
