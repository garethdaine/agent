<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Telemetry\ProjectionTable;
use Illuminate\Database\Eloquent\Model;

class RunClassification extends Model
{
    protected $guarded = [];

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
