<?php

declare(strict_types=1);

namespace App\Http\Resources\Connectors;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvocationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'action_name' => $this->action_name,
            'http_method' => $this->http_method,
            'http_status' => $this->http_status,
            'duration_ms' => $this->duration_ms,
            'outcome' => $this->outcome,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
