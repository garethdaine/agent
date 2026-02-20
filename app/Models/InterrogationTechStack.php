<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin Builder
 */
class InterrogationTechStack extends Model
{
    protected $guarded = [];

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
