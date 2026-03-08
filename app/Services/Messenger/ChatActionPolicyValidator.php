<?php

declare(strict_types=1);

namespace App\Services\Messenger;

use App\DTOs\Messenger\PolicyValidationResult;
use App\Enums\Messenger\ChatActionType;
use App\Models\AgentJob;
use App\Models\AgentJobRun;
use App\Models\ChatAction;
use App\Models\ChatSession;
use App\Models\ConnectorAccount;
use App\Models\User;

class ChatActionPolicyValidator
{
    /**
     * Validate that a user can execute an action.
     */
    public function validate(ChatAction $action, User $user): PolicyValidationResult
    {
        $violations = [];

        // Get session and account context
        $message = $action->message;
        $session = $message?->session;
        $account = $session?->connectorAccount;

        if ($session === null || $account === null) {
            return PolicyValidationResult::denied(
                'Unable to determine session context for action validation'
            );
        }

        // Check channel restrictions
        $channelResult = $this->validateChannelRestrictions($action, $session, $account);
        if (! $channelResult->allowed) {
            return $channelResult;
        }

        // Check user permissions
        $userResult = $this->validateUserPermissions($action, $user);
        if (! $userResult->allowed) {
            return $userResult;
        }

        // Check ownership for resource-specific actions
        $ownershipResult = $this->validateResourceOwnership($action, $user);
        if (! $ownershipResult->allowed) {
            return $ownershipResult;
        }

        // Check command/path/env policies if applicable
        $policyResult = $this->validateAgentPolicies($action, $user);
        if (! $policyResult->allowed) {
            return $policyResult;
        }

        return PolicyValidationResult::allowed();
    }

    /**
     * Validate channel restrictions.
     */
    private function validateChannelRestrictions(
        ChatAction $action,
        ChatSession $session,
        ConnectorAccount $account
    ): PolicyValidationResult {
        $restrictions = $account->config['channel_restrictions'] ?? [];

        if (empty($restrictions)) {
            return PolicyValidationResult::allowed();
        }

        $isRestricted = in_array($session->channel_id, $restrictions, true);

        if (! $isRestricted) {
            return PolicyValidationResult::allowed();
        }

        $actionType = ChatActionType::tryFrom($action->action_type);

        // Restricted channels only allow read operations
        if ($actionType?->isQuery()) {
            return PolicyValidationResult::allowed();
        }

        return PolicyValidationResult::denied(
            "This channel is configured for read-only access. Mutation actions like '{$action->action_type}' are not permitted here.",
            ['channel_restricted']
        );
    }

    /**
     * Validate user has permission for this action type.
     */
    private function validateUserPermissions(
        ChatAction $action,
        User $user
    ): PolicyValidationResult {
        $actionType = ChatActionType::tryFrom($action->action_type);

        if ($actionType === null) {
            return PolicyValidationResult::denied('Unknown action type');
        }

        // Ownership-based validation only - all authenticated users can perform actions
        // on resources they own. Role-based permissions are not implemented.
        return PolicyValidationResult::allowed();
    }

    /**
     * Validate ownership for resource-specific actions.
     */
    private function validateResourceOwnership(
        ChatAction $action,
        User $user
    ): PolicyValidationResult {
        $actionType = ChatActionType::tryFrom($action->action_type);
        $parameters = $action->parameters ?? [];

        if ($actionType === null) {
            return PolicyValidationResult::allowed();
        }

        // Actions that require ownership validation
        $ownershipActions = [
            ChatActionType::JOBS_UPDATE,
            ChatActionType::JOBS_DELETE,
            ChatActionType::RUNS_STOP,
            ChatActionType::RUNS_RETRY,
            ChatActionType::RUNS_STEER,
            ChatActionType::RUNS_RUN_NOW,
        ];

        if (! in_array($actionType, $ownershipActions, true)) {
            return PolicyValidationResult::allowed();
        }

        $jobId = $parameters['job_id'] ?? null;
        $runId = $parameters['run_id'] ?? null;

        // Check job ownership
        if ($jobId !== null) {
            $job = AgentJob::find($jobId);
            if ($job === null) {
                return PolicyValidationResult::denied('Resource not found');
            }
            if ($job->user_id !== $user->id) {
                return PolicyValidationResult::denied('You do not own this resource');
            }
        }

        // Check run ownership via job
        if ($runId !== null) {
            $run = AgentJobRun::with('job')->find($runId);
            if ($run === null || $run->job === null) {
                return PolicyValidationResult::denied('Resource not found');
            }
            if ($run->job->user_id !== $user->id) {
                return PolicyValidationResult::denied('You do not own this resource');
            }
        }

        return PolicyValidationResult::allowed();
    }

    /**
     * Validate against CommandPolicy, PathPolicy, EnvPolicy constraints.
     */
    private function validateAgentPolicies(
        ChatAction $action,
        User $user
    ): PolicyValidationResult {
        $actionType = ChatActionType::tryFrom($action->action_type);
        $parameters = $action->parameters ?? [];

        if ($actionType !== ChatActionType::JOBS_CREATE && $actionType !== ChatActionType::JOBS_UPDATE) {
            return PolicyValidationResult::allowed();
        }

        $command = $parameters['command'] ?? null;

        if ($command === null) {
            return PolicyValidationResult::allowed();
        }

        // Perform basic safety checks for dangerous command patterns.

        $violations = [];

        // Basic command safety checks
        $dangerousPatterns = [
            '/\brm\s+-rf\s+\//' => 'Removing root directory is not allowed',
            '/\bdd\s+.*of=\/dev\//' => 'Direct device writes are not allowed',
            '/\b(mkfs|fdisk|parted)\b/' => 'Disk formatting commands are not allowed',
            '/\bchmod\s+777\s+\//' => 'Changing permissions on root is not allowed',
        ];

        foreach ($dangerousPatterns as $pattern => $message) {
            if (preg_match($pattern, $command) === 1) {
                $violations[] = $message;
            }
        }

        if (! empty($violations)) {
            return PolicyValidationResult::denied(
                'Command violates security policies: '.implode('; ', $violations),
                $violations
            );
        }

        return PolicyValidationResult::allowed();
    }
}
