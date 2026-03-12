<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $delegation_graph_id
 * @property int $delegatee_profile_id
 * @property array|null $outcome_summary_json
 * @property array|null $success_patterns_json
 * @property array|null $failure_patterns_json
 * @property \Carbon\CarbonInterface|null $aggregated_at
 * @property \Carbon\CarbonInterface|null $created_at
 * @property \Carbon\CarbonInterface|null $updated_at
 * @property-read \App\Models\DelegationGraph|null $graph
 * @property-read \App\Models\DelegateeProfile|null $delegateeProfile
 *
 * @mixin \Illuminate\Database\Eloquent\Builder
 */
class DelegationLearning extends Model
{
    use HasFactory;

    protected $fillable = [
        'delegation_graph_id',
        'delegatee_profile_id',
        'outcome_summary_json',
        'success_patterns_json',
        'failure_patterns_json',
        'aggregated_at',
    ];

    protected function casts(): array
    {
        return [
            'outcome_summary_json' => 'array',
            'success_patterns_json' => 'array',
            'failure_patterns_json' => 'array',
            'aggregated_at' => 'datetime',
        ];
    }

    public function graph(): BelongsTo
    {
        return $this->belongsTo(DelegationGraph::class, 'delegation_graph_id');
    }

    public function delegateeProfile(): BelongsTo
    {
        return $this->belongsTo(DelegateeProfile::class, 'delegatee_profile_id');
    }
}
