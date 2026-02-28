<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Dead letter record for failed messenger messages.
 *
 * Messages that exceed retry duration are moved here for manual inspection
 * and retry. Stores the original payload, error history, and attempt count.
 *
 * @mixin Builder
 *
 * @property int $id
 * @property string $connector_account_id
 * @property array $original_payload
 * @property string $error_message
 * @property array $error_history
 * @property int $attempts
 * @property \Carbon\Carbon $failed_at
 * @property \Carbon\Carbon|null $retried_at
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read ConnectorAccount $connectorAccount
 */
class MessengerDeadLetter extends Model
{
    protected $table = 'messenger_dead_letters';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'original_payload' => 'array',
            'error_history' => 'array',
            'attempts' => 'integer',
            'failed_at' => 'datetime',
            'retried_at' => 'datetime',
        ];
    }

    /**
     * Get the connector account this dead letter belongs to.
     */
    public function connectorAccount(): BelongsTo
    {
        return $this->belongsTo(ConnectorAccount::class);
    }

    /**
     * Scope to filter by connector account.
     */
    public function scopeForConnector(Builder $query, string $connectorAccountId): void
    {
        $query->where('connector_account_id', $connectorAccountId);
    }

    /**
     * Scope to order by most recently failed first.
     */
    public function scopeRecentFirst(Builder $query): void
    {
        $query->orderByDesc('failed_at');
    }

    /**
     * Scope to filter dead letters that have never been retried.
     */
    public function scopeNeverRetried(Builder $query): void
    {
        $query->whereNull('retried_at');
    }

    /**
     * Check if this dead letter has been retried at least once.
     */
    public function hasBeenRetried(): bool
    {
        return $this->retried_at !== null;
    }

    /**
     * Get a preview of the original payload content for display.
     */
    public function getPayloadPreview(int $maxLength = 100): string
    {
        $content = $this->original_payload['content'] ?? json_encode($this->original_payload);

        if (strlen($content) > $maxLength) {
            return substr($content, 0, $maxLength).'...';
        }

        return $content;
    }

    /**
     * Add a new error entry to the error history.
     */
    public function addErrorToHistory(string $error): void
    {
        $history = $this->error_history ?? [];
        $history[] = [
            'error' => $error,
            'at' => now()->toIso8601String(),
        ];

        $this->error_history = $history;
    }
}
