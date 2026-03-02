<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserNotificationSetting extends Model
{
    protected $fillable = ['user_id', 'channel', 'enabled'];

    protected $attributes = [
        'channel' => 'email',
        'enabled' => true,
    ];

    protected function casts(): array
    {
        return ['enabled' => 'boolean'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function getChannelOptions(): array
    {
        return ['email', 'in_app', 'both'];
    }
}
