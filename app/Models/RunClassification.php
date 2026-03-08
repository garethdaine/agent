<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Telemetry\ProjectionTable;
use Illuminate\Database\Eloquent\Model;

class RunClassification extends Model
{
    protected $fillable = [
        'run_id',
        'workflow_key',
        'classification',
        'weight',
        'hard_fail',
        'classification_reason_code',
        'failure_class',
        'failure_reason_code',
        'verification_completed_at',
        'assisted_sla_expires_at',
        'classified_at',
        'reclassified_at',
        'metadata_json',
        'projection_build_id',
    ];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->setTable(ProjectionTable::qualified('run_classifications'));
    }

    protected function casts(): array
    {
        return [
            'weight' => 'float',
            'hard_fail' => 'boolean',
            'verification_completed_at' => 'datetime',
            'assisted_sla_expires_at' => 'datetime',
            'classified_at' => 'datetime',
            'reclassified_at' => 'datetime',
            'metadata_json' => 'array',
        ];
    }
}
