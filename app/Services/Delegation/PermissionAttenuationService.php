<?php

declare(strict_types=1);

namespace App\Services\Delegation;

/**
 * Attenuates permissions at delegation boundaries.
 *
 * Core invariant: child task permissions can never exceed parent task permissions.
 * At each delegation boundary, permissions are narrowed (intersected) — never widened.
 */
class PermissionAttenuationService
{
    /**
     * Compute the attenuated permission set for a child task.
     *
     * @param  array<string, mixed>  $parentPermissions  The parent task's effective permissions
     * @param  array<string, mixed>  $childContract  The child task's contract_json
     * @return array<string, mixed> The attenuated permission set for the child
     */
    public function attenuate(array $parentPermissions, array $childContract): array
    {
        $childScope = $childContract['authority_scope'] ?? [];
        $attenuated = $childContract;
        $attenuated['authority_scope'] = $this->attenuateScope(
            $parentPermissions['authority_scope'] ?? [],
            $childScope
        );

        return $attenuated;
    }

    /**
     * Validate that a child contract does not exceed parent permissions.
     *
     * @param  array<string, mixed>  $parentPermissions
     * @param  array<string, mixed>  $childContract
     * @return array<int, string> List of violations (empty if valid)
     */
    public function validate(array $parentPermissions, array $childContract): array
    {
        $violations = [];
        $parentScope = $parentPermissions['authority_scope'] ?? [];
        $childScope = $childContract['authority_scope'] ?? [];

        // Check allowed_paths
        $parentPaths = $parentScope['allowed_paths'] ?? null;
        $childPaths = $childScope['allowed_paths'] ?? null;
        if ($parentPaths !== null && is_array($parentPaths) && $childPaths !== null && is_array($childPaths)) {
            $extraPaths = array_diff($childPaths, $parentPaths);
            if ($extraPaths !== []) {
                $violations[] = sprintf(
                    'child allowed_paths exceeds parent: %s',
                    implode(', ', $extraPaths)
                );
            }
        } elseif ($parentPaths !== null && is_array($parentPaths) && ($childPaths === null || $childPaths === [])) {
            // Parent has restrictions but child has none — child is wider
            $violations[] = 'child has unrestricted allowed_paths but parent has restrictions';
        }

        // Check env_whitelist
        $parentEnv = $parentScope['env_whitelist'] ?? null;
        $childEnv = $childScope['env_whitelist'] ?? null;
        if ($parentEnv !== null && is_array($parentEnv) && $childEnv !== null && is_array($childEnv)) {
            $extraEnv = array_diff($childEnv, $parentEnv);
            if ($extraEnv !== []) {
                $violations[] = sprintf(
                    'child env_whitelist exceeds parent: %s',
                    implode(', ', $extraEnv)
                );
            }
        } elseif ($parentEnv !== null && is_array($parentEnv) && ($childEnv === null || $childEnv === [])) {
            $violations[] = 'child has unrestricted env_whitelist but parent has restrictions';
        }

        // Check max_runtime_seconds
        $parentRuntime = $parentScope['max_runtime_seconds'] ?? null;
        $childRuntime = $childScope['max_runtime_seconds'] ?? null;
        if ($parentRuntime !== null && $childRuntime !== null && (int) $childRuntime > (int) $parentRuntime) {
            $violations[] = sprintf(
                'child max_runtime_seconds (%d) exceeds parent (%d)',
                $childRuntime,
                $parentRuntime
            );
        }

        // Check resource quotas
        $parentQuotas = $parentPermissions['resource_quotas'] ?? [];
        $childQuotas = $childContract['resource_quotas'] ?? [];
        if (is_array($parentQuotas) && is_array($childQuotas)) {
            foreach ($childQuotas as $resource => $childLimit) {
                $parentLimit = $parentQuotas[$resource] ?? null;
                if ($parentLimit !== null && is_numeric($childLimit) && is_numeric($parentLimit)) {
                    if ((float) $childLimit > (float) $parentLimit) {
                        $violations[] = sprintf(
                            'child resource_quota.%s (%s) exceeds parent (%s)',
                            $resource,
                            $childLimit,
                            $parentLimit
                        );
                    }
                }
            }
        }

        return $violations;
    }

    /**
     * Attenuate an authority_scope to the intersection with parent scope.
     *
     * @param  array<string, mixed>  $parentScope
     * @param  array<string, mixed>  $childScope
     * @return array<string, mixed>
     */
    private function attenuateScope(array $parentScope, array $childScope): array
    {
        $attenuated = $childScope;

        // Intersect allowed_paths
        $parentPaths = $parentScope['allowed_paths'] ?? null;
        $childPaths = $childScope['allowed_paths'] ?? null;
        if ($parentPaths !== null && is_array($parentPaths)) {
            if ($childPaths !== null && is_array($childPaths)) {
                $attenuated['allowed_paths'] = array_values(array_intersect($childPaths, $parentPaths));
            } else {
                // Child has no restrictions but parent does — adopt parent restrictions
                $attenuated['allowed_paths'] = $parentPaths;
            }
        }

        // Intersect env_whitelist
        $parentEnv = $parentScope['env_whitelist'] ?? null;
        $childEnv = $childScope['env_whitelist'] ?? null;
        if ($parentEnv !== null && is_array($parentEnv)) {
            if ($childEnv !== null && is_array($childEnv)) {
                $attenuated['env_whitelist'] = array_values(array_intersect($childEnv, $parentEnv));
            } else {
                $attenuated['env_whitelist'] = $parentEnv;
            }
        }

        // Cap max_runtime_seconds
        $parentRuntime = $parentScope['max_runtime_seconds'] ?? null;
        $childRuntime = $childScope['max_runtime_seconds'] ?? null;
        if ($parentRuntime !== null) {
            if ($childRuntime !== null) {
                $attenuated['max_runtime_seconds'] = min((int) $childRuntime, (int) $parentRuntime);
            } else {
                $attenuated['max_runtime_seconds'] = (int) $parentRuntime;
            }
        }

        return $attenuated;
    }
}
