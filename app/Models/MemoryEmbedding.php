<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property string $source_type
 * @property string $source_id
 * @property string $content
 * @property string $content_hash
 * @property array|null $metadata_json
 * @property string $classification
 * @property float $importance_score
 * @property int $access_count
 * @property \Carbon\CarbonInterface|null $last_accessed_at
 * @property \Carbon\CarbonInterface|null $created_at
 * @property mixed $embedding
 * @property-read \App\Models\User|null $user
 * @property int|null $duplicate_count
 *
 * @mixin \Illuminate\Database\Eloquent\Builder
 */
class MemoryEmbedding extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'source_type',
        'source_id',
        'content',
        'content_hash',
        'metadata_json',
        'classification',
        'importance_score',
        'access_count',
        'last_accessed_at',
        'created_at',
    ];

    /**
     * The name of the "updated at" column.
     * This table only has created_at.
     */
    public const UPDATED_AT = null;

    /**
     * Source type constants.
     */
    public const SOURCE_CONVERSATION = 'conversation';

    public const SOURCE_CORE_BLOCK = 'core_block';

    public const SOURCE_DOCUMENT = 'document';

    public const SOURCE_ENTITY = 'entity';

    protected function casts(): array
    {
        return [
            'metadata_json' => 'array',
            'importance_score' => 'float',
            'access_count' => 'integer',
            'last_accessed_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope to filter by user.
     */
    public function scopeForUser(Builder $query, int $userId): void
    {
        $query->where('user_id', $userId);
    }

    /**
     * Scope to filter by classification level(s).
     *
     * @param  string|array<string>  $classifications
     */
    public function scopeWithClassification(Builder $query, string|array $classifications): void
    {
        $query->whereIn('classification', (array) $classifications);
    }

    /**
     * Scope to filter by source type.
     */
    public function scopeOfSourceType(Builder $query, string $sourceType): void
    {
        $query->where('source_type', $sourceType);
    }

    /**
     * Scope to filter by minimum importance score.
     */
    public function scopeAboveImportance(Builder $query, float $threshold): void
    {
        $query->where('importance_score', '>=', $threshold);
    }

    /**
     * Scope to filter by content hash.
     */
    public function scopeByContentHash(Builder $query, string $hash): void
    {
        $query->where('content_hash', $hash);
    }

    /**
     * Generate content hash for deduplication.
     */
    public static function generateContentHash(string $content): string
    {
        return hash('sha256', $content);
    }

    /**
     * Record an access to this embedding (for decay calculations).
     */
    public function recordAccess(): void
    {
        $this->increment('access_count');
        $this->update(['last_accessed_at' => now()]);
    }

    /**
     * Calculate decay score based on importance, recency, and access frequency.
     *
     * Formula: importance × recency_decay × access_frequency_bonus
     */
    public function calculateDecayScore(): float
    {
        $importance = $this->importance_score ?? 0.5;

        // Recency decay: half-life of 30 days
        // Use absolute value since diffInDays returns negative for past dates
        $daysSinceAccess = $this->last_accessed_at
            ? abs(now()->diffInDays($this->last_accessed_at))
            : abs(now()->diffInDays($this->created_at));
        $recencyDecay = pow(0.5, $daysSinceAccess / 30);

        // Access frequency bonus: log scale
        $accessBonus = 1 + (log10(max(1, $this->access_count ?? 0)) * 0.1);

        return $importance * $recencyDecay * $accessBonus;
    }

    /**
     * Check if embedding exists by content hash.
     */
    public static function existsByContentHash(int $userId, string $content): bool
    {
        return static::query()
            ->where('user_id', $userId)
            ->where('content_hash', self::generateContentHash($content))
            ->exists();
    }

    /**
     * Create or get existing embedding by content hash.
     *
     * Returns [model, created] tuple.
     *
     * @return array{MemoryEmbedding, bool}
     */
    public static function createOrGetByContentHash(int $userId, string $content, array $attributes = []): array
    {
        $hash = self::generateContentHash($content);

        $existing = static::query()
            ->where('user_id', $userId)
            ->where('content_hash', $hash)
            ->first();

        if ($existing) {
            return [$existing, false];
        }

        $model = static::create(array_merge($attributes, [
            'user_id' => $userId,
            'content' => $content,
            'content_hash' => $hash,
            'created_at' => now(),
        ]));

        return [$model, true];
    }
}
