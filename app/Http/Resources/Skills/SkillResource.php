<?php

declare(strict_types=1);

namespace App\Http\Resources\Skills;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SkillResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'version' => $this->version,
            'author' => $this->author,
            'risk_level' => $this->risk_level,
            'status' => $this->status,
            'industries' => $this->industries,
            'memory_blocks' => $this->memory_blocks,
            'mcp_dependencies' => $this->mcp_dependencies,
            'tools_required' => $this->tools_required,
            'trigger_keywords' => $this->trigger_keywords,
            'run_after' => $this->run_after,
            'requires_approval' => $this->requires_approval,
            'is_global' => $this->is_global,
            'validation_result' => $this->validation_result,
            'invocation_count' => $this->invocation_count,
            'last_invoked_at' => $this->last_invoked_at?->toIso8601String(),
            'installed_by' => $this->installed_by,
            'installed_at' => $this->installed_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
