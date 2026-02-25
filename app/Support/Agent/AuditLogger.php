<?php

namespace App\Support\Agent;

use App\Models\AgentAuditLog;
use App\Models\ChatAction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AuditLogger
{
    /**
     * @param  array<int, string>  $changedFields
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    public function recordUserAction(
        Request $request,
        string $action,
        string $targetType,
        string|int $targetId,
        ?int $ownerUserId,
        array $changedFields = [],
        ?array $before = null,
        ?array $after = null,
        string $outcome = 'success',
        ?string $errorCode = null,
        ?string $errorMessage = null,
    ): AgentAuditLog {
        $actorId = $request->user()?->id;

        return $this->record(
            userId: $ownerUserId,
            actorType: 'user',
            actorId: $actorId,
            action: $action,
            targetType: $targetType,
            targetId: (string) $targetId,
            changedFields: $changedFields,
            before: $before,
            after: $after,
            requestId: (string) ($request->header('X-Request-Id') ?? ''),
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
            hostname: gethostname() ?: null,
            outcome: $outcome,
            errorCode: $errorCode,
            errorMessage: $errorMessage,
        );
    }

    /**
     * @param  array<int, string>  $changedFields
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    public function recordSystemAction(
        string $action,
        string $targetType,
        string|int $targetId,
        array $changedFields = [],
        ?array $before = null,
        ?array $after = null,
        string $outcome = 'success',
        ?string $errorCode = null,
        ?string $errorMessage = null,
    ): AgentAuditLog {
        return $this->record(
            userId: null,
            actorType: 'system',
            actorId: null,
            action: $action,
            targetType: $targetType,
            targetId: (string) $targetId,
            changedFields: $changedFields,
            before: $before,
            after: $after,
            requestId: null,
            ipAddress: null,
            userAgent: null,
            hostname: gethostname() ?: null,
            outcome: $outcome,
            errorCode: $errorCode,
            errorMessage: $errorMessage,
        );
    }

    /**
     * Record an action initiated through the messenger control plane.
     *
     * @param  array<int, string>  $changedFields
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    public function recordMessengerAction(
        ChatAction $action,
        string $targetType,
        string|int $targetId,
        array $changedFields = [],
        ?array $before = null,
        ?array $after = null,
        string $outcome = 'success',
        ?string $errorCode = null,
        ?string $errorMessage = null,
    ): AgentAuditLog {
        $message = $action->message;
        $session = $message?->session;
        $connector = $message?->connectorAccount ?? $session?->connectorAccount;

        return $this->record(
            userId: $session?->user_id,
            actorType: 'messenger',
            actorId: $action->id,
            action: $action->action_type,
            targetType: $targetType,
            targetId: (string) $targetId,
            changedFields: $changedFields,
            before: $before,
            after: $after,
            requestId: $session?->id,
            ipAddress: null,
            userAgent: $connector ? "messenger/{$connector->provider}" : 'messenger/unknown',
            hostname: gethostname() ?: null,
            outcome: $outcome,
            errorCode: $errorCode,
            errorMessage: $errorMessage,
        );
    }

    /**
     * @param  array<int, string>  $changedFields
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    private function record(
        ?int $userId,
        string $actorType,
        ?int $actorId,
        string $action,
        string $targetType,
        string $targetId,
        array $changedFields,
        ?array $before,
        ?array $after,
        ?string $requestId,
        ?string $ipAddress,
        ?string $userAgent,
        ?string $hostname,
        string $outcome,
        ?string $errorCode,
        ?string $errorMessage,
    ): AgentAuditLog {
        $entry = AgentAuditLog::query()->create([
            'user_id' => $userId,
            'actor_type' => $actorType,
            'actor_id' => $actorId,
            'action' => $action,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'changed_fields_json' => $changedFields,
            'before_json' => $before,
            'after_json' => $after,
            'request_id' => $requestId,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'hostname' => $hostname,
            'outcome' => $outcome,
            'error_code' => $errorCode,
            'error_message' => $errorMessage,
            'created_at' => now('UTC'),
        ]);

        Log::info('agent.audit', [
            'audit_id' => $entry->id,
            'user_id' => $entry->user_id,
            'actor_type' => $entry->actor_type,
            'actor_id' => $entry->actor_id,
            'action' => $entry->action,
            'target_type' => $entry->target_type,
            'target_id' => $entry->target_id,
            'changed_fields' => $entry->changed_fields_json,
            'outcome' => $entry->outcome,
            'error_code' => $entry->error_code,
            'created_at' => optional($entry->created_at)?->toIso8601String(),
        ]);

        return $entry;
    }
}
