<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property bool $is_enabled
 * @property string $timezone
 * @property int $run_hour
 * @property int $run_minute
 * @property int $retention_days
 * @property \Carbon\CarbonInterface|null $last_run_at
 * @property string|null $last_status
 * @property string|null $last_error
 * @property int|null $updated_by_user_id
 * @property \Carbon\CarbonInterface|null $created_at
 * @property \Carbon\CarbonInterface|null $updated_at
 *
 * @mixin \Illuminate\Database\Eloquent\Builder
 */
class AgentBackupSetting extends Model
{
    public const STATUS_SUCCEEDED = 'succeeded';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'is_enabled',
        'timezone',
        'run_hour',
        'run_minute',
        'retention_days',
        'last_run_at',
        'last_status',
        'last_error',
        'updated_by_user_id',
    ];

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
