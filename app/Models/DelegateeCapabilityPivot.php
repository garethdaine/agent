<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * @property int $id
 * @property int $delegatee_profile_id
 * @property int $delegation_capability_id
 * @property \Carbon\CarbonInterface|null $created_at
 * @property \Carbon\CarbonInterface|null $updated_at
 * @property-read \App\Models\DelegateeProfile|null $delegateeProfile
 * @property-read \App\Models\DelegationCapability|null $capability
 *
 * @mixin \Illuminate\Database\Eloquent\Builder
 */
class DelegateeCapabilityPivot extends Pivot
{
    use HasFactory;

    protected $table = 'delegatee_capabilities_pivot';

    public function delegateeProfile(): BelongsTo
    {
        return $this->belongsTo(DelegateeProfile::class);
    }

    public function capability(): BelongsTo
    {
        return $this->belongsTo(DelegationCapability::class, 'delegation_capability_id');
    }
}
