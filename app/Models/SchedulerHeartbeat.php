<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SchedulerHeartbeat extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'last_seen_at' => 'datetime',
            'meta_json' => 'array',
        ];
    }
}
