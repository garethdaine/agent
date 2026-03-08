<?php

declare(strict_types=1);

namespace App\Http\Resources\Connectors;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConnectorResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'display_name' => $this->display_name,
            'description' => $this->description,
            'category' => $this->category,
            'industries' => $this->industries,
            'version' => $this->version,
            'auth_type' => $this->auth_type,
            'cost_model' => $this->cost_model,
            'risk_level' => $this->risk_level,
            'actions_count' => is_array($this->actions) ? count($this->actions) : 0,
            'status' => $this->status,
        ];
    }
}
