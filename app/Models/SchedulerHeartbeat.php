<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $source
 * @property \Carbon\CarbonInterface|null $last_seen_at
 * @property array|null $meta_json
 *
 * @mixin \Illuminate\Database\Eloquent\Builder
 */
class SchedulerHeartbeat extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'source',
        'last_seen_at',
        'meta_json',
    ];

    protected function casts(): array
    {
        return [
            'last_seen_at' => 'datetime',
            'meta_json' => 'array',
        ];
    }
}
