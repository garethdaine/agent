<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $delegatee_profile_id
 * @property float $score
 * @property array $components_json
 * @property \Carbon\CarbonInterface $calculated_at
 * @property string $reason
 * @property \Carbon\CarbonInterface|null $created_at
 * @property \Carbon\CarbonInterface|null $updated_at
 * @property-read \App\Models\DelegateeProfile|null $delegateeProfile
 *
 * @mixin \Illuminate\Database\Eloquent\Builder
 */
class TrustScoreHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'delegatee_profile_id',
        'score',
        'components_json',
        'calculated_at',
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'score' => 'decimal:2',
            'components_json' => 'array',
            'calculated_at' => 'datetime',
        ];
    }

    public function delegateeProfile(): BelongsTo
    {
        return $this->belongsTo(DelegateeProfile::class);
    }
}
