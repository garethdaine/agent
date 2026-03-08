<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @mixin Builder
 */
class AgentConnector extends Model
{
    use HasFactory, HasUuids;

    public const STATUS_AVAILABLE = 'available';

    public const STATUS_DEPRECATED = 'deprecated';

    public const STATUS_PARTNERSHIP_PENDING = 'partnership_pending';

    protected $fillable = [
        'name',
        'display_name',
        'description',
        'category',
        'industries',
        'version',
        'auth_type',
        'auth_config',
        'base_url',
        'rate_limits',
        'cost_model',
        'risk_level',
        'actions',
        'webhooks',
        'icon_path',
        'mcp_tool_prefix',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'industries' => 'array',
            'auth_config' => 'array',
            'rate_limits' => 'array',
            'actions' => 'array',
            'webhooks' => 'array',
        ];
    }

    public function connections(): HasMany
    {
        return $this->hasMany(AgentConnectorConnection::class, 'connector_id');
    }

    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_AVAILABLE);
    }

    public function scopeByCategory(Builder $query, string $category): Builder
    {
        return $query->where('category', $category);
    }
}
