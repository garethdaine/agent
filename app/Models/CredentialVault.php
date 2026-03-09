<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

/**
 * @property string $id
 * @property int $user_id
 * @property string $provider
 * @property string $key
 * @property string $encrypted_value
 * @property array|null $metadata
 * @property \Carbon\CarbonInterface|null $created_at
 * @property \Carbon\CarbonInterface|null $updated_at
 * @property-read \App\Models\User|null $user
 *
 * @mixin \Illuminate\Database\Eloquent\Builder
 */
class CredentialVault extends Model
{
    use HasFactory;
    use HasUuids;

    protected $table = 'credential_vault';

    protected $fillable = [
        'user_id',
        'provider',
        'key',
        'encrypted_value',
        'metadata',
    ];

    protected $hidden = ['encrypted_value'];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getDecryptedValue(): ?string
    {
        if ($this->encrypted_value === null || $this->encrypted_value === '') { // @phpstan-ignore identical.alwaysFalse
            return null;
        }

        try {
            return Crypt::decryptString($this->encrypted_value);
        } catch (\Throwable $e) {
            Log::warning('CredentialVault: decryption failed', ['id' => $this->id, 'provider' => $this->provider, 'key' => $this->key]);

            return null;
        }
    }

    public function setDecryptedValue(string $value): void
    {
        $this->encrypted_value = Crypt::encryptString($value);
    }

    public static function redactForAudit(?string $value): string
    {
        if ($value === null || $value === '') {
            return '[empty]';
        }

        $len = strlen($value);
        if ($len <= 8) {
            return '***';
        }

        return substr($value, 0, 4).'…'.substr($value, -4);
    }
}
