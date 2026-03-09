<?php

declare(strict_types=1);

namespace Tests\Feature\Delegation;

use App\Models\DelegateeProfile;
use App\Models\DelegationGraph;
use App\Models\DelegationTask;
use App\Models\EscalationIncident;
use App\Models\TrustScoreHistory;
use App\Models\User;
use App\Services\Delegation\PermissionAttenuationService;
use App\Support\Delegation\ContractEnforcer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DelegationArchitectureTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private DelegationGraph $graph;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->graph = DelegationGraph::factory()->create(['user_id' => $this->user->id]);
    }

    // ─── Capability Profile (AGE-2330) ───

    public function test_capability_profile_is_queryable_on_delegatee_profile(): void
    {
        $profile = DelegateeProfile::factory()->create([
            'user_id' => $this->user->id,
            'capability_profile' => [
                'capabilities' => ['code_execution', 'file_write'],
                'max_complexity' => 'high',
                'languages' => ['php', 'python'],
            ],
        ]);

        $found = DelegateeProfile::where('id', $profile->id)->first();
        $this->assertIsArray($found->capability_profile);
        $this->assertEquals(['code_execution', 'file_write'], $found->capability_profile['capabilities']);
        $this->assertEquals('high', $found->capability_profile['max_complexity']);
    }

    public function test_capability_profile_defaults_to_null(): void
    {
        $profile = DelegateeProfile::factory()->create(['user_id' => $this->user->id]);
        $this->assertNull($profile->fresh()->capability_profile);
    }

    public function test_capability_profile_is_fillable_and_cast_to_array(): void
    {
        $profile = DelegateeProfile::factory()->create([
            'user_id' => $this->user->id,
            'capability_profile' => ['capabilities' => ['test']],
        ]);

        $profile->update(['capability_profile' => ['capabilities' => ['updated']]]);
        $this->assertEquals(['capabilities' => ['updated']], $profile->fresh()->capability_profile);
    }

    // ─── Trust Score History (AGE-2334) ───

    public function test_trust_score_history_model_can_be_created(): void
    {
        $profile = DelegateeProfile::factory()->create(['user_id' => $this->user->id]);

        $history = TrustScoreHistory::create([
            'delegatee_profile_id' => $profile->id,
            'score' => 0.85,
            'components_json' => ['star_completion' => 0.9, 'task_correct' => 0.8],
            'calculated_at' => now(),
            'reason' => 'manual_recalculation',
        ]);

        $this->assertDatabaseHas('trust_score_histories', [
            'id' => $history->id,
            'delegatee_profile_id' => $profile->id,
            'reason' => 'manual_recalculation',
        ]);
    }

    public function test_delegatee_profile_has_trust_score_histories_relationship(): void
    {
        $profile = DelegateeProfile::factory()->create(['user_id' => $this->user->id]);

        TrustScoreHistory::factory()->count(3)->create([
            'delegatee_profile_id' => $profile->id,
        ]);

        $this->assertCount(3, $profile->trustScoreHistories);
    }

    public function test_trust_score_history_belongs_to_delegatee_profile(): void
    {
        $profile = DelegateeProfile::factory()->create(['user_id' => $this->user->id]);
        $history = TrustScoreHistory::factory()->create(['delegatee_profile_id' => $profile->id]);

        $this->assertEquals($profile->id, $history->delegateeProfile->id);
    }

    public function test_trust_score_history_cascades_on_profile_delete(): void
    {
        $profile = DelegateeProfile::factory()->create(['user_id' => $this->user->id]);
        TrustScoreHistory::factory()->count(2)->create(['delegatee_profile_id' => $profile->id]);

        $profile->forceDelete();
        $this->assertDatabaseCount('trust_score_histories', 0);
    }

    // ─── ContractEnforcer Completion (AGE-2331) ───

    public function test_deadline_enforcement_passes_when_not_exceeded(): void
    {
        $enforcer = new ContractEnforcer;
        $contract = [
            'time_constraints' => ['deadline_ts' => time() + 3600],
            'authority_scope' => [],
        ];
        $profile = DelegateeProfile::factory()->create(['user_id' => $this->user->id]);

        $result = $enforcer->enforce($contract, $profile);
        $this->assertFalse($result->wasNarrowed());
    }

    public function test_deadline_enforcement_triggers_escalation_when_exceeded(): void
    {
        $enforcer = new ContractEnforcer;
        $contract = [
            'time_constraints' => ['deadline_ts' => time() - 3600],
            'criticality' => 'critical',
            'authority_scope' => [],
        ];
        $profile = DelegateeProfile::factory()->create(['user_id' => $this->user->id]);

        $result = $enforcer->enforce($contract, $profile);
        $this->assertTrue($result->wasNarrowed());
        $this->assertNotEmpty(array_filter($result->warnings(), fn ($w) => str_contains($w, 'deadline_exceeded')));

        // Verify escalation incident was created
        $this->assertDatabaseHas((new EscalationIncident)->getTable(), [
            'workflow_key' => 'delegation.deadline',
            'trigger_type' => 'deadline_critical',
            'reason_code' => 'DEADLINE_EXCEEDED',
        ]);
    }

    public function test_deadline_escalation_uses_criticality_levels(): void
    {
        $enforcer = new ContractEnforcer;

        // High criticality
        $contract = [
            'time_constraints' => ['deadline_ts' => time() - 100],
            'criticality' => 'high',
            'authority_scope' => [],
        ];
        $profile = DelegateeProfile::factory()->create(['user_id' => $this->user->id]);
        $enforcer->enforce($contract, $profile);

        $this->assertDatabaseHas((new EscalationIncident)->getTable(), [
            'trigger_type' => 'deadline_high',
            'status' => 'investigating',
        ]);
    }

    public function test_resource_quota_enforcement_caps_to_profile_limits(): void
    {
        $enforcer = new ContractEnforcer;
        $contract = [
            'authority_scope' => [],
            'resource_quotas' => [
                'max_tokens' => 100000,
                'max_api_calls' => 500,
            ],
        ];
        $profile = DelegateeProfile::factory()->withConfig([
            'resource_quotas' => [
                'max_tokens' => 50000,
                'max_api_calls' => 1000,
            ],
        ])->create(['user_id' => $this->user->id]);

        $result = $enforcer->enforce($contract, $profile);
        $this->assertTrue($result->wasNarrowed());

        $narrowed = $result->narrowedConfig();
        $this->assertEquals(50000, $narrowed['resource_quotas']['max_tokens']);
        $this->assertEquals(500, $narrowed['resource_quotas']['max_api_calls']); // Not capped
    }

    public function test_capability_validation_warns_on_missing_capabilities(): void
    {
        $enforcer = new ContractEnforcer;
        $contract = [
            'authority_scope' => [],
            'required_capabilities' => ['code_execution', 'file_write', 'network_access'],
        ];
        $profile = DelegateeProfile::factory()->create([
            'user_id' => $this->user->id,
            'capability_profile' => [
                'capabilities' => ['code_execution', 'file_write'],
            ],
        ]);

        $result = $enforcer->enforce($contract, $profile);
        $this->assertTrue($result->wasNarrowed());
        $this->assertNotEmpty(array_filter($result->warnings(), fn ($w) => str_contains($w, 'network_access')));
    }

    public function test_capability_validation_passes_when_all_present(): void
    {
        $enforcer = new ContractEnforcer;
        $contract = [
            'authority_scope' => [],
            'required_capabilities' => ['code_execution'],
        ];
        $profile = DelegateeProfile::factory()->create([
            'user_id' => $this->user->id,
            'capability_profile' => ['capabilities' => ['code_execution', 'file_write']],
        ]);

        $result = $enforcer->enforce($contract, $profile);
        $this->assertFalse($result->wasNarrowed());
    }

    // ─── PermissionAttenuationService (AGE-2332) ───

    public function test_permission_attenuation_narrows_paths(): void
    {
        $service = new PermissionAttenuationService;
        $parent = [
            'authority_scope' => [
                'allowed_paths' => ['/src', '/tests'],
                'max_runtime_seconds' => 3600,
            ],
        ];
        $child = [
            'authority_scope' => [
                'allowed_paths' => ['/src', '/tests', '/config'],
                'max_runtime_seconds' => 7200,
            ],
        ];

        $attenuated = $service->attenuate($parent, $child);
        $this->assertEquals(['/src', '/tests'], $attenuated['authority_scope']['allowed_paths']);
        $this->assertEquals(3600, $attenuated['authority_scope']['max_runtime_seconds']);
    }

    public function test_permission_attenuation_inherits_parent_restrictions(): void
    {
        $service = new PermissionAttenuationService;
        $parent = [
            'authority_scope' => [
                'allowed_paths' => ['/src'],
                'env_whitelist' => ['HOME', 'PATH'],
            ],
        ];
        $child = [
            'authority_scope' => [],
        ];

        $attenuated = $service->attenuate($parent, $child);
        $this->assertEquals(['/src'], $attenuated['authority_scope']['allowed_paths']);
        $this->assertEquals(['HOME', 'PATH'], $attenuated['authority_scope']['env_whitelist']);
    }

    public function test_permission_validation_detects_violations(): void
    {
        $service = new PermissionAttenuationService;
        $parent = [
            'authority_scope' => [
                'allowed_paths' => ['/src'],
                'max_runtime_seconds' => 1800,
            ],
        ];
        $child = [
            'authority_scope' => [
                'allowed_paths' => ['/src', '/etc'],
                'max_runtime_seconds' => 3600,
            ],
        ];

        $violations = $service->validate($parent, $child);
        $this->assertCount(2, $violations);
        $this->assertNotEmpty(array_filter($violations, fn ($v) => str_contains($v, '/etc')));
        $this->assertNotEmpty(array_filter($violations, fn ($v) => str_contains($v, 'max_runtime_seconds')));
    }

    public function test_permission_validation_passes_for_subset(): void
    {
        $service = new PermissionAttenuationService;
        $parent = [
            'authority_scope' => [
                'allowed_paths' => ['/src', '/tests'],
                'max_runtime_seconds' => 3600,
            ],
        ];
        $child = [
            'authority_scope' => [
                'allowed_paths' => ['/src'],
                'max_runtime_seconds' => 1800,
            ],
        ];

        $violations = $service->validate($parent, $child);
        $this->assertEmpty($violations);
    }

    public function test_permission_attenuation_caps_resource_quotas(): void
    {
        $service = new PermissionAttenuationService;
        $parent = [
            'authority_scope' => [],
            'resource_quotas' => ['max_tokens' => 50000],
        ];
        $child = [
            'authority_scope' => [],
            'resource_quotas' => ['max_tokens' => 100000],
        ];

        $violations = $service->validate($parent, $child);
        $this->assertNotEmpty(array_filter($violations, fn ($v) => str_contains($v, 'max_tokens')));
    }

    // ─── Sub-Delegation Framework (AGE-2333) ───

    public function test_delegation_task_has_parent_and_child_relationships(): void
    {
        $parent = DelegationTask::factory()->create([
            'delegation_graph_id' => $this->graph->id,
        ]);
        $child = DelegationTask::factory()->create([
            'delegation_graph_id' => $this->graph->id,
            'parent_delegation_task_id' => $parent->id,
        ]);

        $this->assertEquals($parent->id, $child->parentTask->id);
        $this->assertCount(1, $parent->childTasks);
        $this->assertEquals($child->id, $parent->childTasks->first()->id);
    }

    public function test_delegation_depth_calculation_for_2_deep_chain(): void
    {
        $root = DelegationTask::factory()->create([
            'delegation_graph_id' => $this->graph->id,
        ]);
        $level1 = DelegationTask::factory()->create([
            'delegation_graph_id' => $this->graph->id,
            'parent_delegation_task_id' => $root->id,
        ]);
        $level2 = DelegationTask::factory()->create([
            'delegation_graph_id' => $this->graph->id,
            'parent_delegation_task_id' => $level1->id,
        ]);

        $this->assertEquals(0, $root->getDelegationDepth());
        $this->assertEquals(1, $level1->getDelegationDepth());
        $this->assertEquals(2, $level2->getDelegationDepth());
    }

    public function test_delegation_depth_calculation_for_3_deep_chain(): void
    {
        $root = DelegationTask::factory()->create([
            'delegation_graph_id' => $this->graph->id,
        ]);
        $level1 = DelegationTask::factory()->create([
            'delegation_graph_id' => $this->graph->id,
            'parent_delegation_task_id' => $root->id,
        ]);
        $level2 = DelegationTask::factory()->create([
            'delegation_graph_id' => $this->graph->id,
            'parent_delegation_task_id' => $level1->id,
        ]);
        $level3 = DelegationTask::factory()->create([
            'delegation_graph_id' => $this->graph->id,
            'parent_delegation_task_id' => $level2->id,
        ]);

        $this->assertEquals(3, $level3->getDelegationDepth());
        $this->assertEquals($root->id, $level3->getRootTask()->id);
    }

    public function test_get_root_task_returns_self_for_root(): void
    {
        $root = DelegationTask::factory()->create([
            'delegation_graph_id' => $this->graph->id,
        ]);

        $this->assertEquals($root->id, $root->getRootTask()->id);
    }

    public function test_get_root_task_traverses_to_root(): void
    {
        $root = DelegationTask::factory()->create([
            'delegation_graph_id' => $this->graph->id,
        ]);
        $child = DelegationTask::factory()->create([
            'delegation_graph_id' => $this->graph->id,
            'parent_delegation_task_id' => $root->id,
        ]);
        $grandchild = DelegationTask::factory()->create([
            'delegation_graph_id' => $this->graph->id,
            'parent_delegation_task_id' => $child->id,
        ]);

        $this->assertEquals($root->id, $grandchild->getRootTask()->id);
    }

    public function test_sub_delegation_factory_with_parent(): void
    {
        $parent = DelegationTask::factory()->create([
            'delegation_graph_id' => $this->graph->id,
        ]);
        $child = DelegationTask::factory()->withParent($parent)->create([
            'delegation_graph_id' => $this->graph->id,
        ]);

        $this->assertEquals($parent->id, $child->parent_delegation_task_id);
    }

    public function test_parent_delegation_task_id_is_nullable(): void
    {
        $task = DelegationTask::factory()->create([
            'delegation_graph_id' => $this->graph->id,
        ]);

        $this->assertNull($task->parent_delegation_task_id);
    }

    public function test_parent_deletion_nullifies_child_reference(): void
    {
        $parent = DelegationTask::factory()->create([
            'delegation_graph_id' => $this->graph->id,
        ]);
        $child = DelegationTask::factory()->create([
            'delegation_graph_id' => $this->graph->id,
            'parent_delegation_task_id' => $parent->id,
        ]);

        $parent->delete();
        $this->assertNull($child->fresh()->parent_delegation_task_id);
    }
}
