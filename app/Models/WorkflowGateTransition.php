<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Telemetry\ProjectionTable;
use Illuminate\Database\Eloquent\Model;

class WorkflowGateTransition extends Model
{
    protected $fillable = [
        'workflow_key',
        'previous_gate_state',
        'new_gate_state',
        'source',
        'reason_code',
        'reason',
        'actor_id',
        'run_id',
        'projection_build_id',
        'metadata_json',
        'transitioned_at',
    ];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->setTable(ProjectionTable::qualified('workflow_gate_transitions'));
    }

    protected function casts(): array
    {
        return [
            'transitioned_at' => 'datetime',
            'metadata_json' => 'array',
        ];
    }
}
