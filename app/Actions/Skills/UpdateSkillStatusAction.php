<?php

declare(strict_types=1);

namespace App\Actions\Skills;

use App\Models\AgentSkill;

class UpdateSkillStatusAction
{
    public function execute(AgentSkill $skill, string $status, int $userId): AgentSkill
    {
        $updates = [
            'status' => $status,
            'updated_by' => $userId,
            'updated_at' => now(),
        ];

        if ($status === AgentSkill::STATUS_PAUSED) {
            $updates['paused_by'] = $userId;
            $updates['paused_at'] = now();
        } elseif ($status === AgentSkill::STATUS_ACTIVE) {
            $updates['paused_by'] = null;
            $updates['paused_at'] = null;
        }

        $skill->update($updates);

        return $skill;
    }
}
