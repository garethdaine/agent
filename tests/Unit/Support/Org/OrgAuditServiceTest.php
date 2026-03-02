<?php

namespace Tests\Unit\Support\Org;

use App\Models\OrgAgentProfile;
use App\Models\User;
use App\Support\Org\OrgAuditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrgAuditServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_logs_mutation_with_required_fields(): void
    {
        $user = User::factory()->create();
        $profile = OrgAgentProfile::factory()->forUser($user)->create();
        $service = app(OrgAuditService::class);

        $service->logMutation(
            action: 'created',
            resource: $profile,
            before: null,
            after: $profile->toArray(),
            actor: $user,
            correlationId: 'test-correlation-123'
        );

        $this->assertDatabaseHas('agent_audit_logs', [
            'target_type' => 'org_agent_profile',
            'target_id' => $profile->id,
            'action' => 'created',
            'actor_id' => (string) $user->id,
            'request_id' => 'test-correlation-123',
        ]);
    }

    public function test_logs_update_with_before_and_after_state(): void
    {
        $user = User::factory()->create();
        $profile = OrgAgentProfile::factory()->forUser($user)->create([
            'name' => 'original-name',
        ]);
        $service = app(OrgAuditService::class);

        $before = ['name' => 'original-name'];
        $after = ['name' => 'updated-name'];

        $service->logMutation(
            action: 'updated',
            resource: $profile,
            before: $before,
            after: $after,
            actor: $user,
            correlationId: 'update-correlation-456'
        );

        $this->assertDatabaseHas('agent_audit_logs', [
            'target_type' => 'org_agent_profile',
            'target_id' => $profile->id,
            'action' => 'updated',
            'actor_id' => (string) $user->id,
            'request_id' => 'update-correlation-456',
        ]);

        // Verify JSON fields were stored correctly
        $log = \App\Models\AgentAuditLog::where('target_id', $profile->id)
            ->where('action', 'updated')
            ->first();

        $this->assertEquals($before, $log->before_json);
        $this->assertEquals($after, $log->after_json);
    }

    public function test_logs_archive_action(): void
    {
        $user = User::factory()->create();
        $profile = OrgAgentProfile::factory()->forUser($user)->create();
        $service = app(OrgAuditService::class);

        $before = ['archived_at' => null];
        $after = ['archived_at' => now()->toIso8601String()];

        $service->logMutation(
            action: 'archived',
            resource: $profile,
            before: $before,
            after: $after,
            actor: $user,
            correlationId: 'archive-correlation-789'
        );

        $this->assertDatabaseHas('agent_audit_logs', [
            'target_type' => 'org_agent_profile',
            'target_id' => $profile->id,
            'action' => 'archived',
        ]);
    }

    public function test_logs_restore_action(): void
    {
        $user = User::factory()->create();
        $profile = OrgAgentProfile::factory()->forUser($user)->archived()->create();
        $service = app(OrgAuditService::class);

        $before = ['archived_at' => now()->toIso8601String()];
        $after = ['archived_at' => null];

        $service->logMutation(
            action: 'restored',
            resource: $profile,
            before: $before,
            after: $after,
            actor: $user,
            correlationId: 'restore-correlation-012'
        );

        $this->assertDatabaseHas('agent_audit_logs', [
            'target_type' => 'org_agent_profile',
            'target_id' => $profile->id,
            'action' => 'restored',
        ]);
    }

    public function test_sets_actor_type_to_org(): void
    {
        $user = User::factory()->create();
        $profile = OrgAgentProfile::factory()->forUser($user)->create();
        $service = app(OrgAuditService::class);

        $service->logMutation(
            action: 'created',
            resource: $profile,
            before: null,
            after: $profile->toArray(),
            actor: $user,
            correlationId: 'actor-type-test'
        );

        $this->assertDatabaseHas('agent_audit_logs', [
            'target_id' => $profile->id,
            'actor_type' => 'org',
        ]);
    }

    public function test_sets_user_id_from_resource_owner(): void
    {
        $resourceOwner = User::factory()->create();
        $actor = User::factory()->create(); // Different user performing the action
        $profile = OrgAgentProfile::factory()->forUser($resourceOwner)->create();
        $service = app(OrgAuditService::class);

        $service->logMutation(
            action: 'created',
            resource: $profile,
            before: null,
            after: $profile->toArray(),
            actor: $actor,
            correlationId: 'user-id-test'
        );

        // user_id should be the resource owner, actor_id should be the actor
        $this->assertDatabaseHas('agent_audit_logs', [
            'target_id' => $profile->id,
            'user_id' => $resourceOwner->id,
            'actor_id' => (string) $actor->id,
        ]);
    }
}
