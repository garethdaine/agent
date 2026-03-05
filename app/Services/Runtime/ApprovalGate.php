<?php

namespace App\Services\Runtime;

use App\Enums\Runtime\RuntimeApprovalState;
use App\Enums\Runtime\RuntimeMode;
use App\Enums\Runtime\RuntimeToolCallStatus;
use App\Models\Runtime\RuntimeApproval;
use App\Models\Runtime\RuntimeSession;
use App\Models\Runtime\RuntimeToolCall;
use App\Models\User;
use App\Support\Agent\AuditLogger;
use Illuminate\Support\Collection;

class ApprovalGate
{
    private const APPROVAL_TTL_MINUTES = 30;

    /**
     * Tools that perform mutations (file writes, deletes, process management).
     * These require approval in standard and full modes.
     */
    private const MUTATION_TOOLS = [
        'fs.write',
        'fs.edit',
        'fs.delete',
        'fs.move',
        'runtime.exec',
        'runtime.spawn',
        'runtime.kill',
        'runtime.run',
        'runtime.stop',
        'agent_api.run_now',
        'agent_api.stop_run',
        'discovery.start',
    ];

    /**
     * Tools that perform external operations (network, browser).
     * These require approval only in full mode.
     */
    private const EXTERNAL_TOOLS = [
        'web.search',
        'web.fetch',
        'browser.navigate',
        'browser.click',
        'browser.type',
        'browser.screenshot',
        'browser.extract',
        'mcp.call',
    ];

    public function __construct(
        private PolicyEngine $policyEngine,
        private AuditLogger $auditLogger,
    ) {}

    /**
     * Determine if a tool call requires approval based on the current mode.
     *
     * Safe mode: No approvals (writes are hard-blocked, not approved)
     * Standard mode: Approve all mutations
     * Full mode: Approve mutations + external calls
     */
    public function requiresApproval(RuntimeMode $mode, string $toolName, array $args = [], ?RuntimeSession $session = null): bool
    {
        if ($mode === RuntimeMode::Safe) {
            return false;
        }

        if ($session?->isToolAutoApproved($toolName)) {
            return false;
        }

        $isMutation = in_array($toolName, self::MUTATION_TOOLS, true);
        $isExternal = in_array($toolName, self::EXTERNAL_TOOLS, true);

        if ($mode === RuntimeMode::Standard && $isMutation) {
            return $this->policyEngine->requiresApproval($mode, 'mutations');
        }

        if ($mode === RuntimeMode::Full) {
            if ($isMutation && $this->policyEngine->requiresApproval($mode, 'mutations')) {
                return true;
            }
            if ($isExternal && $this->policyEngine->requiresApproval($mode, 'external')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Create an approval request for a tool call.
     *
     * @param  RuntimeToolCall  $toolCall  The tool call requiring approval
     * @param  User  $requestedBy  The user who triggered the tool call
     * @return RuntimeApproval The created approval record
     */
    public function createApprovalRequest(RuntimeToolCall $toolCall, User $requestedBy): RuntimeApproval
    {
        return RuntimeApproval::create([
            'runtime_tool_call_id' => $toolCall->id,
            'requested_by' => $requestedBy->id,
            'state' => RuntimeApprovalState::Pending,
            'expires_at' => now()->addMinutes(self::APPROVAL_TTL_MINUTES),
        ]);
    }

    /**
     * Approve a pending approval request and update the associated tool call.
     *
     * @param  RuntimeApproval  $approval  The approval to approve
     * @param  User  $decider  The user making the approval decision
     * @param  string|null  $reason  Optional reason for the approval
     * @return bool True if approval was successful, false if not in pending state
     */
    /**
     * @param  bool  $allowAlways  If true, add the tool to the session's auto-approval list
     */
    public function approve(RuntimeApproval $approval, User $decider, ?string $reason = null, bool $allowAlways = false): bool
    {
        if ($approval->state !== RuntimeApprovalState::Pending) {
            return false;
        }

        if ($this->isExpired($approval)) {
            $this->expireApproval($approval);

            return false;
        }

        $approval->update([
            'state' => RuntimeApprovalState::Approved,
            'decision_by' => $decider->id,
            'decision_reason' => $reason ?? ($allowAlways ? 'allow_always' : null),
        ]);

        $approval->toolCall->update([
            'status' => RuntimeToolCallStatus::Approved,
            'approved_at' => now(),
        ]);

        if ($allowAlways) {
            $session = $approval->toolCall->turn->runtimeSession;
            $toolName = $approval->toolCall->tool_name;
            $session?->addToolAutoApproval($toolName);
        }

        $this->auditLogger->recordSystemAction(
            'runtime.approval.approved',
            'runtime_approval',
            $approval->id,
            ['state', 'decision_by', 'decision_reason'],
        );

        return true;
    }

    /**
     * Deny a pending approval request and update the associated tool call.
     *
     * @param  RuntimeApproval  $approval  The approval to deny
     * @param  User  $decider  The user making the denial decision
     * @param  string|null  $reason  Optional reason for the denial
     * @return bool True if denial was successful, false if not in pending state
     */
    public function deny(RuntimeApproval $approval, User $decider, ?string $reason = null): bool
    {
        if ($approval->state !== RuntimeApprovalState::Pending) {
            return false;
        }

        $approval->update([
            'state' => RuntimeApprovalState::Denied,
            'decision_by' => $decider->id,
            'decision_reason' => $reason,
        ]);

        $approval->toolCall->update([
            'status' => RuntimeToolCallStatus::Denied,
        ]);

        $this->auditLogger->recordSystemAction(
            'runtime.approval.denied',
            'runtime_approval',
            $approval->id,
            ['state', 'decision_by', 'decision_reason'],
        );

        return true;
    }

    /**
     * Expire stale pending approvals that have passed their TTL.
     *
     * @return int Number of approvals expired
     */
    public function expireStaleApprovals(): int
    {
        $stale = RuntimeApproval::where('state', RuntimeApprovalState::Pending)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->get();

        foreach ($stale as $approval) {
            $this->expireApproval($approval);
        }

        return $stale->count();
    }

    private function isExpired(RuntimeApproval $approval): bool
    {
        return $approval->expires_at !== null && $approval->expires_at->isPast();
    }

    private function expireApproval(RuntimeApproval $approval): void
    {
        $approval->update(['state' => RuntimeApprovalState::Expired]);

        $approval->toolCall->update([
            'status' => RuntimeToolCallStatus::Failed,
            'result_json' => ['error' => 'Approval expired after '.self::APPROVAL_TTL_MINUTES.' minutes'],
        ]);

        $this->auditLogger->recordSystemAction(
            'runtime.approval.expired',
            'runtime_approval',
            $approval->id,
            ['state'],
        );
    }

    /**
     * Get all pending approvals for a runtime session.
     *
     * @param  RuntimeSession  $session  The session to get pending approvals for
     * @return Collection<int, RuntimeApproval> Collection of pending approvals
     */
    public function getPendingApprovals(RuntimeSession $session): Collection
    {
        return RuntimeApproval::whereHas('toolCall.turn', function ($query) use ($session) {
            $query->where('runtime_session_id', $session->id);
        })->where('state', RuntimeApprovalState::Pending)->get();
    }
}
