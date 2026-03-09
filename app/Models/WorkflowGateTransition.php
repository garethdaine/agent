<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Telemetry\ProjectionTable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $workflow_key
 * @property string|null $previous_gate_state
 * @property string $new_gate_state
 * @property string $source
 * @property string|null $reason_code
 * @property string|null $reason
 * @property string|null $actor_id
 * @property string|null $run_id
 * @property string|null $projection_build_id
 * @property array|null $metadata_json
 * @property \Carbon\CarbonInterface|null $transitioned_at
 * @property \Carbon\CarbonInterface|null $created_at
 * @property \Carbon\CarbonInterface|null $updated_at
 *
 * @mixin \Illuminate\Database\Eloquent\Builder
 */
class WorkflowGateTransition extends Model
{
    use HasFactory;

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
