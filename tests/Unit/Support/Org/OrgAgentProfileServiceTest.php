<?php

namespace Tests\Unit\Support\Org;

use App\Models\DelegateeProfile;
use App\Models\DelegationCapability;
use App\Models\OrgAgentProfile;
use App\Models\User;
use App\Support\Org\Exceptions\AuthorityWideningException;
use App\Support\Org\Exceptions\InvalidDelegateeBindingException;
use App\Support\Org\OrgAgentProfileService;
use Tests\TestCase;

class OrgAgentProfileServiceTest extends TestCase
{
    private OrgAgentProfileService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(OrgAgentProfileService::class);
    }

    public function test_create_profile_with_valid_delegatee_binding(): void
    {
        $user = User::factory()->create();
        $delegatee = DelegateeProfile::factory()->for($user)->create();

        // Get or create capabilities and attach to delegatee
        $readCap = DelegationCapability::firstOrCreate(['slug' => 'read'], ['name' => 'Read', 'is_active' => true]);
        $writeCap = DelegationCapability::firstOrCreate(['slug' => 'write'], ['name' => 'Write', 'is_active' => true]);
        $executeCap = DelegationCapability::firstOrCreate(['slug' => 'execute'], ['name' => 'Execute', 'is_active' => true]);
        $delegatee->capabilities()->syncWithoutDetaching([$readCap->id, $writeCap->id, $executeCap->id]);

        $profile = $this->service->create($user, [
            'name' => 'test-agent',
            'role_slug' => 'developer',
            'role_description' => 'Development tasks',
            'delegatee_profile_id' => $delegatee->id,
            'capability_bindings' => ['read', 'write'],
            'authority_overrides' => [],
        ]);

        $this->assertInstanceOf(OrgAgentProfile::class, $profile);
        $this->assertEquals($user->id, $profile->user_id);
        $this->assertEquals('test-agent', $profile->name);
        $this->assertEquals(['read', 'write'], $profile->capability_bindings);
    }

    public function test_rejects_delegatee_not_owned_by_user(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $delegatee = DelegateeProfile::factory()->for($otherUser)->create();

        $this->expectException(InvalidDelegateeBindingException::class);

        $this->service->create($user, [
            'name' => 'test-agent',
            'role_slug' => 'developer',
            'role_description' => 'Test',
            'delegatee_profile_id' => $delegatee->id,
            'capability_bindings' => [],
            'authority_overrides' => [],
        ]);
    }

    public function test_rejects_authority_widening(): void
    {
        $user = User::factory()->create();
        $delegatee = DelegateeProfile::factory()->for($user)->create();

        // Only attach 'read' capability to delegatee
        $readCap = DelegationCapability::firstOrCreate(['slug' => 'read'], ['name' => 'Read', 'is_active' => true]);
        $delegatee->capabilities()->syncWithoutDetaching([$readCap->id]);

        $this->expectException(AuthorityWideningException::class);

        // Attempt to request 'write' and 'admin' which delegatee doesn't have
        $this->service->create($user, [
            'name' => 'test-agent',
            'role_slug' => 'developer',
            'role_description' => 'Test',
            'delegatee_profile_id' => $delegatee->id,
            'capability_bindings' => ['read', 'write', 'admin'],
            'authority_overrides' => [],
        ]);
    }

    public function test_archive_sets_archived_at(): void
    {
        $profile = OrgAgentProfile::factory()->create();

        $this->service->archive($profile);

        $this->assertNotNull($profile->fresh()->archived_at);
    }

    public function test_restore_clears_archived_at(): void
    {
        $profile = OrgAgentProfile::factory()->create([
            'archived_at' => now(),
        ]);

        $this->service->restore($profile);

        $this->assertNull($profile->fresh()->archived_at);
    }
}
