<?php

declare(strict_types=1);

namespace App\Http\Resources\Connectors;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\AgentConnectorConnection
 *
 * @property \App\Models\AgentConnectorConnection $resource
 */
class ConnectionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'connector' => new ConnectorResource($this->whenLoaded('connector')),
            'status' => $this->resource->status,
            'health_score' => (float) $this->resource->health_score,
            'connected_at' => $this->resource->connected_at?->toIso8601String(),
            'last_action_at' => $this->resource->last_action_at?->toIso8601String(),
            'action_count_24h' => $this->resource->action_count_24h,
            'error_count_24h' => $this->resource->error_count_24h,
        ];
    }
}
