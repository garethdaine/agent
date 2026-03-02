<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin Builder
 */
class OrgReportingEdge extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'subordinate_agent_id',
        'manager_agent_id',
        'user_id',
    ];

    public function subordinate(): BelongsTo
    {
        return $this->belongsTo(OrgAgentProfile::class, 'subordinate_agent_id');
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(OrgAgentProfile::class, 'manager_agent_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
