<?php

declare(strict_types=1);

namespace App\Http\Resources\Skills;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\AgentSkill
 */
class SkillResource extends JsonResource
{
    /** @var \App\Models\AgentSkill */
    public $resource;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'name' => $this->resource->name,
            'slug' => $this->resource->slug,
            'description' => $this->resource->description,
            'version' => $this->resource->version,
            'author' => $this->resource->author,
            'risk_level' => $this->resource->risk_level,
            'status' => $this->resource->status,
            'industries' => $this->resource->industries,
            'memory_blocks' => $this->resource->memory_blocks,
            'mcp_dependencies' => $this->resource->mcp_dependencies,
            'tools_required' => $this->resource->tools_required,
            'trigger_keywords' => $this->resource->trigger_keywords,
            'run_after' => $this->resource->run_after,
            'requires_approval' => $this->resource->requires_approval,
            'is_global' => $this->resource->is_global,
            'validation_result' => $this->resource->validation_result,
            'invocation_count' => $this->resource->invocation_count,
            'last_invoked_at' => $this->resource->last_invoked_at?->toIso8601String(),
            'installed_by' => $this->resource->installed_by,
            'installed_at' => $this->resource->installed_at?->toIso8601String(),
            'updated_at' => $this->resource->updated_at?->toIso8601String(),
        ];
    }
}
