<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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
