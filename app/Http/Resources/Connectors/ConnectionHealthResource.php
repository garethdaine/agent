<?php

declare(strict_types=1);

namespace App\Http\Resources\Connectors;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\AgentConnectorConnection
 */
class ConnectionHealthResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $healthScore = (float) $this->health_score;

        return [
            'health_score' => $healthScore,
            'status' => match (true) {
                $healthScore >= 0.8 => 'healthy',
                $healthScore >= 0.5 => 'degraded',
                default => 'unhealthy',
            },
            'last_check_at' => $this->last_health_check_at?->toIso8601String(),
        ];
    }
}
