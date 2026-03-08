<?php

declare(strict_types=1);

namespace App\Support\Compliance;

use App\Support\Agent\FeatureFlagManager;

/**
 * Resolves compliance flags with hierarchical resolution.
 *
 * Resolution order:
 * 1. Check for tenant-specific override (if tenantId provided)
 * 2. Fall back to global default from config
 *
 * Stricter-only policy:
 * - Tenant overrides can only make policy stricter, not weaker
 * - For boolean flags: tenant can enable but not disable
 * - For enforcement mode: tenant can escalate (advisory -> warning -> strict) but not de-escalate
 */
class ComplianceFlagResolver
{
    /**
     * Enforcement mode ranking (higher = stricter).
     *
     * @var array<string, int>
     */
    private const MODE_RANK = [
        'advisory' => 0,
        'warning' => 1,
        'strict' => 2,
    ];

    public function __construct(
        private readonly FeatureFlagManager $flagManager // @phpstan-ignore property.onlyWritten
    ) {}

    /**
     * Resolve a single compliance flag value.
     *
     * @param  string  $key  The compliance flag key (e.g., 'compliance.enabled')
     * @param  int|null  $tenantId  Optional tenant ID for tenant-specific override
     * @return mixed The resolved flag value
     */
    public function resolve(string $key, ?int $tenantId = null): mixed
    {
        $globalValue = $this->getGlobalValue($key);

        if ($tenantId === null) {
            return $globalValue;
        }

        $tenantOverride = $this->getTenantOverride($tenantId, $key);

        if ($tenantOverride === null) {
            return $globalValue;
        }

        return $this->applyStricterOnly($globalValue, $tenantOverride, $key);
    }

    /**
     * Resolve all compliance flags as an associative array.
     *
     * @param  int|null  $tenantId  Optional tenant ID for tenant-specific overrides
     * @return array<string, mixed> Map of flag keys to resolved values
     */
    public function resolveAll(?int $tenantId = null): array
    {
        $flags = [];

        foreach (FeatureFlagManager::getComplianceFlags() as $flag) {
            $flags[$flag] = $this->resolve($flag, $tenantId);
        }

        return $flags;
    }

    /**
     * Get the effective enforcement mode for a tenant.
     *
     * @param  int|null  $tenantId  Optional tenant ID
     * @return string One of: 'advisory', 'warning', 'strict'
     */
    public function getEffectiveMode(?int $tenantId = null): string
    {
        $mode = $this->resolve(FeatureFlagManager::COMPLIANCE_ENFORCEMENT_MODE, $tenantId);

        // Ensure we return a valid mode string
        if (! is_string($mode) || ! isset(self::MODE_RANK[$mode])) {
            return 'advisory';
        }

        return $mode;
    }

    /**
     * Check if compliance is enabled for a tenant.
     *
     * @param  int|null  $tenantId  Optional tenant ID
     */
    public function isEnabled(?int $tenantId = null): bool
    {
        return (bool) $this->resolve(FeatureFlagManager::COMPLIANCE_ENABLED, $tenantId);
    }

    /**
     * Get the global default value from config.
     */
    private function getGlobalValue(string $key): mixed
    {
        return config("agent.{$key}");
    }

    /**
     * Get tenant-specific override for a compliance flag.
     *
     * Note: Tenant overrides are intentionally not supported.
     * All tenants follow the same system-wide compliance rules
     * to ensure consistent security and compliance posture.
     *
     * This method is protected to allow testing via anonymous class extension.
     *
     * @param  int|null  $tenantId  The tenant ID (unused)
     * @param  string  $key  The compliance flag key (unused)
     * @return mixed Always returns null
     */
    protected function getTenantOverride(?int $tenantId, string $key): mixed
    {
        return null;
    }

    /**
     * Apply stricter-only merge logic.
     *
     * For boolean flags: tenant can enable but not disable.
     * For enforcement mode: tenant can escalate but not de-escalate.
     *
     * @param  mixed  $global  The global default value
     * @param  mixed  $tenant  The tenant override value
     * @param  string  $key  The flag key (used to determine merge strategy)
     * @return mixed The merged value following stricter-only policy
     */
    private function applyStricterOnly(mixed $global, mixed $tenant, string $key): mixed
    {
        // For boolean gates: tenant can enable but not disable
        if (is_bool($global)) {
            // If global is true, it stays true (tenant cannot weaken)
            // If global is false, tenant can enable it (making it stricter)
            return $global || (bool) $tenant;
        }

        // For enforcement mode: strict > warning > advisory
        if ($key === FeatureFlagManager::COMPLIANCE_ENFORCEMENT_MODE) {
            $globalRank = self::MODE_RANK[$global] ?? 0;
            $tenantRank = self::MODE_RANK[$tenant] ?? 0;

            // Only use tenant value if it's stricter (higher rank)
            return $tenantRank > $globalRank ? $tenant : $global;
        }

        // For other types, use tenant value (no stricter-only logic defined)
        return $tenant;
    }
}
