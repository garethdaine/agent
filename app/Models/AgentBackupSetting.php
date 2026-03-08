<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AgentBackupSetting extends Model
{
    public const STATUS_SUCCEEDED = 'succeeded';

    public const STATUS_FAILED = 'failed';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'run_hour' => 'integer',
            'run_minute' => 'integer',
            'retention_days' => 'integer',
            'last_run_at' => 'immutable_datetime',
        ];
    }

    public static function current(): self
    {
        $setting = static::query()->first();

        if ($setting !== null) {
            return $setting;
        }

        return static::query()->create([
            'is_enabled' => true,
            'timezone' => 'UTC',
            'run_hour' => 2,
            'run_minute' => 0,
            'retention_days' => 14,
        ]);
    }
}
