<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Telemetry\ProjectionTable;
use Illuminate\Database\Eloquent\Model;

class WorkflowGateTransition extends Model
{
    protected $guarded = [];

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
