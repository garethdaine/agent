<?php

declare(strict_types=1);

namespace App\Http\Resources\Skills;

use App\Services\Skills\DTOs\SkillLibraryEntry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @property SkillLibraryEntry $resource */
class SkillLibraryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'slug' => $this->resource->slug,
            'name' => $this->resource->name,
            'description' => $this->resource->description,
            'version' => $this->resource->version,
            'author' => $this->resource->author,
            'category' => $this->resource->category,
            'industries' => $this->resource->industries,
            'risk_level' => $this->resource->riskLevel,
        ];
    }
}
