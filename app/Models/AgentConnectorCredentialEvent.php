<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $credential_id
 * @property string $event_type
 * @property int|null $actor_id
 * @property array $details
 * @property \Carbon\CarbonInterface|null $created_at
 * @property-read \App\Models\AgentConnectorCredential|null $credential
 * @property-read \App\Models\User|null $actor
 *
 * @mixin \Illuminate\Database\Eloquent\Builder
 */
class AgentConnectorCredentialEvent extends Model
{
    use HasFactory, HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'credential_id',
        'event_type',
        'actor_id',
        'details',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'details' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function credential(): BelongsTo
    {
        return $this->belongsTo(AgentConnectorCredential::class, 'credential_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
