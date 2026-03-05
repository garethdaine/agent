<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

class CredentialVault extends Model
{
    use HasUuids;

    protected $table = 'credential_vault';

    protected $guarded = [];

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
        if ($this->encrypted_value === null || $this->encrypted_value === '') {
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
