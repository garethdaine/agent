<?php

declare(strict_types=1);

namespace App\Support\Delegation;

use App\Models\DelegateeProfile;
use App\Models\EscalationIncident;

/**
 * Enforces policy constraints on delegation contracts by narrowing scope.
 *
 * Enforcement Logic:
 * - Intersects authority_scope.allowed_paths with profile config boundaries
 * - Intersects authority_scope.env_whitelist with profile env boundaries
 * - Caps max_runtime_seconds to the smaller of profile limit or system ceiling (86400)
 * - Enforces time_constraints.deadline_ts — triggers escalation if exceeded
 * - Validates resource quotas defined in contract against profile limits
 * - Validates delegatee capability_profile matches task requirements
 * - Records warnings when any scope is narrowed
 */
class ContractEnforcer
{
    private const SYSTEM_MAX_RUNTIME_SECONDS = 86400; // 24 hours

    /**
     * Enforce policy constraints on a contract, narrowing scope as needed.
     *
     * @param  array<string, mixed>  $contractJson
     */
    public function enforce(array $contractJson, DelegateeProfile $profile): EnforcementResult
    {
        $narrowedConfig = $contractJson;
        $warnings = [];
        $wasNarrowed = false;

        $profileConfig = $profile->config_json ?? [];

        // Narrow allowed_paths
        $pathResult = $this->narrowPaths(
            $narrowedConfig['authority_scope']['allowed_paths'] ?? null,
            $profileConfig['allowed_paths'] ?? null
        );
        if ($pathResult !== null) {
            $narrowedConfig['authority_scope']['allowed_paths'] = $pathResult['narrowed'];
            if ($pathResult['was_narrowed']) {
                $warnings[] = 'allowed_paths was narrowed to profile boundaries';
                $wasNarrowed = true;
            }
        }

        // Narrow env_whitelist
        $envResult = $this->narrowEnvWhitelist(
            $narrowedConfig['authority_scope']['env_whitelist'] ?? null,
            $profileConfig['env_whitelist'] ?? null
        );
        if ($envResult !== null) {
            $narrowedConfig['authority_scope']['env_whitelist'] = $envResult['narrowed'];
            if ($envResult['was_narrowed']) {
                $warnings[] = 'env_whitelist was narrowed to profile boundaries';
                $wasNarrowed = true;
            }
        }

        // Cap max_runtime_seconds
        $runtimeResult = $this->capMaxRuntime(
            $narrowedConfig['authority_scope']['max_runtime_seconds'] ?? null,
            $profileConfig['max_runtime_seconds'] ?? null
        );
        if ($runtimeResult !== null) {
            $narrowedConfig['authority_scope']['max_runtime_seconds'] = $runtimeResult['capped'];
            if ($runtimeResult['was_capped']) {
                $warnings[] = sprintf(
                    'max_runtime_seconds was capped from %d to %d',
                    $runtimeResult['original'],
                    $runtimeResult['capped']
                );
                $wasNarrowed = true;
            }
        }

        // Enforce deadline
        $deadlineViolation = $this->checkDeadline($narrowedConfig);
        if ($deadlineViolation !== null) {
            $warnings[] = $deadlineViolation;
            $wasNarrowed = true;
        }

        // Enforce resource quotas
        $quotaWarnings = $this->enforceResourceQuotas($narrowedConfig, $profileConfig);
        if ($quotaWarnings !== []) {
            $warnings = array_merge($warnings, $quotaWarnings);
            $wasNarrowed = true;
        }

        // Validate capability requirements
        $capabilityWarning = $this->validateCapabilities($narrowedConfig, $profile);
        if ($capabilityWarning !== null) {
            $warnings[] = $capabilityWarning;
            $wasNarrowed = true;
        }

        if ($wasNarrowed) {
            return EnforcementResult::narrowed($narrowedConfig, $warnings);
        }

        return EnforcementResult::unchanged($narrowedConfig);
    }

    /**
     * Check deadline enforcement and trigger escalation if exceeded.
     *
     * @param  array<string, mixed>  $contractJson
     */
    public function checkDeadline(array $contractJson): ?string
    {
        $deadlineTs = $contractJson['time_constraints']['deadline_ts'] ?? null;
        if ($deadlineTs === null) {
            return null;
        }

        $deadline = is_numeric($deadlineTs) ? (int) $deadlineTs : strtotime((string) $deadlineTs);
        if ($deadline === false) {
            return null;
        }

        if (time() <= $deadline) {
            return null;
        }

        $criticality = $contractJson['criticality'] ?? 'normal';
        $this->triggerDeadlineEscalation($contractJson, $criticality);

        return sprintf('deadline_exceeded: deadline_ts %d has passed (criticality: %s)', $deadline, $criticality);
    }

    /**
     * Trigger escalation based on criticality level.
     *
     * @param  array<string, mixed>  $contractJson
     */
    private function triggerDeadlineEscalation(array $contractJson, string $criticality): void
    {
        $triggerType = match ($criticality) {
            'critical' => 'deadline_critical',
            'high' => 'deadline_high',
            default => 'deadline_normal',
        };

        $status = match ($criticality) {
            'critical' => 'open',
            'high' => 'investigating',
            default => 'open',
        };

        EscalationIncident::create([
            'workflow_key' => 'delegation.deadline',
            'trigger_type' => $triggerType,
            'status' => $status,
            'reason_code' => 'DEADLINE_EXCEEDED',
            'reason' => sprintf(
                'Deadline exceeded for task (criticality: %s). Deadline was %s.',
                $criticality,
                date('Y-m-d H:i:s', (int) ($contractJson['time_constraints']['deadline_ts'] ?? 0))
            ),
            'opened_at' => now(),
            'last_triggered_at' => now(),
            'metadata_json' => [
                'criticality' => $criticality,
                'deadline_ts' => $contractJson['time_constraints']['deadline_ts'] ?? null,
                'contract_name' => $contractJson['name'] ?? null,
            ],
        ]);
    }

    /**
     * Enforce resource quotas defined in the contract against profile limits.
     *
     * @param  array<string, mixed>  $contractJson
     * @param  array<string, mixed>  $profileConfig
     * @return array<int, string>
     */
    private function enforceResourceQuotas(array &$contractJson, array $profileConfig): array
    {
        $warnings = [];
        $contractQuotas = $contractJson['resource_quotas'] ?? null;
        $profileQuotas = $profileConfig['resource_quotas'] ?? null;

        if ($contractQuotas === null || ! is_array($contractQuotas)) {
            return [];
        }

        if ($profileQuotas === null || ! is_array($profileQuotas)) {
            return [];
        }

        foreach ($contractQuotas as $resource => $contractLimit) {
            if (! is_numeric($contractLimit)) {
                continue;
            }

            $profileLimit = $profileQuotas[$resource] ?? null;
            if ($profileLimit === null || ! is_numeric($profileLimit)) {
                continue;
            }

            if ((float) $contractLimit > (float) $profileLimit) {
                $contractJson['resource_quotas'][$resource] = $profileLimit;
                $warnings[] = sprintf(
                    'resource_quota.%s was capped from %s to %s',
                    $resource,
                    $contractLimit,
                    $profileLimit
                );
            }
        }

        return $warnings;
    }

    /**
     * Validate that delegatee capabilities match task requirements.
     *
     * @param  array<string, mixed>  $contractJson
     */
    private function validateCapabilities(array $contractJson, DelegateeProfile $profile): ?string
    {
        $requiredCapabilities = $contractJson['required_capabilities'] ?? null;
        if ($requiredCapabilities === null || ! is_array($requiredCapabilities) || $requiredCapabilities === []) {
            return null;
        }

        $capabilityProfile = $profile->capability_profile ?? [];
        $profileCapabilities = $capabilityProfile['capabilities'] ?? [];

        if ($profileCapabilities === []) {
            // Fall back to the BelongsToMany capabilities relationship slugs
            $profileCapabilities = $profile->capabilities()->pluck('slug')->all();
        }

        $missing = array_diff($requiredCapabilities, $profileCapabilities);
        if ($missing === []) {
            return null;
        }

        return sprintf(
            'delegatee missing required capabilities: %s',
            implode(', ', $missing)
        );
    }

    /**
     * Narrow allowed paths to intersection with profile boundaries.
     *
     * @param  array<int, string>|null  $contractPaths
     * @param  array<int, string>|null  $profilePaths
     * @return array{narrowed: array<int, string>, was_narrowed: bool}|null
     */
    private function narrowPaths(?array $contractPaths, ?array $profilePaths): ?array
    {
        if ($contractPaths === null || $contractPaths === []) {
            return null;
        }

        if ($profilePaths === null || $profilePaths === []) {
            return null;
        }

        $intersection = array_values(array_intersect($contractPaths, $profilePaths));
        $wasNarrowed = count($intersection) !== count($contractPaths);

        return [
            'narrowed' => $intersection,
            'was_narrowed' => $wasNarrowed,
        ];
    }

    /**
     * Narrow env whitelist to intersection with profile boundaries.
     *
     * @param  array<int, string>|null  $contractEnv
     * @param  array<int, string>|null  $profileEnv
     * @return array{narrowed: array<int, string>, was_narrowed: bool}|null
     */
    private function narrowEnvWhitelist(?array $contractEnv, ?array $profileEnv): ?array
    {
        if ($contractEnv === null || $contractEnv === []) {
            return null;
        }

        if ($profileEnv === null || $profileEnv === []) {
            return null;
        }

        $intersection = array_values(array_intersect($contractEnv, $profileEnv));
        $wasNarrowed = count($intersection) !== count($contractEnv);

        return [
            'narrowed' => $intersection,
            'was_narrowed' => $wasNarrowed,
        ];
    }

    /**
     * Cap max_runtime_seconds to profile limit or system ceiling.
     *
     * @return array{capped: int, original: int, was_capped: bool}|null
     */
    private function capMaxRuntime(?int $contractRuntime, ?int $profileLimit): ?array
    {
        if ($contractRuntime === null) {
            return null;
        }

        $effectiveCeiling = self::SYSTEM_MAX_RUNTIME_SECONDS;
        if ($profileLimit !== null && $profileLimit < self::SYSTEM_MAX_RUNTIME_SECONDS) {
            $effectiveCeiling = $profileLimit;
        }

        if ($contractRuntime > $effectiveCeiling) {
            return [
                'capped' => $effectiveCeiling,
                'original' => $contractRuntime,
                'was_capped' => true,
            ];
        }

        return [
            'capped' => $contractRuntime,
            'original' => $contractRuntime,
            'was_capped' => false,
        ];
    }
}
