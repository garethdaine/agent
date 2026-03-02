<?php

namespace App\Support\Org;

use App\Models\AgentAuditLog;
use App\Models\OrgAgentProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

/**
 * Audit service for org layer mutations.
 *
 * Records mutations to org layer resources in the agent_audit_logs table,
 * enabling comprehensive audit trails for compliance and debugging.
 *
 * Uses the existing AuditLogger table schema with:
 * - target_type/target_id for resource identification
 * - request_id for correlation tracking
 * - actor_type = 'org' for org layer operations
 */
class OrgAuditService
{
    /**
     * Log a mutation to an org layer resource.
     *
     * @param  string  $action  The action performed (created, updated, archived, restored)
     * @param  Model  $resource  The resource that was mutated
     * @param  array<string, mixed>|null  $before  State before mutation (null for create)
     * @param  array<string, mixed>  $after  State after mutation
     * @param  User  $actor  The user who performed the mutation
     * @param  string  $correlationId  Correlation ID for request tracing
     */
    public function logMutation(
        string $action,
        Model $resource,
        ?array $before,
        array $after,
        User $actor,
        string $correlationId
    ): AgentAuditLog {
        $targetType = $this->getTargetType($resource);
        $ownerId = $this->getResourceOwnerId($resource);

        // Calculate changed fields for updates
        $changedFields = $this->calculateChangedFields($before, $after);

        $entry = AgentAuditLog::query()->create([
            'user_id' => $ownerId,
            'actor_type' => 'org',
            'actor_id' => (string) $actor->id,
            'action' => $action,
            'target_type' => $targetType,
            'target_id' => (string) $resource->getKey(),
            'changed_fields_json' => $changedFields,
            'before_json' => $before,
            'after_json' => $after,
            'request_id' => $correlationId,
            'ip_address' => null,
            'user_agent' => null,
            'hostname' => gethostname() ?: null,
            'outcome' => 'success',
            'error_code' => null,
            'error_message' => null,
            'created_at' => now('UTC'),
        ]);

        Log::info('org.audit', [
            'audit_id' => $entry->id,
            'user_id' => $entry->user_id,
            'actor_type' => $entry->actor_type,
            'actor_id' => $entry->actor_id,
            'action' => $entry->action,
            'target_type' => $entry->target_type,
            'target_id' => $entry->target_id,
            'correlation_id' => $correlationId,
            'changed_fields' => $entry->changed_fields_json,
            'outcome' => $entry->outcome,
            'created_at' => optional($entry->created_at)?->toIso8601String(),
        ]);

        return $entry;
    }

    /**
     * Get the target type string for a resource model.
     */
    private function getTargetType(Model $resource): string
    {
        return match ($resource::class) {
            OrgAgentProfile::class => 'org_agent_profile',
            default => strtolower(class_basename($resource)),
        };
    }

    /**
     * Extract the owner user ID from a resource.
     *
     * Looks for user_id attribute on the resource, falling back to null
     * for resources that don't have direct user ownership.
     */
    private function getResourceOwnerId(Model $resource): ?int
    {
        if (isset($resource->user_id)) {
            return $resource->user_id;
        }

        return null;
    }

    /**
     * Calculate the list of fields that changed between before and after states.
     *
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>  $after
     * @return array<int, string>
     */
    private function calculateChangedFields(?array $before, array $after): array
    {
        if ($before === null) {
            // For creates, all fields in after are "changed"
            return array_keys($after);
        }

        $changedFields = [];

        foreach ($after as $key => $value) {
            if (! array_key_exists($key, $before) || $before[$key] !== $value) {
                $changedFields[] = $key;
            }
        }

        return $changedFields;
    }
}
