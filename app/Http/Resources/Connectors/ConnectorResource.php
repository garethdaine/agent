<?php

declare(strict_types=1);

namespace App\Http\Resources\Connectors;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\AgentConnector
 */
class ConnectorResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var \App\Models\AgentConnector $resource */
        $resource = $this->resource;

        return [
            'id' => $resource->id,
            'name' => $resource->name,
            'display_name' => $resource->display_name,
            'description' => $resource->description,
            'category' => $resource->category,
            'industries' => $resource->industries,
            'version' => $resource->version,
            'auth_type' => $resource->auth_type,
            'cost_model' => $resource->cost_model,
            'risk_level' => $resource->risk_level,
            'actions_count' => is_array($resource->actions) ? count($resource->actions) : 0,
            'status' => $resource->status,
        ];
    }
}
