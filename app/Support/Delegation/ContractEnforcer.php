<?php

declare(strict_types=1);

namespace App\Support\Delegation;

use App\Models\DelegateeProfile;

/**
 * Enforces policy constraints on delegation contracts by narrowing scope.
 *
 * Enforcement Logic:
 * - Intersects authority_scope.allowed_paths with profile config boundaries
 * - Intersects authority_scope.env_whitelist with profile env boundaries
 * - Caps max_runtime_seconds to the smaller of profile limit or system ceiling (86400)
 * - Records warnings when any scope is narrowed
 *
 * Conditions for Correctness:
 * - DelegateeProfile has config_json with optional allowed_paths, env_whitelist, max_runtime_seconds
 * - System ceiling for max_runtime_seconds is 86400 (24 hours)
 *
 * Not Handled:
 * - Deep validation of path formats (assumed already validated by ContractValidator/PathPolicy)
 * - Enforcement of time_constraints.deadline_ts
 * - PathPolicy/EnvPolicy integration for env value validation (assumes whitelist is sufficient)
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

        if ($wasNarrowed) {
            return EnforcementResult::narrowed($narrowedConfig, $warnings);
        }

        return EnforcementResult::unchanged($narrowedConfig);
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
        // If contract has no paths, nothing to narrow
        if ($contractPaths === null || $contractPaths === []) {
            return null;
        }

        // If profile has no path restrictions, contract paths pass through unchanged
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
        // If contract has no env whitelist, nothing to narrow
        if ($contractEnv === null || $contractEnv === []) {
            return null;
        }

        // If profile has no env restrictions, contract env passes through unchanged
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
        // If contract has no runtime specified, nothing to cap
        if ($contractRuntime === null) {
            return null;
        }

        // Determine the effective ceiling (smaller of profile limit or system ceiling)
        $effectiveCeiling = self::SYSTEM_MAX_RUNTIME_SECONDS;
        if ($profileLimit !== null && $profileLimit < self::SYSTEM_MAX_RUNTIME_SECONDS) {
            $effectiveCeiling = $profileLimit;
        }

        // Cap if contract exceeds effective ceiling
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
