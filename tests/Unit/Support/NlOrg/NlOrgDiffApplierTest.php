<?php

namespace Tests\Unit\Support\NlOrg;

use App\Models\OrgAgentProfile;
use App\Models\OrgCouncilTemplate;
use App\Models\OrgReportingEdge;
use App\Models\OrgRitualTemplate;
use App\Models\User;
use App\Support\NlOrg\NlOrgDiffApplier;
use App\Support\NlOrg\NlOrgParseResult;
use App\Support\Org\OrgAgentProfileService;
use App\Support\Org\OrgCouncilService;
use App\Support\Org\OrgReportingEdgeService;
use App\Support\Org\OrgRitualTemplateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Mockery;
use Mockery\MockInterface;
use RuntimeException;
use Tests\TestCase;

class NlOrgDiffApplierTest extends TestCase
{
    use RefreshDatabase;

    private MockInterface $profileService;

    private MockInterface $edgeService;

    private MockInterface $ritualService;

    private MockInterface $councilService;

    private NlOrgDiffApplier $applier;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->profileService = Mockery::mock(OrgAgentProfileService::class);
        $this->edgeService = Mockery::mock(OrgReportingEdgeService::class);
        $this->ritualService = Mockery::mock(OrgRitualTemplateService::class);
        $this->councilService = Mockery::mock(OrgCouncilService::class);

        $this->applier = new NlOrgDiffApplier(
            $this->profileService,
            $this->edgeService,
            $this->ritualService,
            $this->councilService,
        );

        $this->user = User::factory()->create();
    }

    private function makeResult(array $changeset): NlOrgParseResult
    {
        return new NlOrgParseResult(
            confidence: 0.9,
            changeset: $changeset,
            annotations: [],
            resolvedReferences: [],
        );
    }

    private function createAgentProfile(array $overrides = []): OrgAgentProfile
    {
        return OrgAgentProfile::factory()->forUser($this->user)->create($overrides);
    }

    public function test_add_agent_delegates_to_profile_service_create(): void
    {
        $createdProfile = $this->createAgentProfile(['name' => 'Test Agent', 'role_slug' => 'tester']);

        $agentData = [
            'name' => 'Test Agent',
            'role_slug' => 'tester',
            'delegatee_profile_id' => $createdProfile->delegatee_profile_id,
        ];

        $this->profileService
            ->shouldReceive('create')
            ->once()
            ->with($this->user, $agentData)
            ->andReturn($createdProfile);

        $result = $this->makeResult([
            [
                'entity_type' => NlOrgParseResult::TYPE_AGENT_PROFILE,
                'operation' => NlOrgParseResult::OP_ADD,
                'payload' => $agentData,
                'temp_id' => null,
            ],
        ]);

        $this->applier->apply($this->user, $result);
    }

    public function test_update_agent_delegates_to_profile_service_update(): void
    {
        $profile = $this->createAgentProfile();
        $updateData = ['name' => 'Updated Agent'];

        $this->profileService
            ->shouldReceive('update')
            ->once()
            ->with(
                Mockery::on(fn (OrgAgentProfile $p) => $p->id === $profile->id),
                $updateData,
            )
            ->andReturn($profile);

        $result = $this->makeResult([
            [
                'entity_type' => NlOrgParseResult::TYPE_AGENT_PROFILE,
                'operation' => NlOrgParseResult::OP_UPDATE,
                'payload' => array_merge(['id' => $profile->id], $updateData),
                'temp_id' => null,
            ],
        ]);

        $this->applier->apply($this->user, $result);
    }

    public function test_remove_agent_delegates_to_profile_service_archive(): void
    {
        $profile = $this->createAgentProfile();

        $this->profileService
            ->shouldReceive('archive')
            ->once()
            ->with(Mockery::on(fn (OrgAgentProfile $p) => $p->id === $profile->id));

        $result = $this->makeResult([
            [
                'entity_type' => NlOrgParseResult::TYPE_AGENT_PROFILE,
                'operation' => NlOrgParseResult::OP_REMOVE,
                'payload' => ['id' => $profile->id],
                'temp_id' => null,
            ],
        ]);

        $this->applier->apply($this->user, $result);
    }

    public function test_add_edge_delegates_to_edge_service_set_manager(): void
    {
        $subordinate = $this->createAgentProfile();
        $manager = $this->createAgentProfile();

        $edge = OrgReportingEdge::factory()->create([
            'subordinate_agent_id' => $subordinate->id,
            'manager_agent_id' => $manager->id,
            'user_id' => $this->user->id,
        ]);

        $this->edgeService
            ->shouldReceive('setManager')
            ->once()
            ->with(
                Mockery::on(fn (OrgAgentProfile $p) => $p->id === $subordinate->id),
                Mockery::on(fn (OrgAgentProfile $p) => $p->id === $manager->id),
            )
            ->andReturn($edge);

        $result = $this->makeResult([
            [
                'entity_type' => NlOrgParseResult::TYPE_REPORTING_EDGE,
                'operation' => NlOrgParseResult::OP_ADD,
                'payload' => [
                    'subordinate_agent_id' => $subordinate->id,
                    'manager_agent_id' => $manager->id,
                ],
                'temp_id' => null,
            ],
        ]);

        $this->applier->apply($this->user, $result);
    }

    public function test_remove_edge_delegates_to_edge_service_remove_manager(): void
    {
        $subordinate = $this->createAgentProfile();

        $this->edgeService
            ->shouldReceive('removeManager')
            ->once()
            ->with(Mockery::on(fn (OrgAgentProfile $p) => $p->id === $subordinate->id));

        $result = $this->makeResult([
            [
                'entity_type' => NlOrgParseResult::TYPE_REPORTING_EDGE,
                'operation' => NlOrgParseResult::OP_REMOVE,
                'payload' => ['subordinate_agent_id' => $subordinate->id],
                'temp_id' => null,
            ],
        ]);

        $this->applier->apply($this->user, $result);
    }

    public function test_add_ritual_delegates_to_ritual_service_create(): void
    {
        $ritualData = [
            'name' => 'Daily Standup',
            'cron_expression' => '0 9 * * 1-5',
            'phase_graph' => ['phases' => []],
        ];

        $createdRitual = OrgRitualTemplate::factory()->create([
            'user_id' => $this->user->id,
            ...$ritualData,
        ]);

        $this->ritualService
            ->shouldReceive('create')
            ->once()
            ->with($this->user, $ritualData)
            ->andReturn($createdRitual);

        $result = $this->makeResult([
            [
                'entity_type' => NlOrgParseResult::TYPE_RITUAL_TEMPLATE,
                'operation' => NlOrgParseResult::OP_ADD,
                'payload' => $ritualData,
                'temp_id' => null,
            ],
        ]);

        $this->applier->apply($this->user, $result);
    }

    public function test_add_council_delegates_to_council_service_create(): void
    {
        $agentProfile = $this->createAgentProfile();
        $councilData = [
            'name' => 'Review Board',
            'member_list' => [['agent_id' => $agentProfile->id]],
            'synthesis_mode' => 'majority',
        ];

        $createdCouncil = OrgCouncilTemplate::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Review Board',
            'synthesis_mode' => 'majority',
        ]);

        $this->councilService
            ->shouldReceive('create')
            ->once()
            ->with($this->user, $councilData)
            ->andReturn($createdCouncil);

        $result = $this->makeResult([
            [
                'entity_type' => NlOrgParseResult::TYPE_COUNCIL_TEMPLATE,
                'operation' => NlOrgParseResult::OP_ADD,
                'payload' => $councilData,
                'temp_id' => null,
            ],
        ]);

        $this->applier->apply($this->user, $result);
    }

    public function test_temp_id_resolution_maps_temp_ids_to_real_uuids(): void
    {
        $createdProfile = $this->createAgentProfile(['name' => 'New Agent', 'role_slug' => 'dev']);
        $realId = $createdProfile->id;

        $agentData = [
            'name' => 'New Agent',
            'role_slug' => 'dev',
            'delegatee_profile_id' => $createdProfile->delegatee_profile_id,
        ];

        $existingManager = $this->createAgentProfile();

        $this->profileService
            ->shouldReceive('create')
            ->once()
            ->with($this->user, $agentData)
            ->andReturn($createdProfile);

        $edge = OrgReportingEdge::factory()->create([
            'subordinate_agent_id' => $createdProfile->id,
            'manager_agent_id' => $existingManager->id,
            'user_id' => $this->user->id,
        ]);

        $this->edgeService
            ->shouldReceive('setManager')
            ->once()
            ->with(
                Mockery::on(fn (OrgAgentProfile $p) => $p->id === $realId),
                Mockery::on(fn (OrgAgentProfile $p) => $p->id === $existingManager->id),
            )
            ->andReturn($edge);

        $result = $this->makeResult([
            [
                'entity_type' => NlOrgParseResult::TYPE_AGENT_PROFILE,
                'operation' => NlOrgParseResult::OP_ADD,
                'payload' => $agentData,
                'temp_id' => 'new_1',
            ],
            [
                'entity_type' => NlOrgParseResult::TYPE_REPORTING_EDGE,
                'operation' => NlOrgParseResult::OP_ADD,
                'payload' => [
                    'subordinate_agent_id' => 'new_1',
                    'manager_agent_id' => $existingManager->id,
                ],
                'temp_id' => null,
            ],
        ]);

        $this->applier->apply($this->user, $result);
    }

    public function test_transaction_rollback_on_service_exception(): void
    {
        $createdProfile = $this->createAgentProfile(['name' => 'Will Be Rolled Back', 'role_slug' => 'temp']);

        $agentData = [
            'name' => 'Will Be Rolled Back',
            'role_slug' => 'temp',
            'delegatee_profile_id' => $createdProfile->delegatee_profile_id,
        ];

        $this->profileService
            ->shouldReceive('create')
            ->once()
            ->andReturn($createdProfile);

        $this->ritualService
            ->shouldReceive('create')
            ->once()
            ->andThrow(new RuntimeException('Ritual creation failed'));

        $result = $this->makeResult([
            [
                'entity_type' => NlOrgParseResult::TYPE_AGENT_PROFILE,
                'operation' => NlOrgParseResult::OP_ADD,
                'payload' => $agentData,
                'temp_id' => null,
            ],
            [
                'entity_type' => NlOrgParseResult::TYPE_RITUAL_TEMPLATE,
                'operation' => NlOrgParseResult::OP_ADD,
                'payload' => ['name' => 'Failing Ritual', 'phase_graph' => []],
                'temp_id' => null,
            ],
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Ritual creation failed');

        $this->applier->apply($this->user, $result);
    }

    public function test_dependency_ordering_removals_processed_before_adds(): void
    {
        $existingProfile = $this->createAgentProfile();
        $newProfile = $this->createAgentProfile(['name' => 'New Agent', 'role_slug' => 'dev']);
        $newAgentData = [
            'name' => 'New Agent',
            'role_slug' => 'dev',
            'delegatee_profile_id' => $newProfile->delegatee_profile_id,
        ];

        $callOrder = [];

        $this->profileService
            ->shouldReceive('archive')
            ->once()
            ->with(Mockery::on(fn (OrgAgentProfile $p) => $p->id === $existingProfile->id))
            ->andReturnUsing(function () use (&$callOrder) {
                $callOrder[] = 'remove';
            });

        $this->profileService
            ->shouldReceive('create')
            ->once()
            ->with($this->user, $newAgentData)
            ->andReturnUsing(function () use (&$callOrder, $newProfile) {
                $callOrder[] = 'add';

                return $newProfile;
            });

        // Changeset has add BEFORE remove — applier should reorder
        $result = $this->makeResult([
            [
                'entity_type' => NlOrgParseResult::TYPE_AGENT_PROFILE,
                'operation' => NlOrgParseResult::OP_ADD,
                'payload' => $newAgentData,
                'temp_id' => null,
            ],
            [
                'entity_type' => NlOrgParseResult::TYPE_AGENT_PROFILE,
                'operation' => NlOrgParseResult::OP_REMOVE,
                'payload' => ['id' => $existingProfile->id],
                'temp_id' => null,
            ],
        ]);

        $this->applier->apply($this->user, $result);

        $this->assertSame(['remove', 'add'], $callOrder);
    }

    public function test_unresolved_temp_id_throws_descriptive_exception(): void
    {
        $managerId = Str::uuid()->toString();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unresolved temp_id');

        $result = $this->makeResult([
            [
                'entity_type' => NlOrgParseResult::TYPE_REPORTING_EDGE,
                'operation' => NlOrgParseResult::OP_ADD,
                'payload' => [
                    'subordinate_agent_id' => 'nonexistent_temp',
                    'manager_agent_id' => $managerId,
                ],
                'temp_id' => null,
            ],
        ]);

        $this->applier->apply($this->user, $result);
    }
}
