<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property bool $require_confirmation_for_delete
 * @property bool $require_confirmation_for_stop
 * @property bool $require_confirmation_for_steer
 * @property \Carbon\CarbonInterface|null $created_at
 * @property \Carbon\CarbonInterface|null $updated_at
 * @property-read \App\Models\User|null $user
 *
 * @mixin \Illuminate\Database\Eloquent\Builder
 */
class UserChatPreference extends Model
{
    protected $fillable = [
        'user_id',
        'require_confirmation_for_delete',
        'require_confirmation_for_stop',
        'require_confirmation_for_steer',
    ];

    protected $attributes = [
        'require_confirmation_for_delete' => true,
        'require_confirmation_for_stop' => true,
        'require_confirmation_for_steer' => false,
    ];

    protected function casts(): array
    {
        return [
            'require_confirmation_for_delete' => 'boolean',
            'require_confirmation_for_stop' => 'boolean',
            'require_confirmation_for_steer' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function requiresConfirmationFor(string $action): bool
    {
        return match ($action) {
            'delete' => $this->require_confirmation_for_delete,
            'stop' => $this->require_confirmation_for_stop,
            'steer' => $this->require_confirmation_for_steer,
            default => false,
        };
    }
}
