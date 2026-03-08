<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $name
 * @property string $display_name
 * @property string $description
 * @property string $category
 * @property array $industries
 * @property string $version
 * @property string $auth_type
 * @property array|null $auth_config
 * @property string|null $base_url
 * @property array|null $rate_limits
 * @property string $cost_model
 * @property string $risk_level
 * @property array|null $actions
 * @property array $webhooks
 * @property string|null $icon_path
 * @property string|null $mcp_tool_prefix
 * @property string $status
 * @property \Carbon\CarbonInterface|null $created_at
 * @property \Carbon\CarbonInterface|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\AgentConnectorConnection> $connections
 *
 * @mixin \Illuminate\Database\Eloquent\Builder
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
