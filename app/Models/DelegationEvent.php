<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $delegation_graph_id
 * @property int|null $delegation_task_id
 * @property string $event_type
 * @property int $sequence
 * @property array|null $payload_json
 * @property \Carbon\CarbonInterface|null $event_ts
 * @property \Carbon\CarbonInterface|null $created_at
 * @property \Carbon\CarbonInterface|null $updated_at
 * @property-read \App\Models\DelegationGraph|null $graph
 * @property-read \App\Models\DelegationTask|null $task
 *
 * @mixin \Illuminate\Database\Eloquent\Builder
 */
class DelegationEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'delegation_graph_id',
        'delegation_task_id',
        'event_type',
        'sequence',
        'payload_json',
        'event_ts',
    ];

    protected function casts(): array
    {
        return [
            'payload_json' => 'array',
            'event_ts' => 'datetime',
            'sequence' => 'integer',
        ];
    }

    public function graph(): BelongsTo
    {
        return $this->belongsTo(DelegationGraph::class, 'delegation_graph_id');
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(DelegationTask::class, 'delegation_task_id');
    }
}
