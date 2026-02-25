<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class DelegateeCapabilityPivot extends Pivot
{
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
