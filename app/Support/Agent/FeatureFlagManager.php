<?php

declare(strict_types=1);

namespace App\Support\Agent;

use App\Models\AgentFeatureSetting;
use Illuminate\Support\Facades\Schema;
use Throwable;

class FeatureFlagManager
{
    // Core feature flags
    public const DELEGATION_ENABLED = 'delegation.enabled';

    public const DELEGATION_UI_ENABLED = 'delegation.ui_enabled';

    public const ADVERSARIAL_REVIEW_ENABLED = 'agent.interrogation.adversarial_review_enabled';

    public const MEMORY_ENABLED = 'memory.enabled';

    public const MEMORY_API_ENABLED = 'memory.api_enabled';

    public const REPO_ANALYSIS_ENABLED = 'repo_analysis.enabled';

    public const REPO_ANALYSIS_AI_ENABLED = 'repo_analysis.ai.enabled';

    // Org layer flag constants
    public const ORG_ENABLED = 'agent.org.enabled';

    public const ORG_PROFILES_ENABLED = 'agent.org.features.profiles';

    public const ORG_RITUALS_ENABLED = 'agent.org.features.rituals';

    public const ORG_COUNCILS_ENABLED = 'agent.org.features.councils';

    public const ORG_COST_ENABLED = 'agent.org.features.cost_governance';

    // Compliance flag constants
    public const COMPLIANCE_ENABLED = 'compliance.enabled';

    public const COMPLIANCE_ENFORCEMENT_MODE = 'compliance.enforcement_mode';

    public const COMPLIANCE_PLAN_GATE = 'compliance.plan_gate_enabled';

    public const COMPLIANCE_VERIFICATION_GATE = 'compliance.verification_gate_enabled';

    public const COMPLIANCE_ELEGANCE_GATE = 'compliance.elegance_gate_enabled';

    public const COMPLIANCE_LESSONS = 'compliance.lessons_enabled';

    // Runtime flag constants
    public const STAR_PREAMBLE_ENABLED = 'agent.star_preamble.enabled';

    public const TARGETED_RETRY_ENABLED = 'agent.targeted_retry.enabled';

    public const WEBHOOKS_ENABLED = 'agent.webhooks.enabled';

    public const MCP_ENABLED = 'runtime.mcp.enabled';

    public const WRAPPER_ENABLED = 'runtime.cli.wrapper_enabled';

    public const YIELD_ENABLED = 'runtime.cli.yield_enabled';

    public const SUBAGENTS_ENABLED = 'runtime.subagents.enabled';

    // Messenger flag constants
    public const MESSENGER_SYSTEM_NOTIFICATIONS_ENABLED = 'messenger.system_notifications.enabled';

    // Platform flag constants
    public const BILLING_ENABLED = 'billing.enabled';

    public const COMPACTION_ENABLED = 'messenger.compaction.enabled';

    public const TUNNEL_ENABLED = 'tunnel.enabled';

    // Skills flag constants
    public const SKILLS_ENABLED = 'skills.enabled';

    public const SKILLS_UI_ENABLED = 'skills.ui_enabled';

    public const SKILLS_LIBRARY_ENABLED = 'skills.library_enabled';

    public const SKILLS_AUTO_RESOLVE = 'skills.auto_resolve';

    public const SKILLS_VALIDATION_LLM_REVIEW = 'skills.validation.llm_review';

    // Engineering rules flag constants
    public const ENGINEERING_RULES_ENABLED = 'agent.engineering_rules.enabled';

    public const SKILLS_VALIDATION_STRICT_MODE = 'skills.validation.strict_mode';

    // Connector flag constants
    public const CONNECTORS_ENABLED = 'connectors.enabled';

    public const CONNECTORS_UI_ENABLED = 'connectors.ui_enabled';

    public const CONNECTORS_WEBHOOKS_ENABLED = 'connectors.webhooks_enabled';

    public const CONNECTORS_AUTO_RESOLVE = 'connectors.auto_resolve';

    public const CONNECTORS_WRITE_ACTIONS = 'connectors.write_actions';

    public const CONNECTORS_CREDENTIAL_REFRESH = 'connectors.credential_refresh';

    // Security flag constants (immutable — always enabled)
    public const SECURITY_CONTENT_TRUST = 'security.content_trust';

    public const SECURITY_INJECTION_DETECTION = 'security.injection_detection';

    public const SECURITY_EXFILTRATION_DETECTION = 'security.exfiltration_detection';

    private const IMMUTABLE_SECURITY_FLAGS = [
        self::SECURITY_CONTENT_TRUST,
        self::SECURITY_INJECTION_DETECTION,
        self::SECURITY_EXFILTRATION_DETECTION,
    ];

    /**
     * @var array<string, array{label: string, description: string}>
     */
    private const DEFINITIONS = [
        // Core features
        self::DELEGATION_ENABLED => [
            'label' => 'Delegation API & Engine',
            'description' => 'Enable delegation API routes, coordinator processing, and scheduled delegation jobs.',
        ],
        self::DELEGATION_UI_ENABLED => [
            'label' => 'Delegation UI',
            'description' => 'Enable delegation screens and navigation items in the web interface.',
        ],
        self::ADVERSARIAL_REVIEW_ENABLED => [
            'label' => 'Adversarial Reviewer',
            'description' => 'Enable adversarial review passes during summary and plan generation.',
        ],
        self::MEMORY_ENABLED => [
            'label' => 'Agent Memory',
            'description' => 'Enable the memory system: Core Memory blocks, Working Memory buffer, and Long-term Memory with BM25 retrieval.',
        ],
        self::MEMORY_API_ENABLED => [
            'label' => 'Memory API Features',
            'description' => 'Enable LLM-powered memory features: semantic embeddings (pgvector), entity extraction, and Neo4j knowledge graph. Requires Agent Memory to be enabled and provider keys configured.',
        ],
        self::REPO_ANALYSIS_ENABLED => [
            'label' => 'Code Analysis',
            'description' => 'Enable the Code Analysis tool, API lifecycle routes, and Tools navigation entry.',
        ],
        self::REPO_ANALYSIS_AI_ENABLED => [
            'label' => 'Code Analysis AI Tasks',
            'description' => 'Enable AI-driven task execution and narrative report synthesis for Code Analysis sessions.',
        ],

        // Org layer
        self::ORG_ENABLED => [
            'label' => 'Org Layer',
            'description' => 'Enable organizational AI workforce orchestration layer.',
        ],
        self::ORG_PROFILES_ENABLED => [
            'label' => 'Org Profiles',
            'description' => 'Enable agent profile management within the org layer. Requires Org Layer to be enabled.',
        ],
        self::ORG_RITUALS_ENABLED => [
            'label' => 'Org Rituals',
            'description' => 'Enable automated ritual scheduling and execution within the org layer. Requires Org Layer to be enabled.',
        ],
        self::ORG_COUNCILS_ENABLED => [
            'label' => 'Org Councils',
            'description' => 'Enable council deliberation workflows within the org layer. Requires Org Layer to be enabled.',
        ],
        self::ORG_COST_ENABLED => [
            'label' => 'Org Cost Governance',
            'description' => 'Enable cost tracking and budget enforcement for the org layer. Requires Org Layer to be enabled.',
        ],

        // Compliance
        self::COMPLIANCE_ENABLED => [
            'label' => 'Compliance Engine',
            'description' => 'Enable the compliance review engine for agent job runs.',
        ],
        self::COMPLIANCE_PLAN_GATE => [
            'label' => 'Compliance Plan Gate',
            'description' => 'Require plan compliance checks before job execution. Requires Compliance Engine to be enabled.',
        ],
        self::COMPLIANCE_VERIFICATION_GATE => [
            'label' => 'Compliance Verification Gate',
            'description' => 'Require verification compliance checks after job execution. Requires Compliance Engine to be enabled.',
        ],
        self::COMPLIANCE_ELEGANCE_GATE => [
            'label' => 'Compliance Elegance Gate',
            'description' => 'Require code elegance checks during compliance review. Requires Compliance Engine to be enabled.',
        ],
        self::COMPLIANCE_LESSONS => [
            'label' => 'Compliance Lessons',
            'description' => 'Enable lessons-learned extraction from compliance reviews. Requires Compliance Engine to be enabled.',
        ],

        // Runtime
        self::STAR_PREAMBLE_ENABLED => [
            'label' => 'STAR Preamble',
            'description' => 'Inject STAR-format preamble into agent prompts for structured reasoning.',
        ],
        self::TARGETED_RETRY_ENABLED => [
            'label' => 'Targeted Retry',
            'description' => 'Enable targeted retry of failed agent runs with adjusted prompts.',
        ],
        self::WEBHOOKS_ENABLED => [
            'label' => 'Webhooks',
            'description' => 'Enable outbound webhook notifications for agent events.',
        ],
        self::MCP_ENABLED => [
            'label' => 'MCP Integration',
            'description' => 'Enable Model Context Protocol server integration for runtime tool access.',
        ],
        self::WRAPPER_ENABLED => [
            'label' => 'CLI Wrapper',
            'description' => 'Enable persistent CLI wrapper process for session reuse and reduced startup overhead.',
        ],
        self::YIELD_ENABLED => [
            'label' => 'Turn Yielding',
            'description' => 'Enable automatic turn yielding for long-running agent sessions.',
        ],
        self::SUBAGENTS_ENABLED => [
            'label' => 'Sub-Agents',
            'description' => 'Enable spawning of sub-agent processes from messenger runtime sessions.',
        ],
        self::ENGINEERING_RULES_ENABLED => [
            'label' => 'Engineering Rules Injection',
            'description' => 'Inject AgentOps engineering rules into agent task context for structured quality enforcement.',
        ],

        // Messenger
        self::MESSENGER_SYSTEM_NOTIFICATIONS_ENABLED => [
            'label' => 'Messenger System Notifications',
            'description' => 'Send system event notifications (ritual completions, escalations, job failures) to connected messenger channels.',
        ],

        // Platform
        self::BILLING_ENABLED => [
            'label' => 'Billing',
            'description' => 'Enable Stripe-based billing, usage metering, and subscription management.',
        ],
        self::COMPACTION_ENABLED => [
            'label' => 'Messenger Compaction',
            'description' => 'Enable automatic compaction of long messenger conversation threads.',
        ],
        self::TUNNEL_ENABLED => [
            'label' => 'Cloudflare Tunnel',
            'description' => 'Enable Cloudflare Tunnel integration for secure remote access via a custom hostname.',
        ],

        // Skills
        self::SKILLS_ENABLED => [
            'label' => 'Agent Skills',
            'description' => 'Enable the pluggable skill system for extending agent capabilities with domain-specific skill bundles.',
        ],
        self::SKILLS_UI_ENABLED => [
            'label' => 'Skills UI',
            'description' => 'Enable skills management pages in the web interface. Requires Agent Skills to be enabled.',
        ],
        self::SKILLS_LIBRARY_ENABLED => [
            'label' => 'Skills Library',
            'description' => 'Enable browsing and installing skills from the built-in skill library. Requires Agent Skills to be enabled.',
        ],
        self::SKILLS_AUTO_RESOLVE => [
            'label' => 'Skills Auto-Resolve',
            'description' => 'Enable automatic skill matching and LLM ranking during delegation. Requires Agent Skills to be enabled.',
        ],
        self::SKILLS_VALIDATION_LLM_REVIEW => [
            'label' => 'Skills LLM Review',
            'description' => 'Enable LLM-assisted content analysis during skill validation via AdversarialReviewerService.',
        ],
        self::SKILLS_VALIDATION_STRICT_MODE => [
            'label' => 'Skills Strict Validation',
            'description' => 'Block skill installation on any validation warning, not just errors.',
        ],

        // Connectors
        self::CONNECTORS_ENABLED => [
            'label' => 'Connectors',
            'description' => 'Enable the external service connector system for agent integrations with SaaS platforms and APIs.',
        ],
        self::CONNECTORS_UI_ENABLED => [
            'label' => 'Connectors UI',
            'description' => 'Enable connector management pages in the web interface. Requires Connectors to be enabled.',
        ],
        self::CONNECTORS_WEBHOOKS_ENABLED => [
            'label' => 'Connector Webhooks',
            'description' => 'Enable push-based webhook ingestion from connected external services. Requires Connectors to be enabled.',
        ],
        self::CONNECTORS_AUTO_RESOLVE => [
            'label' => 'Connector Auto-Resolve',
            'description' => 'Enable automatic connector discovery and matching during agent delegation. Requires Connectors to be enabled.',
        ],
        self::CONNECTORS_WRITE_ACTIONS => [
            'label' => 'Connector Write Actions',
            'description' => 'Enable write/mutate actions on connected services. When disabled, only read-only actions are permitted. Requires Connectors to be enabled.',
        ],
        self::CONNECTORS_CREDENTIAL_REFRESH => [
            'label' => 'Connector Credential Refresh',
            'description' => 'Enable automatic background token refresh for OAuth-connected services. Requires Connectors to be enabled.',
        ],

        // Security (immutable)
        self::SECURITY_CONTENT_TRUST => [
            'label' => 'Content Trust Classification',
            'description' => 'Enable content trust classification for all tool results. Immutable: cannot be disabled.',
        ],
        self::SECURITY_INJECTION_DETECTION => [
            'label' => 'Injection Detection',
            'description' => 'Enable prompt injection detection engine for untrusted content. Immutable: cannot be disabled.',
        ],
        self::SECURITY_EXFILTRATION_DETECTION => [
            'label' => 'Exfiltration Detection',
            'description' => 'Enable outbound request monitoring for data exfiltration patterns. Immutable: cannot be disabled.',
        ],
    ];

    private ?bool $storeAvailable = null;

    /**
     * @return array<int, string>
     */
    public static function managedKeys(): array
    {
        return array_keys(self::DEFINITIONS);
    }

    /**
     * Returns all compliance-related flag keys.
     *
     * @return array<int, string>
     */
    public static function getComplianceFlags(): array
    {
        return [
            self::COMPLIANCE_ENABLED,
            self::COMPLIANCE_PLAN_GATE,
            self::COMPLIANCE_VERIFICATION_GATE,
            self::COMPLIANCE_ELEGANCE_GATE,
            self::COMPLIANCE_LESSONS,
        ];
    }

    /**
     * Returns all skills-related flag keys.
     *
     * @return array<int, string>
     */
    public static function getSkillsFlags(): array
    {
        return [
            self::SKILLS_ENABLED,
            self::SKILLS_UI_ENABLED,
            self::SKILLS_LIBRARY_ENABLED,
            self::SKILLS_AUTO_RESOLVE,
            self::SKILLS_VALIDATION_LLM_REVIEW,
            self::SKILLS_VALIDATION_STRICT_MODE,
        ];
    }

    /**
     * Returns all connector-related flag keys.
     *
     * @return array<int, string>
     */
    public static function getConnectorsFlags(): array
    {
        return [
            self::CONNECTORS_ENABLED,
            self::CONNECTORS_UI_ENABLED,
            self::CONNECTORS_WEBHOOKS_ENABLED,
            self::CONNECTORS_AUTO_RESOLVE,
            self::CONNECTORS_WRITE_ACTIONS,
            self::CONNECTORS_CREDENTIAL_REFRESH,
        ];
    }

    /**
     * Returns all security-related flag keys (immutable).
     *
     * @return array<int, string>
     */
    public static function getSecurityFlags(): array
    {
        return self::IMMUTABLE_SECURITY_FLAGS;
    }

    public function enabled(string $key): bool
    {
        if (in_array($key, self::IMMUTABLE_SECURITY_FLAGS, true)) {
            return true;
        }

        if (! $this->isManagedKey($key)) {
            return (bool) config($key, false);
        }

        $setting = $this->findSetting($key);
        if ($setting === null) {
            return $this->defaultValue($key);
        }

        return (bool) $setting->is_enabled;
    }

    /**
     * Alias for enabled() to match common naming conventions.
     */
    public function isEnabled(string $key): bool
    {
        return $this->enabled($key);
    }

    /**
     * Check if an org layer sub-feature is enabled.
     * Sub-features are only enabled when the global org flag is also enabled.
     */
    public function isOrgFeatureEnabled(string $subFeature): bool
    {
        if (! $this->isEnabled(self::ORG_ENABLED)) {
            return false;
        }

        $key = "agent.org.features.{$subFeature}";

        return $this->isManagedKey($key)
            ? $this->enabled($key)
            : (bool) config($key, false);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function all(): array
    {
        $stored = $this->storedMap();
        $rows = [];

        foreach (self::DEFINITIONS as $key => $definition) {
            $storedRow = $stored[$key] ?? null;
            $isOverridden = $storedRow !== null;

            $isImmutable = in_array($key, self::IMMUTABLE_SECURITY_FLAGS, true);
            $isEnabled = $isImmutable
                ? true
                : ($isOverridden ? (bool) $storedRow['is_enabled'] : $this->defaultValue($key));

            $rows[] = [
                'key' => $key,
                'label' => $definition['label'],
                'description' => $definition['description'],
                'default_enabled' => $isImmutable ? true : $this->defaultValue($key),
                'is_enabled' => $isEnabled,
                'is_overridden' => $isOverridden,
                'is_immutable' => $isImmutable,
                'updated_at' => $isOverridden ? $storedRow['updated_at']?->toIso8601String() : null,
            ];
        }

        return $rows;
    }

    /**
     * @param  array<string, bool>  $flags
     * @return array<int, array<string, mixed>>
     */
    public function updateMany(array $flags, ?int $updatedByUserId): array
    {
        if (! $this->isStoreAvailable()) {
            throw new \RuntimeException('Feature settings table is unavailable. Run database migrations.');
        }

        foreach ($flags as $key => $enabled) {
            if (! $this->isManagedKey($key)) {
                continue;
            }

            // Security flags are immutable — always force true
            $effectiveValue = in_array($key, self::IMMUTABLE_SECURITY_FLAGS, true)
                ? true
                : (bool) $enabled;

            AgentFeatureSetting::query()->updateOrCreate(
                ['key' => $key],
                [
                    'is_enabled' => $effectiveValue,
                    'updated_by_user_id' => $updatedByUserId,
                ],
            );
        }

        return $this->all();
    }

    /**
     * @param  array<int, string>  $keys
     * @return array<string, bool>
     */
    public function valuesFor(array $keys): array
    {
        $resolved = [];

        foreach ($keys as $key) {
            if (! $this->isManagedKey($key)) {
                continue;
            }

            $resolved[$key] = $this->enabled($key);
        }

        return $resolved;
    }

    private function isManagedKey(string $key): bool
    {
        return array_key_exists($key, self::DEFINITIONS);
    }

    private function defaultValue(string $key): bool
    {
        return (bool) config($key, false);
    }

    private function findSetting(string $key): ?AgentFeatureSetting
    {
        if (! $this->isStoreAvailable()) {
            return null;
        }

        return AgentFeatureSetting::query()
            ->where('key', $key)
            ->first();
    }

    /**
     * @return array<string, array{is_enabled: bool, updated_at: mixed}>
     */
    private function storedMap(): array
    {
        if (! $this->isStoreAvailable()) {
            return [];
        }

        return AgentFeatureSetting::query()
            ->whereIn('key', self::managedKeys())
            ->get(['key', 'is_enabled', 'updated_at'])
            ->mapWithKeys(fn (AgentFeatureSetting $setting) => [
                (string) $setting->key => [
                    'is_enabled' => (bool) $setting->is_enabled,
                    'updated_at' => $setting->updated_at,
                ],
            ])->all();
    }

    /**
     * Enable a feature flag by key. Primarily intended for testing.
     */
    public static function enable(string $key): void
    {
        AgentFeatureSetting::query()->updateOrCreate(
            ['key' => $key],
            ['is_enabled' => true],
        );
    }

    private function isStoreAvailable(): bool
    {
        if ($this->storeAvailable !== null) {
            return $this->storeAvailable;
        }

        try {
            $this->storeAvailable = Schema::hasTable('agent_feature_settings');
        } catch (Throwable) {
            $this->storeAvailable = false;
        }

        return $this->storeAvailable;
    }
}
