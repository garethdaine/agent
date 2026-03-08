<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AgentMaintenanceCheckpoint extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'last_processed_id' => 'integer',
            'processed_rows' => 'integer',
            'progress_json' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }
}
