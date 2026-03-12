<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $delegatee_profile_id
 * @property array|null $window_24h_json
 * @property array|null $window_7d_json
 * @property \Carbon\CarbonInterface|null $last_recomputed_at
 * @property \Carbon\CarbonInterface|null $created_at
 * @property \Carbon\CarbonInterface|null $updated_at
 * @property-read \App\Models\DelegateeProfile|null $profile
 *
 * @mixin \Illuminate\Database\Eloquent\Builder
 */
class DelegateeMetric extends Model
{
    use HasFactory;

    protected $fillable = [
        'delegatee_profile_id',
        'window_24h_json',
        'window_7d_json',
        'last_recomputed_at',
    ];

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
