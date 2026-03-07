<?php

declare(strict_types=1);

namespace Tests\Feature\Skills\Compliance;

use App\Models\AgentJobRun;
use App\Models\AgentSkill;
use App\Models\User;
use App\Support\Compliance\LessonsManager;
use App\Support\Compliance\OrchestrationPolicyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class SkillComplianceIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private int $teamId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->withPersonalTeam()->create();
        $this->teamId = $this->user->currentTeam->id;

        config()->set('agent.compliance.enabled', true);
        config()->set('agent.compliance.enforcement_mode', 'strict');
        config()->set('agent.compliance.plan_gate_enabled', true);
    }

    public function test_complex_skill_chain_triggers_plan_gate(): void
    {
        // Create 5 skills where >3 have run_after dependencies (complex chain)
        $skillA = AgentSkill::factory()->create([
            'team_id' => $this->teamId,
            'name' => 'skill-a',
            'slug' => 'skill-a',
            'run_after' => [],
        ]);
        $skillB = AgentSkill::factory()->create([
            'team_id' => $this->teamId,
            'name' => 'skill-b',
            'slug' => 'skill-b',
            'run_after' => ['skill-a'],
        ]);
        $skillC = AgentSkill::factory()->create([
            'team_id' => $this->teamId,
            'name' => 'skill-c',
            'slug' => 'skill-c',
            'run_after' => ['skill-b'],
        ]);
        $skillD = AgentSkill::factory()->create([
            'team_id' => $this->teamId,
            'name' => 'skill-d',
            'slug' => 'skill-d',
            'run_after' => ['skill-c'],
        ]);
        $skillE = AgentSkill::factory()->create([
            'team_id' => $this->teamId,
            'name' => 'skill-e',
            'slug' => 'skill-e',
            'run_after' => ['skill-d'],
        ]);

        $resolvedSkills = [
            ['id' => $skillA->id, 'name' => 'skill-a', 'run_after' => []],
            ['id' => $skillB->id, 'name' => 'skill-b', 'run_after' => ['skill-a']],
            ['id' => $skillC->id, 'name' => 'skill-c', 'run_after' => ['skill-b']],
            ['id' => $skillD->id, 'name' => 'skill-d', 'run_after' => ['skill-c']],
            ['id' => $skillE->id, 'name' => 'skill-e', 'run_after' => ['skill-d']],
        ];

        $run = AgentJobRun::factory()->create([
            'user_id' => $this->user->id,
        ]);

        /** @var OrchestrationPolicyService $service */
        $service = app(OrchestrationPolicyService::class);
        $result = $service->evaluatePreRun($run, [
            'resolved_skills' => $resolvedSkills,
        ]);

        // Should have a skill_chain_complexity gate
        $gateNames = array_map(fn ($g) => $g->gate, $result->gates);
        $this->assertContains('skill_chain_complexity', $gateNames);

        $skillGate = collect($result->gates)->first(fn ($g) => $g->gate === 'skill_chain_complexity');
        $this->assertSame('requires_plan', $skillGate->status);
        $this->assertSame('complex_skill_chain', $skillGate->reasonCode);
    }

    public function test_simple_skill_invocation_passes_plan_gate(): void
    {
        $skill = AgentSkill::factory()->create([
            'team_id' => $this->teamId,
            'name' => 'simple-skill',
            'slug' => 'simple-skill',
            'run_after' => [],
        ]);

        $resolvedSkills = [
            ['id' => $skill->id, 'name' => 'simple-skill', 'run_after' => []],
        ];

        $run = AgentJobRun::factory()->create([
            'user_id' => $this->user->id,
        ]);

        /** @var OrchestrationPolicyService $service */
        $service = app(OrchestrationPolicyService::class);
        $result = $service->evaluatePreRun($run, [
            'resolved_skills' => $resolvedSkills,
        ]);

        // No skill_chain_complexity gate for a single skill
        $gateNames = array_map(fn ($g) => $g->gate, $result->gates);
        $this->assertNotContains('skill_chain_complexity', $gateNames);
    }

    public function test_skill_execution_creates_lesson(): void
    {
        $projectDir = sys_get_temp_dir().'/skill-lessons-test-'.uniqid();
        mkdir($projectDir.'/tasks', 0755, true);

        try {
            $manager = new LessonsManager();
            $manager->appendLesson(
                $projectDir,
                'Skill execution produced unexpected output format',
                'failed_retry',
                [
                    'task_title' => 'Test Task',
                    'task_category' => 'custom',
                    'runner_type' => 'claude',
                    'skill_id' => 'abc-123',
                    'skill_name' => 'output-fact-checker',
                    'skill_version' => '1.0.0',
                ]
            );

            $lessons = $manager->queryLessons($projectDir);

            $this->assertNotEmpty($lessons);
            $this->assertStringContains('skill_id', $lessons[0]['content']);
            $this->assertStringContains('output-fact-checker', $lessons[0]['content']);
        } finally {
            File::deleteDirectory($projectDir);
        }
    }

    private function assertStringContains(string $needle, string $haystack): void
    {
        $this->assertTrue(
            str_contains($haystack, $needle),
            "Failed asserting that '{$haystack}' contains '{$needle}'"
        );
    }
}
