<?php

namespace App\Support\Agent;

use App\Models\AgentFeatureSetting;
use Illuminate\Support\Facades\Schema;
use Throwable;

class FeatureFlagManager
{
    public const DELEGATION_ENABLED = 'delegation.enabled';

    public const DELEGATION_UI_ENABLED = 'delegation.ui_enabled';

    public const ADVERSARIAL_REVIEW_ENABLED = 'agent.interrogation.adversarial_review_enabled';

    // Compliance flag constants
    public const COMPLIANCE_ENABLED = 'compliance.enabled';

    public const COMPLIANCE_ENFORCEMENT_MODE = 'compliance.enforcement_mode';

    public const COMPLIANCE_PLAN_GATE = 'compliance.plan_gate_enabled';

    public const COMPLIANCE_VERIFICATION_GATE = 'compliance.verification_gate_enabled';

    public const COMPLIANCE_ELEGANCE_GATE = 'compliance.elegance_gate_enabled';

    public const COMPLIANCE_LESSONS = 'compliance.lessons_enabled';

    /**
     * @var array<string, array{label: string, description: string}>
     */
    private const DEFINITIONS = [
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
            self::COMPLIANCE_ENFORCEMENT_MODE,
            self::COMPLIANCE_PLAN_GATE,
            self::COMPLIANCE_VERIFICATION_GATE,
            self::COMPLIANCE_ELEGANCE_GATE,
            self::COMPLIANCE_LESSONS,
        ];
    }

    public function enabled(string $key): bool
    {
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
     * @return array<int, array<string, mixed>>
     */
    public function all(): array
    {
        $stored = $this->storedMap();
        $rows = [];

        foreach (self::DEFINITIONS as $key => $definition) {
            $storedRow = $stored[$key] ?? null;
            $isOverridden = $storedRow !== null;

            $rows[] = [
                'key' => $key,
                'label' => $definition['label'],
                'description' => $definition['description'],
                'default_enabled' => $this->defaultValue($key),
                'is_enabled' => $isOverridden ? (bool) $storedRow['is_enabled'] : $this->defaultValue($key),
                'is_overridden' => $isOverridden,
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

            AgentFeatureSetting::query()->updateOrCreate(
                ['key' => $key],
                [
                    'is_enabled' => (bool) $enabled,
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
