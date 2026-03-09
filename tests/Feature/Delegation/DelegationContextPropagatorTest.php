<?php

declare(strict_types=1);

namespace Tests\Feature\Delegation;

use App\Models\DelegateeProfile;
use App\Models\DelegationContext;
use App\Models\DelegationGraph;
use App\Models\DelegationTask;
use App\Models\User;
use App\Services\Delegation\DelegationContextPropagator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DelegationContextPropagatorTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private DelegationGraph $graph;

    private DelegationContextPropagator $propagator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->graph = DelegationGraph::factory()->create(['user_id' => $this->user->id]);
        $this->propagator = new DelegationContextPropagator;
    }

    public function test_context_propagates_down_from_parent_to_child(): void
    {
        $parent = DelegationTask::factory()->running()->create([
            'delegation_graph_id' => $this->graph->id,
        ]);
        $child = DelegationTask::factory()->create([
            'delegation_graph_id' => $this->graph->id,
            'parent_delegation_task_id' => $parent->id,
        ]);

        DelegationContext::factory()->create([
            'delegation_task_id' => $parent->id,
            'context_json' => ['project_path' => '/app', 'language' => 'php'],
            'propagation_direction' => 'down',
            'ttl_seconds' => 3600,
        ]);

        $this->propagator->propagateDown($parent, $child);

        $childContext = DelegationContext::where('delegation_task_id', $child->id)->first();
        $this->assertNotNull($childContext);
        $this->assertEquals('php', $childContext->context_json['language']);
        $this->assertEquals('/app', $childContext->context_json['project_path']);
        $this->assertEquals('down', $childContext->propagation_direction);
    }

    public function test_context_merges_up_from_child_to_parent(): void
    {
        $parent = DelegationTask::factory()->running()->create([
            'delegation_graph_id' => $this->graph->id,
        ]);
        $child = DelegationTask::factory()->succeeded()->create([
            'delegation_graph_id' => $this->graph->id,
            'parent_delegation_task_id' => $parent->id,
        ]);

        DelegationContext::factory()->create([
            'delegation_task_id' => $parent->id,
            'context_json' => ['project_path' => '/app'],
            'propagation_direction' => 'down',
            'ttl_seconds' => 3600,
        ]);

        DelegationContext::factory()->create([
            'delegation_task_id' => $child->id,
            'context_json' => ['result_summary' => 'Tests passed', 'files_changed' => 3],
            'propagation_direction' => 'up',
            'ttl_seconds' => 3600,
        ]);

        $this->propagator->mergeUp($child, $parent);

        $parentContext = DelegationContext::where('delegation_task_id', $parent->id)
            ->where('propagation_direction', 'up')
            ->first();
        $this->assertNotNull($parentContext);
        $this->assertEquals('Tests passed', $parentContext->context_json['result_summary']);
        $this->assertEquals(3, $parentContext->context_json['files_changed']);
    }

    public function test_expired_context_is_not_propagated(): void
    {
        $parent = DelegationTask::factory()->running()->create([
            'delegation_graph_id' => $this->graph->id,
        ]);
        $child = DelegationTask::factory()->create([
            'delegation_graph_id' => $this->graph->id,
            'parent_delegation_task_id' => $parent->id,
        ]);

        DelegationContext::factory()->create([
            'delegation_task_id' => $parent->id,
            'context_json' => ['stale_data' => true],
            'propagation_direction' => 'down',
            'ttl_seconds' => 60,
            'created_at' => now()->subHours(2),
        ]);

        $this->propagator->propagateDown($parent, $child);

        $childContext = DelegationContext::where('delegation_task_id', $child->id)->first();
        $this->assertNull($childContext);
    }

    public function test_bidirectional_context_propagates_down(): void
    {
        $parent = DelegationTask::factory()->running()->create([
            'delegation_graph_id' => $this->graph->id,
        ]);
        $child = DelegationTask::factory()->create([
            'delegation_graph_id' => $this->graph->id,
            'parent_delegation_task_id' => $parent->id,
        ]);

        DelegationContext::factory()->create([
            'delegation_task_id' => $parent->id,
            'context_json' => ['shared_config' => 'value'],
            'propagation_direction' => 'bidirectional',
            'ttl_seconds' => 3600,
        ]);

        $this->propagator->propagateDown($parent, $child);

        $childContext = DelegationContext::where('delegation_task_id', $child->id)->first();
        $this->assertNotNull($childContext);
        $this->assertEquals('value', $childContext->context_json['shared_config']);
    }

    public function test_up_only_context_does_not_propagate_down(): void
    {
        $parent = DelegationTask::factory()->running()->create([
            'delegation_graph_id' => $this->graph->id,
        ]);
        $child = DelegationTask::factory()->create([
            'delegation_graph_id' => $this->graph->id,
            'parent_delegation_task_id' => $parent->id,
        ]);

        DelegationContext::factory()->create([
            'delegation_task_id' => $parent->id,
            'context_json' => ['result_only' => true],
            'propagation_direction' => 'up',
            'ttl_seconds' => 3600,
        ]);

        $this->propagator->propagateDown($parent, $child);

        $childContext = DelegationContext::where('delegation_task_id', $child->id)->first();
        $this->assertNull($childContext);
    }

    public function test_context_records_created_by_agent_id(): void
    {
        $profile = DelegateeProfile::factory()->create(['user_id' => $this->user->id]);
        $parent = DelegationTask::factory()->running()->create([
            'delegation_graph_id' => $this->graph->id,
            'assigned_delegatee_profile_id' => $profile->id,
        ]);
        $child = DelegationTask::factory()->create([
            'delegation_graph_id' => $this->graph->id,
            'parent_delegation_task_id' => $parent->id,
        ]);

        DelegationContext::factory()->create([
            'delegation_task_id' => $parent->id,
            'context_json' => ['data' => 'value'],
            'propagation_direction' => 'down',
            'ttl_seconds' => 3600,
            'created_by_agent_id' => $profile->id,
        ]);

        $this->propagator->propagateDown($parent, $child);

        $childContext = DelegationContext::where('delegation_task_id', $child->id)->first();
        $this->assertNotNull($childContext);
        $this->assertEquals($profile->id, $childContext->created_by_agent_id);
    }
}
