<?php

declare(strict_types=1);

namespace App\Actions\Skills;

use App\Models\AgentSkill;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class EmptySkillPaginatorAction
{
    public function execute(int $perPage = 25): LengthAwarePaginator
    {
        return AgentSkill::query()->whereRaw('1 = 0')->paginate($perPage);
    }
}
