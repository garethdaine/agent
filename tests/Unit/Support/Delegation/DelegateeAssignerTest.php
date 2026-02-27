<?php

namespace Tests\Unit\Support\Delegation;

use App\Models\DelegateeMetric;
use App\Models\DelegateeProfile;
use App\Models\DelegationAttempt;
use App\Models\DelegationCapability;
use App\Models\DelegationGraph;
use App\Models\DelegationTask;
use App\Models\User;
use App\Support\Delegation\DelegateeAssigner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DelegateeAssignerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_assigns_profile_with_matching_capability(): void
    {
        $capability = DelegationCapability::factory()->create(['slug' => 'code_execution']);
        $profile = DelegateeProfile::factory()->create([
            'user_id' => $this->user->id,
            'is_active' => true,
        ]);
        $profile->capabilities()->attach($capability);
        DelegateeMetric::factory()->create([
            'delegatee_profile_id' => $profile->id,
            'window_24h_json' => ['success_rate' => 0.9],
        ]);

        $graph = DelegationGraph::factory()->create(['user_id' => $this->user->id]);
        $task = DelegationTask::factory()->create([
            'delegation_graph_id' => $graph->id,
            'contract_json' => ['required_capability' => 'code_execution'],
        ]);

        $result = (new DelegateeAssigner)->assign($task);

        $this->assertNotNull($result);
        $this->assertEquals($profile->id, $result->profile->id);
        $this->assertArrayHasKey('matched_capability', $result->reasoning);
        $this->assertEquals('code_execution', $result->reasoning['matched_capability']);
    }

    public function test_returns_null_when_no_matching_capability(): void
    {
        $capability = DelegationCapability::factory()->create(['slug' => 'code_execution']);
        $profile = DelegateeProfile::factory()->create([
            'user_id' => $this->user->id,
            'is_active' => true,
        ]);
        $profile->capabilities()->attach($capability);

        $graph = DelegationGraph::factory()->create(['user_id' => $this->user->id]);
        $task = DelegationTask::factory()->create([
            'delegation_graph_id' => $graph->id,
            'contract_json' => ['required_capability' => 'deployment'],
        ]);

        $result = (new DelegateeAssigner)->assign($task);

        $this->assertNull($result);
    }

    public function test_returns_null_when_no_required_capability_in_contract(): void
    {
        $graph = DelegationGraph::factory()->create(['user_id' => $this->user->id]);
        $task = DelegationTask::factory()->create([
            'delegation_graph_id' => $graph->id,
            'contract_json' => ['prompt' => 'Do something'],
        ]);

        $result = (new DelegateeAssigner)->assign($task);

        $this->assertNull($result);
    }

    public function test_ranks_by_success_rate_descending(): void
    {
        $capability = DelegationCapability::factory()->create(['slug' => 'testing']);

        // Create profile with lower success rate
        $profileLow = DelegateeProfile::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Low Success',
            'is_active' => true,
        ]);
        $profileLow->capabilities()->attach($capability);
        DelegateeMetric::factory()->create([
            'delegatee_profile_id' => $profileLow->id,
            'window_24h_json' => ['success_rate' => 0.5],
        ]);

        // Create profile with higher success rate
        $profileHigh = DelegateeProfile::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'High Success',
            'is_active' => true,
        ]);
        $profileHigh->capabilities()->attach($capability);
        DelegateeMetric::factory()->create([
            'delegatee_profile_id' => $profileHigh->id,
            'window_24h_json' => ['success_rate' => 0.95],
        ]);

        $graph = DelegationGraph::factory()->create(['user_id' => $this->user->id]);
        $task = DelegationTask::factory()->create([
            'delegation_graph_id' => $graph->id,
            'contract_json' => ['required_capability' => 'testing'],
        ]);

        $result = (new DelegateeAssigner)->assign($task);

        $this->assertNotNull($result);
        $this->assertEquals($profileHigh->id, $result->profile->id);
        $this->assertEquals(0.95, $result->reasoning['success_rate_24h']);
    }

    public function test_tiebreaks_by_lowest_current_load(): void
    {
        $capability = DelegationCapability::factory()->create(['slug' => 'review']);

        // Create profile with same success rate but higher load
        $profileBusy = DelegateeProfile::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Busy Profile',
            'is_active' => true,
        ]);
        $profileBusy->capabilities()->attach($capability);
        DelegateeMetric::factory()->create([
            'delegatee_profile_id' => $profileBusy->id,
            'window_24h_json' => ['success_rate' => 0.8],
        ]);

        // Create some running attempts for busy profile
        $otherGraph = DelegationGraph::factory()->create(['user_id' => $this->user->id]);
        $otherTask = DelegationTask::factory()->running()->create([
            'delegation_graph_id' => $otherGraph->id,
        ]);
        DelegationAttempt::factory()->running()->count(3)->create([
            'delegation_task_id' => $otherTask->id,
            'delegatee_profile_id' => $profileBusy->id,
        ]);

        // Create profile with same success rate but no load
        $profileIdle = DelegateeProfile::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Idle Profile',
            'is_active' => true,
        ]);
        $profileIdle->capabilities()->attach($capability);
        DelegateeMetric::factory()->create([
            'delegatee_profile_id' => $profileIdle->id,
            'window_24h_json' => ['success_rate' => 0.8],
        ]);

        $graph = DelegationGraph::factory()->create(['user_id' => $this->user->id]);
        $task = DelegationTask::factory()->create([
            'delegation_graph_id' => $graph->id,
            'contract_json' => ['required_capability' => 'review'],
        ]);

        $result = (new DelegateeAssigner)->assign($task);

        $this->assertNotNull($result);
        $this->assertEquals($profileIdle->id, $result->profile->id);
        $this->assertEquals(0, $result->reasoning['current_load']);
    }

    public function test_excludes_inactive_profiles(): void
    {
        $capability = DelegationCapability::factory()->create(['slug' => 'documentation']);

        $inactiveProfile = DelegateeProfile::factory()->create([
            'user_id' => $this->user->id,
            'is_active' => false,
        ]);
        $inactiveProfile->capabilities()->attach($capability);

        $graph = DelegationGraph::factory()->create(['user_id' => $this->user->id]);
        $task = DelegationTask::factory()->create([
            'delegation_graph_id' => $graph->id,
            'contract_json' => ['required_capability' => 'documentation'],
        ]);

        $result = (new DelegateeAssigner)->assign($task);

        $this->assertNull($result);
    }

    public function test_excludes_soft_deleted_profiles(): void
    {
        $capability = DelegationCapability::factory()->create(['slug' => 'monitoring']);

        $deletedProfile = DelegateeProfile::factory()->create([
            'user_id' => $this->user->id,
            'is_active' => true,
            'deleted_at' => now(),
        ]);
        $deletedProfile->capabilities()->attach($capability);

        $graph = DelegationGraph::factory()->create(['user_id' => $this->user->id]);
        $task = DelegationTask::factory()->create([
            'delegation_graph_id' => $graph->id,
            'contract_json' => ['required_capability' => 'monitoring'],
        ]);

        $result = (new DelegateeAssigner)->assign($task);

        $this->assertNull($result);
    }

    public function test_only_returns_profiles_owned_by_graph_user(): void
    {
        $capability = DelegationCapability::factory()->create(['slug' => 'code_execution']);

        // Profile owned by different user
        $otherUser = User::factory()->create();
        $otherProfile = DelegateeProfile::factory()->create([
            'user_id' => $otherUser->id,
            'is_active' => true,
        ]);
        $otherProfile->capabilities()->attach($capability);
        DelegateeMetric::factory()->create([
            'delegatee_profile_id' => $otherProfile->id,
            'window_24h_json' => ['success_rate' => 1.0],
        ]);

        $graph = DelegationGraph::factory()->create(['user_id' => $this->user->id]);
        $task = DelegationTask::factory()->create([
            'delegation_graph_id' => $graph->id,
            'contract_json' => ['required_capability' => 'code_execution'],
        ]);

        $result = (new DelegateeAssigner)->assign($task);

        $this->assertNull($result);
    }

    public function test_handles_profile_without_metrics(): void
    {
        $capability = DelegationCapability::factory()->create(['slug' => 'code_execution']);

        $profile = DelegateeProfile::factory()->create([
            'user_id' => $this->user->id,
            'is_active' => true,
        ]);
        $profile->capabilities()->attach($capability);
        // No metric record created

        $graph = DelegationGraph::factory()->create(['user_id' => $this->user->id]);
        $task = DelegationTask::factory()->create([
            'delegation_graph_id' => $graph->id,
            'contract_json' => ['required_capability' => 'code_execution'],
        ]);

        $result = (new DelegateeAssigner)->assign($task);

        $this->assertNotNull($result);
        $this->assertEquals($profile->id, $result->profile->id);
        $this->assertEquals(0, $result->reasoning['success_rate_24h']);
    }

    public function test_handles_metric_without_success_rate(): void
    {
        $capability = DelegationCapability::factory()->create(['slug' => 'code_execution']);

        $profile = DelegateeProfile::factory()->create([
            'user_id' => $this->user->id,
            'is_active' => true,
        ]);
        $profile->capabilities()->attach($capability);
        DelegateeMetric::factory()->create([
            'delegatee_profile_id' => $profile->id,
            'window_24h_json' => ['total_attempts' => 0],
        ]);

        $graph = DelegationGraph::factory()->create(['user_id' => $this->user->id]);
        $task = DelegationTask::factory()->create([
            'delegation_graph_id' => $graph->id,
            'contract_json' => ['required_capability' => 'code_execution'],
        ]);

        $result = (new DelegateeAssigner)->assign($task);

        $this->assertNotNull($result);
        $this->assertEquals(0, $result->reasoning['success_rate_24h']);
    }
}
