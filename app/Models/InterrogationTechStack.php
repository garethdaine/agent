<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $interrogation_session_id
 * @property int $sequence
 * @property string $name
 * @property string $documentation_url
 * @property array|null $metadata_json
 * @property \Carbon\CarbonInterface|null $created_at
 * @property \Carbon\CarbonInterface|null $updated_at
 * @property-read \App\Models\InterrogationSession|null $session
 *
 * @mixin \Illuminate\Database\Eloquent\Builder
 */
class InterrogationTechStack extends Model
{
    protected $fillable = [
        'interrogation_session_id',
        'sequence',
        'name',
        'documentation_url',
        'metadata_json',
    ];

    protected function casts(): array
    {
        return [
            'sequence' => 'integer',
            'metadata_json' => 'array',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(InterrogationSession::class, 'interrogation_session_id');
    }

    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sequence')->orderBy('id');
    }
}
