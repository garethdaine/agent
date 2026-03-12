<?php

declare(strict_types=1);

namespace App\Support\Memory;

use App\Support\Agent\FeatureFlagManager;

/**
 * Resolves the effective soul/personality for an agent by merging
 * specific soul configuration with memory core block defaults.
 *
 * Hierarchy: Specific soul > Memory core blocks > Empty
 *
 * Specific soul fields (from ConnectorAccount, DelegateeProfile, or OrgAgentProfile)
 * take priority. Empty/missing fields fall back to the user's memory core blocks
 * (agent_persona → personality, user_profile → user_context).
 */
class SoulResolver
{
    public function __construct(
        private readonly CoreMemoryManager $coreMemoryManager,
        private readonly FeatureFlagManager $featureFlags,
    ) {}

    /**
     * Resolve effective soul with memory fallback.
     *
     * @param  array|null  $specificSoul  The connector/delegatee/org-specific soul config
     * @param  int  $userId  The user ID for memory core block lookup
     * @return array{name: string|null, personality: string|null, system_prompt: string|null, user_context: string|null}
     */
    public function resolve(?array $specificSoul, int $userId): array
    {
        $memoryDefaults = $this->buildFromMemory($userId);

        if ($specificSoul === null || ! $this->hasMeaningfulContent($specificSoul)) {
            return $memoryDefaults;
        }

        // Field-level merge: specific soul wins, empty fields fall back to memory
        return [
            'name' => $specificSoul['name'] ?? null,
            'personality' => ! empty($specificSoul['personality'])
                ? $specificSoul['personality']
                : $memoryDefaults['personality'],
            'system_prompt' => $specificSoul['system_prompt'] ?? null,
            'user_context' => ! empty($specificSoul['user_context'])
                ? $specificSoul['user_context']
                : $memoryDefaults['user_context'],
        ];
    }

    /**
     * Build soul defaults from memory core blocks.
     *
     * Reads agent_persona and user_profile core blocks as fallback
     * personality and user context. Returns empty values when memory
     * is disabled or blocks don't exist.
     *
     * @return array{name: string|null, personality: string|null, system_prompt: string|null, user_context: string|null}
     */
    private function buildFromMemory(int $userId): array
    {
        $defaults = [
            'name' => null,
            'personality' => null,
            'system_prompt' => null,
            'user_context' => null,
        ];

        if (! $this->featureFlags->enabled(FeatureFlagManager::MEMORY_ENABLED)) {
            return $defaults;
        }

        try {
            $persona = $this->coreMemoryManager->get($userId, 'agent_persona');
            $profile = $this->coreMemoryManager->get($userId, 'user_profile');

            $defaults['personality'] = $persona?->content_text;
            $defaults['user_context'] = $profile?->content_text;
        } catch (\Throwable) {
            // Memory failures are non-blocking — return empty defaults
        }

        return $defaults;
    }

    /**
     * Check if the soul array has any meaningful content worth using.
     */
    private function hasMeaningfulContent(array $soul): bool
    {
        return ! empty($soul['name'])
            || ! empty($soul['personality'])
            || ! empty($soul['system_prompt'])
            || ! empty($soul['user_context']);
    }
}
