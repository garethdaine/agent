<?php

namespace Tests\Unit\Support\Delegation;

use App\Models\DelegationGraph;
use App\Support\Delegation\GraphStateTransitionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GraphStateTransitionServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_transition_succeeds_from_valid_status(): void
    {
        $graph = DelegationGraph::factory()->create(['status' => 'draft']);
        $service = new GraphStateTransitionService;

        $result = $service->transition($graph->id, ['draft'], 'validating');

        $this->assertTrue($result);
        $this->assertEquals('validating', $graph->fresh()->status);
    }

    public function test_transition_fails_from_invalid_status(): void
    {
        $graph = DelegationGraph::factory()->create(['status' => 'running']);
        $service = new GraphStateTransitionService;

        $result = $service->transition($graph->id, ['draft'], 'validating');

        $this->assertFalse($result);
        $this->assertEquals('running', $graph->fresh()->status);
    }

    public function test_transition_sets_additional_attributes(): void
    {
        $graph = DelegationGraph::factory()->create(['status' => 'ready']);
        $service = new GraphStateTransitionService;

        $result = $service->transition($graph->id, ['ready'], 'running', ['started_at' => now()]);

        $this->assertTrue($result);
        $this->assertNotNull($graph->fresh()->started_at);
    }

    public function test_transition_fails_for_nonexistent_graph(): void
    {
        $service = new GraphStateTransitionService;

        $result = $service->transition(99999, ['draft'], 'validating');

        $this->assertFalse($result);
    }

    public function test_transition_fails_with_empty_from_statuses(): void
    {
        $graph = DelegationGraph::factory()->create(['status' => 'draft']);
        $service = new GraphStateTransitionService;

        $result = $service->transition($graph->id, [], 'validating');

        $this->assertFalse($result);
        $this->assertEquals('draft', $graph->fresh()->status);
    }

    public function test_transition_accepts_multiple_from_statuses(): void
    {
        $graph = DelegationGraph::factory()->create(['status' => 'ready']);
        $service = new GraphStateTransitionService;

        $result = $service->transition($graph->id, ['draft', 'ready'], 'running');

        $this->assertTrue($result);
        $this->assertEquals('running', $graph->fresh()->status);
    }

    public function test_transition_does_not_affect_soft_deleted_records(): void
    {
        $graph = DelegationGraph::factory()->create(['status' => 'draft']);
        $graph->delete();
        $service = new GraphStateTransitionService;

        $result = $service->transition($graph->id, ['draft'], 'validating');

        $this->assertFalse($result);
        $this->assertEquals('draft', DelegationGraph::withTrashed()->find($graph->id)->status);
    }

    public function test_concurrent_update_only_one_succeeds(): void
    {
        $graph = DelegationGraph::factory()->create(['status' => 'draft']);
        $service = new GraphStateTransitionService;

        // First transition succeeds
        $result1 = $service->transition($graph->id, ['draft'], 'validating');

        // Second transition with same from status fails (status already changed)
        $result2 = $service->transition($graph->id, ['draft'], 'ready');

        $this->assertTrue($result1);
        $this->assertFalse($result2);
        $this->assertEquals('validating', $graph->fresh()->status);
    }

    public function test_transition_updates_timestamp(): void
    {
        $this->freezeTime();
        $graph = DelegationGraph::factory()->create(['status' => 'draft']);
        $originalUpdatedAt = $graph->updated_at;

        // Travel forward in time to ensure timestamp difference
        $this->travel(5)->seconds();

        $service = new GraphStateTransitionService;
        $result = $service->transition($graph->id, ['draft'], 'validating');

        $this->assertTrue($result);
        $this->assertGreaterThan($originalUpdatedAt, $graph->fresh()->updated_at);
    }

    public function test_transition_with_error_attributes(): void
    {
        $graph = DelegationGraph::factory()->running()->create();
        $service = new GraphStateTransitionService;

        $result = $service->transition(
            $graph->id,
            ['running'],
            'failed',
            [
                'error_code' => 'TASK_FAILED',
                'error_summary' => 'One or more tasks failed.',
                'finished_at' => now(),
            ]
        );

        $this->assertTrue($result);
        $freshGraph = $graph->fresh();
        $this->assertEquals('failed', $freshGraph->status);
        $this->assertEquals('TASK_FAILED', $freshGraph->error_code);
        $this->assertEquals('One or more tasks failed.', $freshGraph->error_summary);
        $this->assertNotNull($freshGraph->finished_at);
    }
}
