<?php

declare(strict_types=1);

namespace App\Services\Runtime;

use App\Enums\Runtime\PolicySnapshotReason;
use App\Enums\Runtime\RuntimeMode;
use App\Models\Runtime\RuntimePolicySnapshot;
use App\Models\Runtime\RuntimeSession;

class PolicyEngine
{
    /**
     * Check if a tool is allowed by the runtime tool deny/allow list.
     *
     * When tool_allow is non-empty, only tools in that list are allowed.
     * Otherwise, tools in tool_deny are denied. Checks both adapter name
     * and qualified name (e.g. fs and fs.write).
     */
    public function isToolAllowedByPolicy(string $toolName, ?string $qualifiedName = null): bool
    {
        $allow = config('runtime.tool_allow', []);
        $deny = config('runtime.tool_deny', []);

        $qualifiedName = $qualifiedName ?? $toolName;

        if ($allow !== []) {
            $allowed = in_array($toolName, $allow, true) || in_array($qualifiedName, $allow, true);

            return $allowed;
        }

        $denied = in_array($toolName, $deny, true) || in_array($qualifiedName, $deny, true);

        return ! $denied;
    }

    /**
     * Check if a capability is allowed for the given mode.
     *
     * Capabilities are defined in config/runtime.php modes.{mode}.capabilities.
     * The wildcard '*' allows all capabilities.
     */
    public function isCapabilityAllowed(RuntimeMode $mode, string $capability): bool
    {
        $modeConfig = $this->getModeConfig($mode);
        $capabilities = $modeConfig['capabilities'] ?? [];

        // Wildcard allows all capabilities
        if (in_array('*', $capabilities, true)) {
            return true;
        }

        return in_array($capability, $capabilities, true);
    }

    /**
     * Check if a category of operations requires approval for the given mode.
     *
     * Approval requirements are defined in config/runtime.php modes.{mode}.approvals_required.
     * Categories include: mutations, external, elevated
     */
    public function requiresApproval(RuntimeMode $mode, string $category): bool
    {
        $modeConfig = $this->getModeConfig($mode);
        $approvalsRequired = $modeConfig['approvals_required'] ?? [];

        return in_array($category, $approvalsRequired, true);
    }

    /**
     * Get the effective policy for a given mode.
     *
     * Returns the complete policy configuration including:
     * - mode: The mode value
     * - capabilities: List of allowed capabilities
     * - approvals_required: List of categories requiring approval
     * - approval_model: The approval enforcement model (default: strict)
     */
    public function getEffectivePolicy(RuntimeMode $mode): array
    {
        $modeConfig = $this->getModeConfig($mode);

        return [
            'mode' => $mode->value,
            'capabilities' => $modeConfig['capabilities'] ?? [],
            'approvals_required' => $modeConfig['approvals_required'] ?? [],
            'approval_model' => config('runtime.approval_model', 'strict'),
        ];
    }

    /**
     * Capture a policy snapshot for forensic audit.
     *
     * Snapshots are captured at session start and mode changes to provide
     * a complete audit trail of the policy in effect at any given time.
     */
    public function captureSnapshot(
        RuntimeSession $session,
        PolicySnapshotReason $reason
    ): RuntimePolicySnapshot {
        return RuntimePolicySnapshot::create([
            'runtime_session_id' => $session->id,
            'snapshot_reason' => $reason,
            'policy_json' => $this->getEffectivePolicy($session->mode), // @phpstan-ignore argument.type
            'captured_at' => now(),
        ]);
    }

    /**
     * Check if an event should trigger a policy snapshot.
     *
     * Triggers are defined in config/runtime.php policy_snapshot_triggers.
     */
    public function shouldTriggerSnapshot(string $event): bool
    {
        $triggers = config('runtime.policy_snapshot_triggers', []);

        return in_array($event, $triggers, true);
    }

    /**
     * Get the mode configuration from runtime config.
     */
    private function getModeConfig(RuntimeMode $mode): array
    {
        return config("runtime.modes.{$mode->value}", [
            'capabilities' => [],
            'approvals_required' => [],
        ]);
    }
}
