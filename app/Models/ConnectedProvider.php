<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @mixin Builder
 */
class ConnectedProvider extends Model
{
    protected $table = 'providers';

    protected $fillable = [
        'user_id',
        'providerable_type',
        'providerable_id',
        'category',
        'driver',
        'provider_user_id',
        'provider_workspace_id',
        'provider_workspace_name',
        'access_token',
        'refresh_token',
        'token_type',
        'expires_at',
        'scopes_json',
        'metadata_json',
    ];

    protected $hidden = [
        'access_token',
        'refresh_token',
    ];

    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'expires_at' => 'datetime',
            'scopes_json' => 'array',
            'metadata_json' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function providerable(): MorphTo
    {
        return $this->morphTo();
    }
}
