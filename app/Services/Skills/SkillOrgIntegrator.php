<?php

declare(strict_types=1);

namespace App\Services\Skills;

use App\Models\AgentSkill;
use App\Models\OrgAgentProfile;
use App\Models\OrgCouncilTemplate;
use App\Models\OrgRitualTemplate;
use Illuminate\Support\Collection;

final class SkillOrgIntegrator
{
    private const RISK_LEVEL_ORDER = [
        AgentSkill::RISK_LOW => 0,
        AgentSkill::RISK_STANDARD => 1,
        AgentSkill::RISK_ELEVATED => 2,
        AgentSkill::RISK_CRITICAL => 3,
    ];

    public function getAccessibleSkills(OrgAgentProfile $agent, int $teamId): Collection
    {
        $skills = AgentSkill::query()
            ->where(function ($q) use ($teamId) {
                $q->where('team_id', $teamId)->orWhere('is_global', true);
            })
            ->where('status', AgentSkill::STATUS_ACTIVE)
            ->get();

        $profile = $agent->skill_access_profile;

        if ($profile === null) {
            return $skills;
        }

        $allowedSkills = $profile['allowed_skills'] ?? [];
        $deniedSkills = $profile['denied_skills'] ?? [];
        $maxRiskLevel = $profile['max_risk_level'] ?? null;

        if (! empty($allowedSkills)) {
            $skills = $skills->filter(fn (AgentSkill $s) => in_array($s->slug, $allowedSkills, true));
        }

        if (! empty($deniedSkills)) {
            $skills = $skills->reject(fn (AgentSkill $s) => in_array($s->slug, $deniedSkills, true));
        }

        if ($maxRiskLevel !== null) {
            $maxOrder = self::RISK_LEVEL_ORDER[$maxRiskLevel] ?? PHP_INT_MAX;
            $skills = $skills->filter(
                fn (AgentSkill $s) => (self::RISK_LEVEL_ORDER[$s->risk_level] ?? PHP_INT_MAX) <= $maxOrder
            );
        }

        return $skills->values();
    }

    /**
     * @return array<int, string>
     */
    public function validateRitualSkillRequirements(OrgRitualTemplate $ritual, int $teamId): array
    {
        $requiredSlugs = $ritual->required_skills ?? [];

        if (empty($requiredSlugs)) {
            return [];
        }

        $installedSkills = AgentSkill::query()
            ->where(function ($q) use ($teamId) {
                $q->where('team_id', $teamId)->orWhere('is_global', true);
            })
            ->whereIn('slug', $requiredSlugs)
            ->get()
            ->keyBy('slug');

        $errors = [];

        foreach ($requiredSlugs as $slug) {
            $skill = $installedSkills->get($slug);

            if ($skill === null) {
                $errors[] = "Required skill '{$slug}' is not installed.";

                continue;
            }

            if ($skill->status !== AgentSkill::STATUS_ACTIVE) {
                $errors[] = "Required skill '{$slug}' is {$skill->status} (must be active).";
            }
        }

        return $errors;
    }

    /**
     * @return array<string, Collection>
     */
    public function resolveCouncilSkills(OrgCouncilTemplate $council, int $teamId): array
    {
        $result = [];

        foreach ($council->member_list ?? [] as $member) {
            $agentId = $member['agent_id'] ?? null;
            if ($agentId === null) {
                continue;
            }

            $profile = OrgAgentProfile::query()
                ->where('id', $agentId)
                ->active()
                ->first();

            if ($profile === null) {
                $result[$agentId] = collect();

                continue;
            }

            $result[$agentId] = $this->getAccessibleSkills($profile, $teamId);
        }

        return $result;
    }
}
