<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin Builder
 */
class DelegateeMetric extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'window_24h_json' => 'array',
            'window_7d_json' => 'array',
            'last_recomputed_at' => 'datetime',
        ];
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(DelegateeProfile::class, 'delegatee_profile_id');
    }

    /**
     * Alias for profile() to support Laravel factory's automatic relationship resolution.
     *
     * Laravel factories look for relationship methods named after the FK column (without _id).
     */
    public function delegateeProfile(): BelongsTo
    {
        return $this->profile();
    }
}
