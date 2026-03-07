<?php

declare(strict_types=1);

namespace App\Services\Skills\DTOs;

use App\Models\AgentSkill;

final readonly class ResolvedSkill
{
    public function __construct(
        public string $id,
        public string $name,
        public string $description,
        public string $version,
        public string $riskLevel,
        public string $skillPath,
        public array $triggerKeywords,
        public array $runAfter,
        public float $score,
    ) {}

    public static function fromModel(AgentSkill $skill, float $score = 0.0): self
    {
        return new self(
            id: (string) $skill->id,
            name: $skill->name,
            description: $skill->description,
            version: $skill->version,
            riskLevel: $skill->risk_level,
            skillPath: $skill->skill_path,
            triggerKeywords: $skill->trigger_keywords ?? [],
            runAfter: $skill->run_after ?? [],
            score: $score,
        );
    }
}
