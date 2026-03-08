<?php

declare(strict_types=1);

namespace App\Http\Resources\Connectors;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\AgentConnectorInvocation
 *
 * @property \App\Models\AgentConnectorInvocation $resource
 */
class InvocationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'action_name' => $this->resource->action_name,
            'http_method' => $this->resource->http_method,
            'http_status' => $this->resource->http_status,
            'duration_ms' => $this->resource->duration_ms,
            'outcome' => $this->resource->outcome,
            'created_at' => $this->resource->created_at?->toIso8601String(),
        ];
    }
}
