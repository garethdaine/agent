<?php

declare(strict_types=1);

namespace App\Actions\Skills;

use App\Models\AgentSkill;

class GetSkillHealthAction
{
    /**
     * @return list<array{id: mixed, name: mixed, status: mixed, risk_level: mixed, validation_pass: bool, invocation_count: mixed, last_invoked_at: string|null, has_drift: bool}>
     */
    public function execute(int $teamId): array
    {
        $skills = AgentSkill::query()
            ->where(function ($query) use ($teamId) {
                $query->forTeam($teamId)->orWhere->global();
            })
            ->get();

        return $skills->map(fn (AgentSkill $skill) => [
            'id' => $skill->id,
            'name' => $skill->name,
            'status' => $skill->status,
            'risk_level' => $skill->risk_level,
            'validation_pass' => $skill->validation_result['overall_pass'] ?? false,
            'invocation_count' => $skill->invocation_count,
            'last_invoked_at' => $skill->last_invoked_at?->toIso8601String(),
            'has_drift' => $skill->status === AgentSkill::STATUS_PENDING_REVIEW,
        ])->values()->all();
    }
}
