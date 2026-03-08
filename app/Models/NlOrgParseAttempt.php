<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property int $user_id
 * @property string $raw_input
 * @property array|null $parsed_result
 * @property float|null $confidence
 * @property \Carbon\CarbonInterface|null $applied_at
 * @property \Carbon\CarbonInterface|null $created_at
 * @property \Carbon\CarbonInterface|null $updated_at
 * @property-read \App\Models\User|null $user
 *
 * @mixin \Illuminate\Database\Eloquent\Builder
 */
class NlOrgParseAttempt extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'raw_input',
        'parsed_result',
        'confidence',
        'applied_at',
    ];

    protected function casts(): array
    {
        return [
            'parsed_result' => 'array',
            'confidence' => 'float',
            'applied_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeForUser($query, string $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeUnapplied($query)
    {
        return $query->whereNull('applied_at');
    }

    public function scopeForIdempotency($query, string $userId, string $rawInput)
    {
        $window = config('agent.nl_parse.idempotency_window_seconds', 60);

        return $query
            ->where('user_id', $userId)
            ->where('raw_input', $rawInput)
            ->where('created_at', '>=', now()->subSeconds($window));
    }
}
