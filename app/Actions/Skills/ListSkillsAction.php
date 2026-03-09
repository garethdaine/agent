<?php

declare(strict_types=1);

namespace App\Actions\Skills;

use App\Models\AgentSkill;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ListSkillsAction
{
    /**
     * @param  array{status?: string, risk_level?: string, industry?: string, per_page?: int}  $filters
     */
    public function execute(int $teamId, array $filters = []): LengthAwarePaginator
    {
        $query = AgentSkill::query()
            ->where(function ($q) use ($teamId) {
                $q->where('team_id', $teamId)
                    ->orWhere('is_global', true);
            });

        if (isset($filters['status']) && $filters['status'] !== '') {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['risk_level']) && $filters['risk_level'] !== '') {
            $query->where('risk_level', $filters['risk_level']);
        }

        if (isset($filters['industry']) && $filters['industry'] !== '') {
            $query->whereJsonContains('industries', $filters['industry']);
        }

        $perPage = min($filters['per_page'] ?? 25, 100);

        return $query->orderBy('name')->paginate($perPage);
    }
}
