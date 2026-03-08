<?php

declare(strict_types=1);

namespace App\Http\Resources\Connectors;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConnectionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'connector' => new ConnectorResource($this->whenLoaded('connector')),
            'status' => $this->status,
            'health_score' => (float) $this->health_score,
            'connected_at' => $this->connected_at?->toIso8601String(),
            'last_action_at' => $this->last_action_at?->toIso8601String(),
            'action_count_24h' => $this->action_count_24h,
            'error_count_24h' => $this->error_count_24h,
        ];
    }
}
