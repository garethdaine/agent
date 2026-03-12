<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $subordinate_agent_id
 * @property string $manager_agent_id
 * @property int $user_id
 * @property \Carbon\CarbonInterface|null $created_at
 * @property \Carbon\CarbonInterface|null $updated_at
 * @property-read \App\Models\OrgAgentProfile|null $subordinate
 * @property-read \App\Models\OrgAgentProfile|null $manager
 * @property-read \App\Models\User|null $user
 *
 * @mixin \Illuminate\Database\Eloquent\Builder
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
