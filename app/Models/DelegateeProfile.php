<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @mixin Builder
 */
class DelegateeProfile extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'name',
        'runner_type',
        'command_template',
        'working_directory',
        'env_json',
        'config_json',
        'is_active',
        'soul_json',
    ];

    protected function casts(): array
    {
        return [
            'env_json' => 'array',
            'config_json' => 'array',
            'soul_json' => 'array',
            'is_active' => 'boolean',
            'trust_score' => 'decimal:2',
            'trust_updated_at' => 'datetime',
        ];
    }

    /**
     * Get the agent soul configuration.
     *
     * @return array{personality: string|null, system_prompt: string|null, user_context: string|null}
     */
    public function getSoul(): array
    {
        $soul = $this->soul_json ?? [];

        return [
            'personality' => $soul['personality'] ?? null,
            'system_prompt' => $soul['system_prompt'] ?? null,
            'user_context' => $soul['user_context'] ?? null,
        ];
    }

    private const SOUL_MAX_LENGTH = 10000;

    private const CREDENTIAL_PATTERNS = [
        '/\bsk-[a-zA-Z0-9]{20,}\b/',
        '/\bAKIA[A-Z0-9]{16}\b/',
        '/\b(?:password|passwd|pwd)\s*[:=]\s*\S+/i',
        '/\b(?:secret|token|credential)\s*[:=]\s*\S+/i',
        '/-----BEGIN [A-Z ]*PRIVATE KEY-----/m',
    ];

    /**
     * Update the agent soul configuration.
     *
     * @param  array<string, string|null>  $soul
     *
     * @throws \InvalidArgumentException
     */
    public function setSoul(array $soul): void
    {
        $fields = ['personality', 'system_prompt', 'user_context'];

        foreach ($fields as $field) {
            $value = $soul[$field] ?? null;
            if ($value === null || $value === '') {
                continue;
            }

            if (strlen($value) > self::SOUL_MAX_LENGTH) {
                throw new \InvalidArgumentException("Soul field '{$field}' exceeds maximum length of " . self::SOUL_MAX_LENGTH . ' characters.');
            }

            foreach (self::CREDENTIAL_PATTERNS as $pattern) {
                if (preg_match($pattern, $value)) {
                    throw new \InvalidArgumentException("Soul field '{$field}' contains credential-like content which is not allowed.");
                }
            }
        }

        $this->update([
            'soul_json' => array_filter([
                'personality' => $soul['personality'] ?? null,
                'system_prompt' => $soul['system_prompt'] ?? null,
                'user_context' => $soul['user_context'] ?? null,
            ], fn ($v) => $v !== null && $v !== '') ?: null,
        ]);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function capabilities(): BelongsToMany
    {
        return $this->belongsToMany(
            DelegationCapability::class,
            'delegatee_capabilities_pivot',
            'delegatee_profile_id',
            'delegation_capability_id'
        )->using(DelegateeCapabilityPivot::class);
    }

    public function metric(): HasOne
    {
        return $this->hasOne(DelegateeMetric::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->whereNull('deleted_at');
    }
}
