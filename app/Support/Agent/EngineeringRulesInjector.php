<?php

declare(strict_types=1);

namespace App\Support\Agent;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

/**
 * Reads, compiles, and injects AgentOps Engineering Rules into agent task context.
 *
 * Profiles select context-appropriate rule subsets to manage token budget:
 * - full: All sections (~6250 tokens) for standard agent runs
 * - core: Architecture + SOLID + Security + Planning + Testing + Laravel + AgentOps (~3000 tokens)
 * - interrogation: Planning + Testing + Databases + Laravel + AgentOps (~2000 tokens)
 * - build: All except Marketing/UX/Design/Research (~4500 tokens)
 *
 * Compiled profiles are cached in Redis to avoid re-parsing on every job dispatch.
 */
class EngineeringRulesInjector
{
    /**
     * Section markers derived from H2 headings in the rules document.
     *
     * @var array<string, string>
     */
    private const SECTION_HEADERS = [
        'architecture' => '## 🏛 Software Architecture',
        'design_patterns' => '## 🧩 Design Patterns',
        'security' => '## 🔐 Security',
        'ux_ui' => '## 🎨 UX / UI',
        'design' => '## 🎨 Design',
        'research' => '## 🔬 Research & Analysis',
        'solid' => '## 🎯 SOLID Principles',
        'dry' => '## 🔄 DRY',
        'clean_code' => '## ✨ Clean Code',
        'planning' => '## 📋 Planning',
        'rest_apis' => '## 🌐 REST APIs',
        'refactoring' => '## ✂️ Refactoring',
        'coding_standards' => '## ✏️ Coding Standards',
        'debugging' => '## 🐛 Debugging',
        'marketing' => '## 📢 Marketing',
        'server_infra' => '## 🖥 Server Infrastructure',
        'terminal_cli' => '## 💻 Terminal / CLI',
        'testing' => '## 🧪 Testing',
        'devops' => '## 🔄 DevOps',
        'tech_specific' => '## 🛠 Technology-Specific',
        'databases' => '## 🗄 Databases',
        'frontend' => '## ⚛️ Frontend',
        'laravel' => '## 🏗 Laravel',
        'ai_apis' => '## 🤖 AI APIs',
        'agentops' => '## ⚙️ AgentOps-Specific',
    ];

    /**
     * Profile definitions mapping profile name to included section keys.
     * null = all sections (full profile).
     *
     * @var array<string, array<int, string>|null>
     */
    private const PROFILES = [
        'full' => null,
        'core' => [
            'architecture',
            'design_patterns',
            'security',
            'solid',
            'dry',
            'clean_code',
            'planning',
            'testing',
            'laravel',
            'agentops',
        ],
        'interrogation' => [
            'architecture',
            'planning',
            'testing',
            'databases',
            'laravel',
            'agentops',
        ],
        'build' => [
            'architecture',
            'design_patterns',
            'security',
            'solid',
            'dry',
            'clean_code',
            'planning',
            'rest_apis',
            'refactoring',
            'coding_standards',
            'debugging',
            'server_infra',
            'terminal_cli',
            'testing',
            'devops',
            'tech_specific',
            'databases',
            'frontend',
            'laravel',
            'ai_apis',
            'agentops',
        ],
    ];

    public function isEnabled(): bool
    {
        return app(FeatureFlagManager::class)->enabled(
            FeatureFlagManager::ENGINEERING_RULES_ENABLED
        );
    }

    /**
     * Get compiled rules for a given profile, with Redis caching.
     *
     * @return string|null The compiled rules markdown, or null if disabled/failed.
     */
    public function getCompiledRules(string $profile = 'full'): ?string
    {
        if (! $this->isEnabled()) {
            return null;
        }

        $cacheKey = 'engineering_rules:compiled:'.$profile;
        $cacheTtl = (int) config('agent.engineering_rules.cache_ttl_seconds', 3600);

        try {
            return Cache::store('redis')->remember($cacheKey, $cacheTtl, function () use ($profile): string {
                return $this->compile($profile);
            });
        } catch (\Throwable $e) {
            Log::warning('EngineeringRulesInjector: cache/compile failed', [
                'profile' => $profile,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Inject engineering rules into task content by prepending the compiled rules block.
     * Used for file-based task markdown paths (ExecuteAgentRunJob pipeline).
     */
    public function inject(string $existingContent, string $profile = 'full'): string
    {
        $rules = $this->getCompiledRules($profile);

        if ($rules === null || $rules === '') {
            return $existingContent;
        }

        $rules = $this->truncateToTokenBudget($rules, $profile);

        return "## Engineering Rules (Active)\n\n".$rules."\n\n---\n\n".$existingContent;
    }

    /**
     * Inject rules into a system prompt string.
     * Used for CLI --system-prompt paths (CliRuntimeExecutor, SystemPromptResolver).
     */
    public function injectIntoSystemPrompt(string $systemPrompt, string $profile = 'core'): string
    {
        $rules = $this->getCompiledRules($profile);

        if ($rules === null || $rules === '') {
            return $systemPrompt;
        }

        $rules = $this->truncateToTokenBudget($rules, $profile);

        return "## Engineering Rules\n\n".$rules."\n\n---\n\n".$systemPrompt;
    }

    /**
     * Compile the rules document into a profile-specific subset.
     */
    private function compile(string $profile): string
    {
        $rulesPath = (string) config(
            'agent.engineering_rules.source_path',
            base_path('docs/refactoring/agent-ops-engineering-rules.md')
        );

        if (! File::exists($rulesPath)) {
            throw new \RuntimeException("Engineering rules file not found: {$rulesPath}");
        }

        $fullContent = File::get($rulesPath);

        if ($profile === 'full' || ! isset(self::PROFILES[$profile])) {
            return $this->stripPreamble($fullContent);
        }

        /** @var array<int, string> $selectedSections */
        $selectedSections = self::PROFILES[$profile];

        return $this->extractSections($fullContent, $selectedSections);
    }

    /**
     * Truncate compiled rules to the configured max_tokens budget for the profile.
     */
    private function truncateToTokenBudget(string $rules, string $profile): string
    {
        $maxTokens = (int) config("agent.engineering_rules.max_tokens.{$profile}", 8000);
        $charsPerToken = 4;
        $maxChars = $maxTokens * $charsPerToken;

        if (mb_strlen($rules) > $maxChars) {
            return mb_substr($rules, 0, $maxChars - 20)."\n\n[truncated]";
        }

        return $rules;
    }

    /**
     * Strip the document preamble (title, how-to-use, skill format sections).
     * Keeps everything from the first rule section onward.
     */
    private function stripPreamble(string $content): string
    {
        // Match the first H2 heading that starts with an emoji
        // All rule sections use emoji-prefixed H2 headings
        if (preg_match('/^## [^\n]*$/mu', $content, $matches, PREG_OFFSET_CAPTURE)) {
            // Walk through matches to find the first rule section (skip "How to Use" etc.)
            $allH2Positions = [];
            preg_match_all('/^## .+$/mu', $content, $allMatches, PREG_OFFSET_CAPTURE);

            foreach ($allMatches[0] as $match) {
                $heading = $match[0];
                // Check if this heading is a known rule section
                if (in_array($heading, self::SECTION_HEADERS, true)) {
                    return substr($content, (int) $match[1]);
                }
            }
        }

        return $content;
    }

    /**
     * Extract only the named sections from the full document.
     *
     * @param  array<int, string>  $sectionKeys
     */
    private function extractSections(string $content, array $sectionKeys): string
    {
        $sections = $this->parseSections($content);
        $output = [];

        foreach ($sectionKeys as $key) {
            if (isset($sections[$key])) {
                $output[] = $sections[$key];
            }
        }

        return implode("\n\n", $output);
    }

    /**
     * Parse the document into named sections keyed by SECTION_HEADERS identifiers.
     *
     * @return array<string, string>
     */
    private function parseSections(string $content): array
    {
        $headerToKey = [];
        foreach (self::SECTION_HEADERS as $key => $header) {
            $headerToKey[$header] = $key;
        }

        $lines = explode("\n", $content);
        $sections = [];
        $currentKey = null;
        $currentLines = [];

        foreach ($lines as $line) {
            $trimmed = trim($line);

            foreach ($headerToKey as $header => $key) {
                if (str_starts_with($trimmed, $header)) {
                    // Save previous section
                    if ($currentKey !== null) {
                        $sections[$currentKey] = rtrim(implode("\n", $currentLines));
                    }

                    $currentKey = $key;
                    $currentLines = [$line];

                    continue 2;
                }
            }

            if ($currentKey !== null) {
                $currentLines[] = $line;
            }
        }

        // Save final section
        if ($currentKey !== null) {
            $sections[$currentKey] = rtrim(implode("\n", $currentLines));
        }

        return $sections;
    }
}
