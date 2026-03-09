<?php

declare(strict_types=1);

namespace App\Actions\Skills;

use App\Models\AgentSkill;

class FindSkillForTeamAction
{
    public function execute(string $id, int $teamId, bool $includeGlobal = true): ?AgentSkill
    {
        $query = AgentSkill::query();

        if ($includeGlobal) {
            $query->where(function ($q) use ($teamId) {
                $q->where('team_id', $teamId)
                    ->orWhere('is_global', true);
            });
        } else {
            $query->where('team_id', $teamId);
        }

        return $query->find($id);
    }
}
