<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Org;

use App\Models\OrgAgentProfile;
use App\Models\OrgRitualRun;
use App\Models\OrgRitualTemplate;
use App\Models\User;
use App\Support\Org\RitualToDelegationMapper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RitualToDelegationMapperTest extends TestCase
{
    use RefreshDatabase;

    private RitualToDelegationMapper $mapper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mapper = app(RitualToDelegationMapper::class);
    }

    public function test_maps_phases_to_delegation_tasks(): void
    {
        $user = User::factory()->create();
        OrgAgentProfile::factory()->forUser($user)->create(['role_slug' => 'dev']);

        $template = OrgRitualTemplate::factory()->for($user)->create([
            'phase_graph' => [
                ['id' => 'phase1', 'name' => 'Phase 1', 'depends_on' => []],
                ['id' => 'phase2', 'name' => 'Phase 2', 'depends_on' => ['phase1']],
            ],
            'phase_role_mappings' => [
                'phase1' => 'dev',
                'phase2' => 'dev',
            ],
        ]);
        $run = OrgRitualRun::factory()->for($template, 'template')->for($user)->create();

        $tasks = $this->mapper->mapPhasesToTasks($template, $run);

        $this->assertCount(2, $tasks);
        $this->assertEquals('phase1', $tasks[0]['id']);
        $this->assertEquals('phase2', $tasks[1]['id']);
        $this->assertEquals(['phase1'], $tasks[1]['depends_on']);
    }

    public function test_assigns_delegatee_profile_from_agent(): void
    {
        $user = User::factory()->create();
        $agent = OrgAgentProfile::factory()->forUser($user)->create([
            'role_slug' => 'reviewer',
        ]);

        $template = OrgRitualTemplate::factory()->for($user)->create([
            'phase_graph' => [
                ['id' => 'review', 'name' => 'Review', 'depends_on' => []],
            ],
            'phase_role_mappings' => ['review' => 'reviewer'],
        ]);
        $run = OrgRitualRun::factory()->for($template, 'template')->for($user)->create();

        $tasks = $this->mapper->mapPhasesToTasks($template, $run);

        $this->assertEquals($agent->delegatee_profile_id, $tasks[0]['delegatee_profile_id']);
    }

    public function test_includes_context_inputs_in_task_contract(): void
    {
        $user = User::factory()->create();
        OrgAgentProfile::factory()->forUser($user)->create(['role_slug' => 'analyst']);

        $contextInputs = [
            'memory_ref' => 'entity:customer_data',
            'previous_run_output' => 'run:last:summary',
        ];

        $template = OrgRitualTemplate::factory()->for($user)->create([
            'phase_graph' => [
                ['id' => 'analyze', 'name' => 'Analyze', 'depends_on' => []],
            ],
            'phase_role_mappings' => ['analyze' => 'analyst'],
            'context_inputs' => $contextInputs,
        ]);
        $run = OrgRitualRun::factory()->for($template, 'template')->for($user)->create();

        $tasks = $this->mapper->mapPhasesToTasks($template, $run);

        $this->assertEquals($contextInputs, $tasks[0]['contract']['context_inputs'] ?? null);
    }

    public function test_maps_multi_phase_dag_correctly(): void
    {
        $user = User::factory()->create();
        OrgAgentProfile::factory()->forUser($user)->create(['role_slug' => 'coordinator']);
        OrgAgentProfile::factory()->forUser($user)->create(['role_slug' => 'worker']);

        $template = OrgRitualTemplate::factory()->for($user)->create([
            'phase_graph' => [
                ['id' => 'init', 'name' => 'Initialize', 'depends_on' => []],
                ['id' => 'work_a', 'name' => 'Work A', 'depends_on' => ['init']],
                ['id' => 'work_b', 'name' => 'Work B', 'depends_on' => ['init']],
                ['id' => 'merge', 'name' => 'Merge Results', 'depends_on' => ['work_a', 'work_b']],
            ],
            'phase_role_mappings' => [
                'init' => 'coordinator',
                'work_a' => 'worker',
                'work_b' => 'worker',
                'merge' => 'coordinator',
            ],
        ]);
        $run = OrgRitualRun::factory()->for($template, 'template')->for($user)->create();

        $tasks = $this->mapper->mapPhasesToTasks($template, $run);

        $this->assertCount(4, $tasks);

        // Find merge task and verify it has correct dependencies
        $mergeTask = collect($tasks)->firstWhere('id', 'merge');
        $this->assertNotNull($mergeTask);
        $this->assertEqualsCanonicalizing(['work_a', 'work_b'], $mergeTask['depends_on']);
    }

    public function test_handles_missing_role_mapping_gracefully(): void
    {
        $user = User::factory()->create();
        // No agent with 'missing_role' exists

        $template = OrgRitualTemplate::factory()->for($user)->create([
            'phase_graph' => [
                ['id' => 'orphan', 'name' => 'Orphan Phase', 'depends_on' => []],
            ],
            'phase_role_mappings' => ['orphan' => 'missing_role'],
        ]);
        $run = OrgRitualRun::factory()->for($template, 'template')->for($user)->create();

        $tasks = $this->mapper->mapPhasesToTasks($template, $run);

        // Should still produce a task, but delegatee_profile_id will be null
        $this->assertCount(1, $tasks);
        $this->assertNull($tasks[0]['delegatee_profile_id'] ?? null);
    }

    public function test_includes_run_correlation_id_in_tasks(): void
    {
        $user = User::factory()->create();
        OrgAgentProfile::factory()->forUser($user)->create(['role_slug' => 'runner']);

        $template = OrgRitualTemplate::factory()->for($user)->create([
            'phase_graph' => [
                ['id' => 'task1', 'name' => 'Task 1', 'depends_on' => []],
            ],
            'phase_role_mappings' => ['task1' => 'runner'],
        ]);
        // Use a valid UUID format for correlation_id
        $correlationId = '550e8400-e29b-41d4-a716-446655440000';
        $run = OrgRitualRun::factory()
            ->for($template, 'template')
            ->for($user)
            ->withCorrelationId($correlationId)
            ->create();

        $tasks = $this->mapper->mapPhasesToTasks($template, $run);

        $this->assertEquals($correlationId, $tasks[0]['contract']['correlation_id'] ?? null);
    }
}
