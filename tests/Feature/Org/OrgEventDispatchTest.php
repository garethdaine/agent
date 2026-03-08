<?php

declare(strict_types=1);

namespace Tests\Feature\Org;

use App\Events\Org\OrgAgentProfileArchived;
use App\Events\Org\OrgAgentProfileCreated;
use App\Events\Org\OrgAgentProfileRestored;
use App\Events\Org\OrgAgentProfileUpdated;
use App\Models\DelegateeProfile;
use App\Models\User;
use App\Support\Org\OrgAgentProfileService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class OrgEventDispatchTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_profile_dispatches_created_event(): void
    {
        Event::fake([OrgAgentProfileCreated::class]);

        $user = User::factory()->create();
        $delegatee = DelegateeProfile::factory()->for($user)->create();
        $service = app(OrgAgentProfileService::class);

        $service->create($user, [
            'name' => 'test-agent',
            'role_slug' => 'dev',
            'role_description' => 'Test',
            'delegatee_profile_id' => $delegatee->id,
            'capability_bindings' => [],
            'authority_overrides' => [],
        ]);

        Event::assertDispatched(OrgAgentProfileCreated::class);
    }

    public function test_created_event_contains_profile_and_correlation_id(): void
    {
        Event::fake([OrgAgentProfileCreated::class]);

        $user = User::factory()->create();
        $delegatee = DelegateeProfile::factory()->for($user)->create();
        $service = app(OrgAgentProfileService::class);

        $profile = $service->create($user, [
            'name' => 'test-agent-event',
            'role_slug' => 'dev',
            'role_description' => 'Test',
            'delegatee_profile_id' => $delegatee->id,
            'capability_bindings' => [],
            'authority_overrides' => [],
        ]);

        Event::assertDispatched(OrgAgentProfileCreated::class, function ($event) use ($profile) {
            return $event->profile->id === $profile->id
                && ! empty($event->correlationId);
        });
    }

    public function test_updating_profile_dispatches_updated_event(): void
    {
        Event::fake([OrgAgentProfileUpdated::class]);

        $user = User::factory()->create();
        $delegatee = DelegateeProfile::factory()->for($user)->create();
        $service = app(OrgAgentProfileService::class);

        $profile = \App\Models\OrgAgentProfile::factory()->forUser($user)->create([
            'delegatee_profile_id' => $delegatee->id,
        ]);

        $service->update($profile, [
            'name' => 'updated-name',
        ]);

        Event::assertDispatched(OrgAgentProfileUpdated::class);
    }

    public function test_updated_event_contains_before_and_after_state(): void
    {
        Event::fake([OrgAgentProfileUpdated::class]);

        $user = User::factory()->create();
        $delegatee = DelegateeProfile::factory()->for($user)->create();
        $service = app(OrgAgentProfileService::class);

        $profile = \App\Models\OrgAgentProfile::factory()->forUser($user)->create([
            'name' => 'original-name',
            'delegatee_profile_id' => $delegatee->id,
        ]);

        $service->update($profile, [
            'name' => 'new-name',
        ]);

        Event::assertDispatched(OrgAgentProfileUpdated::class, function ($event) {
            return $event->before['name'] === 'original-name'
                && $event->after['name'] === 'new-name';
        });
    }

    public function test_archiving_profile_dispatches_archived_event(): void
    {
        Event::fake([OrgAgentProfileArchived::class]);

        $user = User::factory()->create();
        $delegatee = DelegateeProfile::factory()->for($user)->create();
        $service = app(OrgAgentProfileService::class);

        $profile = \App\Models\OrgAgentProfile::factory()->forUser($user)->create([
            'delegatee_profile_id' => $delegatee->id,
        ]);

        $service->archive($profile);

        Event::assertDispatched(OrgAgentProfileArchived::class);
    }

    public function test_archived_event_contains_profile(): void
    {
        Event::fake([OrgAgentProfileArchived::class]);

        $user = User::factory()->create();
        $delegatee = DelegateeProfile::factory()->for($user)->create();
        $service = app(OrgAgentProfileService::class);

        $profile = \App\Models\OrgAgentProfile::factory()->forUser($user)->create([
            'delegatee_profile_id' => $delegatee->id,
        ]);

        $service->archive($profile);

        Event::assertDispatched(OrgAgentProfileArchived::class, function ($event) use ($profile) {
            return $event->profile->id === $profile->id
                && ! empty($event->correlationId);
        });
    }

    public function test_restoring_profile_dispatches_restored_event(): void
    {
        Event::fake([OrgAgentProfileRestored::class]);

        $user = User::factory()->create();
        $delegatee = DelegateeProfile::factory()->for($user)->create();
        $service = app(OrgAgentProfileService::class);

        $profile = \App\Models\OrgAgentProfile::factory()->forUser($user)->archived()->create([
            'delegatee_profile_id' => $delegatee->id,
        ]);

        $service->restore($profile);

        Event::assertDispatched(OrgAgentProfileRestored::class);
    }

    public function test_restored_event_contains_profile(): void
    {
        Event::fake([OrgAgentProfileRestored::class]);

        $user = User::factory()->create();
        $delegatee = DelegateeProfile::factory()->for($user)->create();
        $service = app(OrgAgentProfileService::class);

        $profile = \App\Models\OrgAgentProfile::factory()->forUser($user)->archived()->create([
            'delegatee_profile_id' => $delegatee->id,
        ]);

        $service->restore($profile);

        Event::assertDispatched(OrgAgentProfileRestored::class, function ($event) use ($profile) {
            return $event->profile->id === $profile->id
                && ! empty($event->correlationId);
        });
    }

    public function test_audit_log_created_on_profile_creation(): void
    {
        $user = User::factory()->create();
        $delegatee = DelegateeProfile::factory()->for($user)->create();
        $service = app(OrgAgentProfileService::class);

        $profile = $service->create($user, [
            'name' => 'audit-test-agent',
            'role_slug' => 'dev',
            'role_description' => 'Test',
            'delegatee_profile_id' => $delegatee->id,
            'capability_bindings' => [],
            'authority_overrides' => [],
        ]);

        $this->assertDatabaseHas('agent_audit_logs', [
            'target_type' => 'org_agent_profile',
            'target_id' => $profile->id,
            'action' => 'created',
            'actor_type' => 'org',
        ]);
    }

    public function test_audit_log_created_on_profile_update(): void
    {
        $user = User::factory()->create();
        $delegatee = DelegateeProfile::factory()->for($user)->create();
        $service = app(OrgAgentProfileService::class);

        $profile = \App\Models\OrgAgentProfile::factory()->forUser($user)->create([
            'delegatee_profile_id' => $delegatee->id,
        ]);

        $service->update($profile, [
            'role_description' => 'Updated description',
        ]);

        $this->assertDatabaseHas('agent_audit_logs', [
            'target_type' => 'org_agent_profile',
            'target_id' => $profile->id,
            'action' => 'updated',
        ]);
    }

    public function test_audit_log_created_on_profile_archive(): void
    {
        $user = User::factory()->create();
        $delegatee = DelegateeProfile::factory()->for($user)->create();
        $service = app(OrgAgentProfileService::class);

        $profile = \App\Models\OrgAgentProfile::factory()->forUser($user)->create([
            'delegatee_profile_id' => $delegatee->id,
        ]);

        $service->archive($profile);

        $this->assertDatabaseHas('agent_audit_logs', [
            'target_type' => 'org_agent_profile',
            'target_id' => $profile->id,
            'action' => 'archived',
        ]);
    }

    public function test_audit_log_created_on_profile_restore(): void
    {
        $user = User::factory()->create();
        $delegatee = DelegateeProfile::factory()->for($user)->create();
        $service = app(OrgAgentProfileService::class);

        $profile = \App\Models\OrgAgentProfile::factory()->forUser($user)->archived()->create([
            'delegatee_profile_id' => $delegatee->id,
        ]);

        $service->restore($profile);

        $this->assertDatabaseHas('agent_audit_logs', [
            'target_type' => 'org_agent_profile',
            'target_id' => $profile->id,
            'action' => 'restored',
        ]);
    }
}
