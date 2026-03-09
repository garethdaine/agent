<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property int $id
 * @property int $user_id
 * @property string $providerable_type
 * @property int $providerable_id
 * @property string $category
 * @property string $driver
 * @property string|null $provider_user_id
 * @property string|null $provider_workspace_id
 * @property string|null $provider_workspace_name
 * @property string|null $access_token
 * @property string|null $refresh_token
 * @property string|null $token_type
 * @property \Carbon\CarbonInterface|null $expires_at
 * @property array|null $scopes_json
 * @property array|null $metadata_json
 * @property \Carbon\CarbonInterface|null $created_at
 * @property \Carbon\CarbonInterface|null $updated_at
 * @property-read \App\Models\User|null $user
 *
 * @mixin \Illuminate\Database\Eloquent\Builder
 */
class ConnectedProvider extends Model
{
    use HasFactory;

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
