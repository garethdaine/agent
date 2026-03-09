<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Telemetry\ProjectionTable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $workflow_key
 * @property string $trigger_type
 * @property string $status
 * @property string|null $reason_code
 * @property string|null $reason
 * @property \Carbon\CarbonInterface|null $opened_at
 * @property \Carbon\CarbonInterface|null $investigating_at
 * @property \Carbon\CarbonInterface|null $resolved_at
 * @property \Carbon\CarbonInterface|null $last_triggered_at
 * @property string|null $projection_build_id
 * @property array|null $metadata_json
 * @property \Carbon\CarbonInterface|null $created_at
 * @property \Carbon\CarbonInterface|null $updated_at
 *
 * @mixin \Illuminate\Database\Eloquent\Builder
 */
class EscalationIncident extends Model
{
    use HasFactory;

    protected $fillable = [
        'workflow_key',
        'trigger_type',
        'status',
        'reason_code',
        'reason',
        'opened_at',
        'investigating_at',
        'resolved_at',
        'last_triggered_at',
        'projection_build_id',
        'metadata_json',
    ];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->setTable(ProjectionTable::qualified('escalation_incidents'));
    }

    protected function casts(): array
    {
        return [
            'opened_at' => 'datetime',
            'investigating_at' => 'datetime',
            'resolved_at' => 'datetime',
            'last_triggered_at' => 'datetime',
            'metadata_json' => 'array',
        ];
    }
}
