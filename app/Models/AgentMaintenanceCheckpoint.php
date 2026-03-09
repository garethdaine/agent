<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $domain
 * @property string $status
 * @property int|null $last_processed_id
 * @property int $processed_rows
 * @property array|null $progress_json
 * @property \Carbon\CarbonInterface|null $started_at
 * @property \Carbon\CarbonInterface|null $finished_at
 * @property \Carbon\CarbonInterface|null $created_at
 * @property \Carbon\CarbonInterface|null $updated_at
 *
 * @mixin \Illuminate\Database\Eloquent\Builder
 */
class AgentMaintenanceCheckpoint extends Model
{
    use HasFactory;

    protected $fillable = [
        'domain',
        'status',
        'last_processed_id',
        'processed_rows',
        'progress_json',
        'started_at',
        'finished_at',
    ];

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
