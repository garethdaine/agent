<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

/**
 * @property string $id
 * @property int $user_id
 * @property string $provider
 * @property string $key
 * @property string $credential_type
 * @property string|null $name
 * @property string|null $url
 * @property string $encrypted_value
 * @property array|null $metadata
 * @property string|null $notes
 * @property string $approval_mode
 * @property \Carbon\CarbonInterface|null $last_used_at
 * @property \Carbon\CarbonInterface|null $last_rotated_at
 * @property \Carbon\CarbonInterface|null $expires_at
 * @property \Carbon\CarbonInterface|null $created_at
 * @property \Carbon\CarbonInterface|null $updated_at
 * @property-read \App\Models\User|null $user
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\CredentialUsageLog> $usageLogs
 *
 * @mixin \Illuminate\Database\Eloquent\Builder
 */
class CredentialVault extends Model
{
    use HasFactory;
    use HasUuids;

    public const TYPE_LOGIN = 'login';

    public const TYPE_API_KEY = 'api_key';

    public const TYPE_SSH_KEY = 'ssh_key';

    public const TYPE_OAUTH_TOKEN = 'oauth_token';

    public const APPROVAL_AUTONOMOUS = 'autonomous';

    public const APPROVAL_SUPERVISED = 'supervised';

    public const APPROVAL_RESTRICTED = 'restricted';

    public const VALID_TYPES = [
        self::TYPE_LOGIN,
        self::TYPE_API_KEY,
        self::TYPE_SSH_KEY,
        self::TYPE_OAUTH_TOKEN,
    ];

    public const VALID_APPROVAL_MODES = [
        self::APPROVAL_AUTONOMOUS,
        self::APPROVAL_SUPERVISED,
        self::APPROVAL_RESTRICTED,
    ];

    protected $table = 'credential_vault';

    protected $fillable = [
        'user_id',
        'provider',
        'key',
        'credential_type',
        'name',
        'url',
        'encrypted_value',
        'metadata',
        'notes',
        'approval_mode',
        'last_used_at',
        'last_rotated_at',
        'expires_at',
    ];

    protected $hidden = ['encrypted_value', 'notes'];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'last_used_at' => 'datetime',
            'last_rotated_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function usageLogs(): HasMany
    {
        return $this->hasMany(CredentialUsageLog::class, 'credential_id');
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

    /**
     * Decrypt the encrypted_value and JSON-decode into a structured array.
     */
    public function getDecryptedPayload(): ?array
    {
        $raw = $this->getDecryptedValue();
        if ($raw === null) {
            return null;
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * JSON-encode the payload and encrypt it.
     */
    public function setDecryptedPayload(array $data): void
    {
        $this->setDecryptedValue(json_encode($data, JSON_THROW_ON_ERROR));
    }

    /**
     * Decrypt the notes field.
     */
    public function getDecryptedNotes(): ?string
    {
        if ($this->notes === null || $this->notes === '') {
            return null;
        }

        try {
            return Crypt::decryptString($this->notes);
        } catch (\Throwable $e) {
            Log::warning('CredentialVault: notes decryption failed', ['id' => $this->id]);

            return null;
        }
    }

    /**
     * Encrypt and set the notes field.
     */
    public function setEncryptedNotes(?string $notes): void
    {
        $this->notes = $notes !== null ? Crypt::encryptString($notes) : null;
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function markUsed(): void
    {
        $this->update(['last_used_at' => now()]);
    }

    /**
     * Determine if the credential requires user approval given a trust score.
     *
     * Returns true if approval is needed, false if the credential can be used autonomously.
     */
    public function requiresApproval(float $trustScore): bool
    {
        $thresholds = config('credentials.trust_thresholds', []);

        return match ($this->approval_mode) {
            self::APPROVAL_AUTONOMOUS => $trustScore < ($thresholds['autonomous'] ?? 0.0),
            self::APPROVAL_SUPERVISED => $trustScore < ($thresholds['supervised'] ?? 0.7),
            self::APPROVAL_RESTRICTED => true,
            default => true,
        };
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('credential_type', $type);
    }

    /**
     * Active, non-expired credentials suitable for runtime use.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeForRuntime(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->whereNull('expires_at')
                ->orWhere('expires_at', '>', now());
        });
    }

    /**
     * Search by name, provider, or URL.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeSearchable(Builder $query, string $search): Builder
    {
        $term = '%'.mb_strtolower($search).'%';

        return $query->where(function (Builder $q) use ($term) {
            $q->whereRaw('LOWER(name) LIKE ?', [$term])
                ->orWhereRaw('LOWER(provider) LIKE ?', [$term])
                ->orWhereRaw('LOWER(url) LIKE ?', [$term]);
        });
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
