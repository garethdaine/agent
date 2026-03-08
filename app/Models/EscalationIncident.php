<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Telemetry\ProjectionTable;
use Illuminate\Database\Eloquent\Model;

class EscalationIncident extends Model
{
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
