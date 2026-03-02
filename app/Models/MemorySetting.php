<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Memory settings with encrypted value storage.
 *
 * Follows the InterrogationSetting pattern for user-scoped encrypted settings.
 * Supports global settings (user_id = null) for system-wide configuration.
 */
class MemorySetting extends Model
{
    protected $guarded = [];

    protected $hidden = [
        'value',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'encrypted',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get a setting value for a user.
     *
     * @param  int|null  $userId  User ID or null for global settings
     */
    public static function getForUser(?int $userId, string $key, mixed $default = null): mixed
    {
        $setting = static::query()
            ->where('user_id', $userId)
            ->where('key', $key)
            ->first();

        return $setting?->value ?? $default;
    }

    /**
     * Set a setting value for a user.
     *
     * @param  int|null  $userId  User ID or null for global settings
     */
    public static function setForUser(?int $userId, string $key, mixed $value): static
    {
        return static::query()->updateOrCreate(
            ['user_id' => $userId, 'key' => $key],
            ['value' => $value],
        );
    }

    /**
     * Get all settings for a user.
     *
     * @param  int|null  $userId  User ID or null for global settings
     * @return array<string, mixed>
     */
    public static function getAllForUser(?int $userId): array
    {
        return static::query()
            ->where('user_id', $userId)
            ->pluck('value', 'key')
            ->toArray();
    }

    /**
     * Delete a setting for a user.
     *
     * @param  int|null  $userId  User ID or null for global settings
     */
    public static function deleteForUser(?int $userId, string $key): bool
    {
        return static::query()
            ->where('user_id', $userId)
            ->where('key', $key)
            ->delete() > 0;
    }

    /**
     * Get a masked version of a setting value (for API keys).
     *
     * Shows only the last 4 characters.
     */
    public static function getMaskedForUser(?int $userId, string $key): ?string
    {
        $value = static::getForUser($userId, $key);

        if ($value === null || ! is_string($value)) {
            return null;
        }

        if (strlen($value) <= 4) {
            return str_repeat('*', strlen($value));
        }

        return str_repeat('*', strlen($value) - 4).substr($value, -4);
    }
}
