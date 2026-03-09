<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Telemetry\ProjectionTable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $run_id
 * @property string $workflow_key
 * @property string $classification
 * @property float $weight
 * @property bool $hard_fail
 * @property string|null $classification_reason_code
 * @property string|null $failure_class
 * @property string|null $failure_reason_code
 * @property \Carbon\CarbonInterface|null $verification_completed_at
 * @property \Carbon\CarbonInterface|null $assisted_sla_expires_at
 * @property \Carbon\CarbonInterface|null $classified_at
 * @property \Carbon\CarbonInterface|null $reclassified_at
 * @property array|null $metadata_json
 * @property string|null $projection_build_id
 * @property \Carbon\CarbonInterface|null $created_at
 * @property \Carbon\CarbonInterface|null $updated_at
 *
 * @mixin \Illuminate\Database\Eloquent\Builder
 */
class RunClassification extends Model
{
    use HasFactory;

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
