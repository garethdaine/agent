<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Core Memory block (Layer 1).
 *
 * Stores identity and operational blocks with version tracking.
 * Supports classification levels: public, internal, confidential.
 */
class MemoryCoreBlock extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'job_id',
        'block_type',
        'block_key',
        'content_text',
        'content_json',
        'classification',
        'version',
    ];

    /**
     * Block type constants.
     */
    public const TYPE_IDENTITY = 'identity';

    public const TYPE_OPERATIONAL = 'operational';

    /**
     * Classification constants.
     */
    public const CLASSIFICATION_PUBLIC = 'public';

    public const CLASSIFICATION_INTERNAL = 'internal';

    public const CLASSIFICATION_CONFIDENTIAL = 'confidential';

    /**
     * Valid block types.
     *
     * @var array<string>
     */
    public static array $validBlockTypes = [
        self::TYPE_IDENTITY,
        self::TYPE_OPERATIONAL,
    ];

    /**
     * Valid classifications.
     *
     * @var array<string>
     */
    public static array $validClassifications = [
        self::CLASSIFICATION_PUBLIC,
        self::CLASSIFICATION_INTERNAL,
        self::CLASSIFICATION_CONFIDENTIAL,
    ];

    protected function casts(): array
    {
        return [
            'content_json' => 'array',
            'version' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(AgentJob::class, 'job_id');
    }

    /**
     * Scope to filter by user.
     */
    public function scopeForUser(Builder $query, int $userId): void
    {
        $query->where('user_id', $userId);
    }

    /**
     * Scope to filter by block type.
     */
    public function scopeOfType(Builder $query, string $type): void
    {
        $query->where('block_type', $type);
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
     * Scope for global blocks (no job_id).
     */
    public function scopeGlobal(Builder $query): void
    {
        $query->whereNull('job_id');
    }

    /**
     * Scope for job-specific blocks.
     */
    public function scopeForJob(Builder $query, int $jobId): void
    {
        $query->where('job_id', $jobId);
    }

    /**
     * Check if this is an identity block.
     */
    public function isIdentityBlock(): bool
    {
        return $this->block_type === self::TYPE_IDENTITY;
    }

    /**
     * Check if this is an operational block.
     */
    public function isOperationalBlock(): bool
    {
        return $this->block_type === self::TYPE_OPERATIONAL;
    }

    /**
     * Get the content (text or JSON based on block type).
     */
    public function getContent(): mixed
    {
        return $this->content_json ?? $this->content_text;
    }

    /**
     * Set the content (automatically determines storage field).
     */
    public function setContent(mixed $content): self
    {
        if (is_array($content) || is_object($content)) {
            $this->content_json = $content;
            $this->content_text = null;
        } else {
            $this->content_text = $content;
            $this->content_json = null;
        }

        return $this;
    }

    /**
     * Increment the version number.
     */
    public function incrementVersion(): self
    {
        $this->version = ($this->version ?? 0) + 1;

        return $this;
    }

    /**
     * Validate the block type.
     */
    public static function isValidBlockType(string $type): bool
    {
        return in_array($type, self::$validBlockTypes, true);
    }

    /**
     * Validate the classification.
     */
    public static function isValidClassification(string $classification): bool
    {
        return in_array($classification, self::$validClassifications, true);
    }

    /**
     * Check if classification can be changed from current to target.
     *
     * Agents can only elevate (internal -> confidential), not downgrade.
     */
    public static function canChangeClassification(string $current, string $target, bool $isAgent = false): bool
    {
        if (! $isAgent) {
            return self::isValidClassification($target);
        }

        $order = [
            self::CLASSIFICATION_PUBLIC => 0,
            self::CLASSIFICATION_INTERNAL => 1,
            self::CLASSIFICATION_CONFIDENTIAL => 2,
        ];

        $currentOrder = $order[$current] ?? 1;
        $targetOrder = $order[$target] ?? 1;

        // Agents can only elevate, not downgrade
        return $targetOrder >= $currentOrder;
    }
}
