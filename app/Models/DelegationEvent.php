<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin Builder
 */
class DelegationEvent extends Model
{
    use HasFactory;

    protected $guarded = [];

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
