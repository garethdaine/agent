<?php

declare(strict_types=1);

namespace App\Actions\Skills;

use App\Models\AgentSkill;

class RevalidateSkillAction
{
    /**
     * @param  array<string, mixed>  $validationResult
     */
    public function execute(AgentSkill $skill, array $validationResult, bool $overallPass, int $userId): AgentSkill
    {
        $newStatus = $overallPass ? AgentSkill::STATUS_ACTIVE : AgentSkill::STATUS_FAILED_VALIDATION;

        $skill->update([
            'validation_result' => $validationResult,
            'status' => $newStatus,
            'updated_by' => $userId,
            'updated_at' => now(),
        ]);

        return $skill;
    }
}
