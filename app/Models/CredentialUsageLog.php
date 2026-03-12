<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $credential_id
 * @property int $user_id
 * @property string $action
 * @property array $context
 * @property string $outcome
 * @property \Carbon\CarbonInterface|null $created_at
 * @property-read \App\Models\CredentialVault|null $credential
 * @property-read \App\Models\User|null $user
 *
 * @mixin \Illuminate\Database\Eloquent\Builder
 */
class CredentialUsageLog extends Model
{
    use HasFactory;
    use HasUuids;

    public const UPDATED_AT = null;

    public const ACTION_RETRIEVED = 'retrieved';

    public const ACTION_ROTATED = 'rotated';

    public const ACTION_CREATED = 'created';

    public const ACTION_UPDATED = 'updated';

    public const ACTION_DELETED = 'deleted';

    public const ACTION_APPROVAL_CHECKED = 'approval_checked';

    protected $fillable = [
        'credential_id',
        'user_id',
        'action',
        'context',
        'outcome',
    ];

    protected function casts(): array
    {
        return [
            'context' => 'array',
        ];
    }

    public function credential(): BelongsTo
    {
        return $this->belongsTo(CredentialVault::class, 'credential_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
