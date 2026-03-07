<?php

declare(strict_types=1);

namespace App\Services\Skills;

use App\Models\AgentSkill;
use App\Models\DelegateeProfile;
use App\Services\Skills\DTOs\ResolvedSkill;
use App\Support\Agent\FeatureFlagManager;
use Illuminate\Support\Facades\Log;

class SkillResolver
{
    private const MIN_KEYWORD_SCORE = 0.05;

    private const KEYWORD_WEIGHT = 0.7;

    private const INDUSTRY_WEIGHT = 0.3;

    private const PRE_FILTER_LIMIT = 15;

    public function __construct(
        private readonly FeatureFlagManager $flags,
    ) {}

    /**
     * @return ResolvedSkill[]
     */
    public function resolve(
        string $taskDescription,
        int $teamId,
        ?DelegateeProfile $delegatee = null,
        array $availableMcpTools = [],
        array $availableMemoryBlocks = [],
    ): array {
        $skills = AgentSkill::query()
            ->where(function ($query) use ($teamId) {
                $query->where(function ($q) use ($teamId) {
                    $q->active()->forTeam($teamId);
                })->orWhere(function ($q) {
                    $q->active()->global();
                });
            })
            ->get();

        if ($skills->isEmpty()) {
            return [];
        }

        $taskTokens = $this->tokenize($taskDescription);
        if (empty($taskTokens)) {
            return [];
        }

        // Keyword pre-filter with scoring
        $scored = [];
        foreach ($skills as $skill) {
            $keywordScore = $this->computeKeywordOverlap($taskTokens, $skill->trigger_keywords ?? []);
            $industryScore = $this->computeKeywordOverlap($taskTokens, $skill->industries ?? []);
            $combinedScore = ($keywordScore * self::KEYWORD_WEIGHT) + ($industryScore * self::INDUSTRY_WEIGHT);

            if ($combinedScore >= self::MIN_KEYWORD_SCORE) {
                $scored[] = ['skill' => $skill, 'score' => $combinedScore];
            }
        }

        if (empty($scored)) {
            return [];
        }

        // Sort by score descending and take top pre-filter limit
        usort($scored, fn ($a, $b) => $b['score'] <=> $a['score']);
        $scored = array_slice($scored, 0, self::PRE_FILTER_LIMIT);

        // Hard filters: trust score threshold
        $trustThresholds = config('agent.skills.risk_level_trust_thresholds', []);
        $scored = array_filter($scored, function ($item) use ($delegatee, $trustThresholds) {
            $skill = $item['skill'];
            $threshold = $trustThresholds[$skill->risk_level] ?? 0.0;

            if ($delegatee !== null && (float) $delegatee->trust_score < $threshold) {
                return false;
            }

            return true;
        });

        // LLM ranking (if auto_resolve enabled) — falls back to keyword scores on failure
        if ($this->flags->enabled(FeatureFlagManager::SKILLS_AUTO_RESOLVE)) {
            try {
                $scored = $this->llmRank($scored, $taskDescription);
            } catch (\Throwable $e) {
                Log::warning('LLM ranking failed, falling back to keyword scores', [
                    'error' => $e->getMessage(),
                ]);
                // Keep keyword scores as-is
            }
        }

        // Take top N
        $maxSkills = (int) config('agent.skills.max_skills_per_invocation', 5);
        $scored = array_slice(array_values($scored), 0, $maxSkills);

        // Build ResolvedSkill DTOs
        $resolved = array_map(
            fn ($item) => ResolvedSkill::fromModel($item['skill'], $item['score']),
            $scored,
        );

        // DAG sort by run_after
        return $this->topologicalSort($resolved);
    }

    /**
     * @return string[]
     */
    private function tokenize(string $text): array
    {
        $text = strtolower($text);
        $text = preg_replace('/[^a-z0-9\s\-]/', ' ', $text);
        $tokens = preg_split('/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);

        return array_values(array_unique(array_filter($tokens, fn ($t) => strlen($t) >= 3)));
    }

    private function computeKeywordOverlap(array $taskTokens, array $keywords): float
    {
        if (empty($keywords)) {
            return 0.0;
        }

        $normalizedKeywords = array_map(fn ($k) => strtolower((string) $k), $keywords);
        $matches = 0;

        foreach ($normalizedKeywords as $keyword) {
            // Check if any task token matches or contains the keyword (or vice versa)
            foreach ($taskTokens as $token) {
                if ($token === $keyword || str_contains($token, $keyword) || str_contains($keyword, $token)) {
                    $matches++;
                    break;
                }
            }
        }

        return $matches / count($normalizedKeywords);
    }

    /**
     * @param  ResolvedSkill[]  $skills
     * @return ResolvedSkill[]
     */
    private function topologicalSort(array $skills): array
    {
        $byName = [];
        foreach ($skills as $skill) {
            $byName[$skill->name] = $skill;
        }

        $resolved = [];
        $visited = [];
        $inStack = [];

        $visit = function (string $name) use (&$visit, &$resolved, &$visited, &$inStack, $byName): bool {
            if (isset($inStack[$name])) {
                return false; // Cycle detected
            }
            if (isset($visited[$name])) {
                return true;
            }

            $inStack[$name] = true;
            $skill = $byName[$name] ?? null;

            if ($skill !== null) {
                foreach ($skill->runAfter as $dep) {
                    if (isset($byName[$dep])) {
                        if (! $visit($dep)) {
                            return false; // Cycle propagated
                        }
                    }
                }

                $resolved[] = $skill;
            }

            $visited[$name] = true;
            unset($inStack[$name]);

            return true;
        };

        $cyclicNames = [];

        foreach (array_keys($byName) as $name) {
            if (! isset($visited[$name])) {
                $resolved = [];
                $visited = [];
                $inStack = [];

                // Try to sort — detect cycles
                $hasCycle = false;
                foreach (array_keys($byName) as $n) {
                    if (! isset($visited[$n])) {
                        if (! $visit($n)) {
                            $hasCycle = true;
                            break;
                        }
                    }
                }

                if (! $hasCycle) {
                    return $resolved;
                }

                // Cycle detected — identify and remove cyclic skills
                $cyclicNames = $this->findCyclicNodes($byName);

                foreach ($cyclicNames as $cyclicName) {
                    Log::warning('Removing skill with circular run_after dependency', [
                        'skill' => $cyclicName,
                    ]);
                    unset($byName[$cyclicName]);
                }

                // Retry sort without cyclic nodes
                $resolved = [];
                $visited = [];
                $inStack = [];

                foreach (array_keys($byName) as $n) {
                    if (! isset($visited[$n])) {
                        $visit($n);
                    }
                }

                return $resolved;
            }
        }

        return $resolved;
    }

    /**
     * @param  array<string, ResolvedSkill>  $byName
     * @return string[]
     */
    private function findCyclicNodes(array $byName): array
    {
        $cyclic = [];

        foreach ($byName as $name => $skill) {
            $visited = [];
            $current = $name;

            while (true) {
                if (isset($visited[$current])) {
                    // Found a cycle — mark all nodes in the cycle
                    $inCycle = $current;
                    do {
                        $cyclic[] = $inCycle;
                        $deps = ($byName[$inCycle] ?? null)?->runAfter ?? [];
                        $inCycle = null;
                        foreach ($deps as $dep) {
                            if (isset($byName[$dep]) && isset($visited[$dep])) {
                                $inCycle = $dep;
                                break;
                            }
                        }
                    } while ($inCycle !== null && $inCycle !== $current);

                    break;
                }

                $visited[$current] = true;
                $deps = ($byName[$current] ?? null)?->runAfter ?? [];
                $nextInSet = null;

                foreach ($deps as $dep) {
                    if (isset($byName[$dep])) {
                        $nextInSet = $dep;
                        break;
                    }
                }

                if ($nextInSet === null) {
                    break;
                }

                $current = $nextInSet;
            }
        }

        return array_unique($cyclic);
    }

    /**
     * @param  array{skill: AgentSkill, score: float}[]  $scored
     * @return array{skill: AgentSkill, score: float}[]
     */
    private function llmRank(array $scored, string $taskDescription): array
    {
        // LLM ranking would call the LLM client here
        // For now, this is a placeholder that preserves keyword ranking
        // A real implementation would send candidate descriptions + task to LLM
        // and parse ranked results
        throw new \RuntimeException('LLM ranking not yet implemented');
    }
}
