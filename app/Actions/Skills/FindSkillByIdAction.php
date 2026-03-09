<?php

declare(strict_types=1);

namespace App\Actions\Skills;

use App\Models\AgentSkill;

class FindSkillByIdAction
{
    public function execute(string $id): AgentSkill
    {
        return AgentSkill::findOrFail($id);
    }
}
