<?php

declare(strict_types=1);

namespace Tests\Feature\Skills\EndToEnd;

use App\Models\AgentSkill;
use App\Models\DelegateeProfile;
use App\Models\User;
use App\Services\Skills\SkillContextInjector;
use App\Services\Skills\SkillDriftDetector;
use App\Services\Skills\SkillInstaller;
use App\Services\Skills\SkillResolver;
use App\Services\Skills\SkillTelemetryRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class SkillFullFlowTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private int $teamId;

    private string $fixturesPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->withPersonalTeam()->create();
        $this->teamId = $this->user->currentTeam->id;
        $this->fixturesPath = base_path('tests/Fixtures/Skills');

        config()->set('agent.skills.enabled', true);
        config()->set('agent.compliance.enabled', true);
    }

    protected function tearDown(): void
    {
        $skillsPath = config('agent.skills.storage_path');
        if (File::isDirectory($skillsPath.'/'.$this->teamId)) {
            File::deleteDirectory($skillsPath.'/'.$this->teamId);
        }

        $globalPath = config('agent.skills.global_storage_path');
        if (File::isDirectory($globalPath)) {
            File::deleteDirectory($globalPath);
        }

        $tmpPath = storage_path('app/skills/tmp');
        if (File::isDirectory($tmpPath)) {
            File::deleteDirectory($tmpPath);
        }

        parent::tearDown();
    }

    public function test_install_resolve_inject_telemetry_flow(): void
    {
        // 1. Install from fixture
        /** @var SkillInstaller $installer */
        $installer = app(SkillInstaller::class);
        $result = $installer->installFromFile(
            $this->fixturesPath.'/valid-skill.skill',
            $this->teamId,
            $this->user->id,
        );

        $this->assertTrue($result->success);
        $skill = AgentSkill::where('team_id', $this->teamId)
            ->where('slug', 'financial-compliance-check')
            ->first();
        $this->assertNotNull($skill);

        // 2. Resolve against matching task description
        /** @var SkillResolver $resolver */
        $resolver = app(SkillResolver::class);
        $resolved = $resolver->resolve(
            'Check financial compliance and audit regulatory requirements',
            $this->teamId,
        );

        $this->assertNotEmpty($resolved);
        $resolvedNames = array_map(fn ($r) => $r->name, $resolved);
        $this->assertContains('financial-compliance-check', $resolvedNames);

        // 3. Inject metadata into task markdown
        $injector = new SkillContextInjector;
        $enhanced = $injector->injectMetadata($resolved, 'Original task content');
        $this->assertStringContainsString('financial-compliance-check', $enhanced);
        $this->assertStringContainsString('Available Skills', $enhanced);

        // 4. Record telemetry (recorder updates skill row directly)
        /** @var SkillTelemetryRecorder $recorder */
        $recorder = app(SkillTelemetryRecorder::class);
        $recorder->recordInvocation(
            $skill,
            'test-run-attempt',
            'test-workflow',
            'completed',
            150,
            200,
        );

        // 5. Assert telemetry_event_ledger has skill event
        $event = DB::table('telemetry_event_ledger')
            ->where('event_type', 'skill.completed')
            ->first();
        $this->assertNotNull($event);

        // 6. Assert invocation_count incremented
        $skill->refresh();
        $this->assertSame(1, $skill->invocation_count);
        $this->assertNotNull($skill->last_invoked_at);

        // 7. Insert telemetry event with full payload for dashboard verification
        DB::table('telemetry_event_ledger')->insert([
            'schema_name' => 'agent.telemetry.event',
            'schema_version' => '1.0.0',
            'event_id' => \Illuminate\Support\Str::uuid()->toString(),
            'run_attempt_id' => \Illuminate\Support\Str::uuid()->toString(),
            'event_type' => 'skill.completed',
            'event_sequence' => 0,
            'event_at' => now(),
            'payload_json' => json_encode([
                'skill_id' => (string) $skill->id,
                'skill_name' => $skill->name,
                'skill_version' => $skill->version,
                'team_id' => $skill->team_id,
                'duration_ms' => 150,
                'token_usage' => 200,
                'outcome' => 'completed',
            ]),
            'schema_hash' => 'test',
            'normalizer_version' => '1.0.0',
            'registry_revision' => 1,
            'terminal' => false,
            'ingested_at' => now(),
            'created_at' => now(),
        ]);

        // Assert dashboard metrics include skill data
        $response = $this->actingAs($this->user)
            ->getJson('/agent/api/v1/skills/dashboard/usage');
        $response->assertOk();

        $data = $response->json('data');
        $skillEntry = collect($data)->firstWhere('skill_name', 'financial-compliance-check');
        $this->assertNotNull($skillEntry);
        $this->assertGreaterThanOrEqual(1, $skillEntry['invocation_count']);
    }

    public function test_drift_detection_blocks_drifted_skill(): void
    {
        // 1. Install skill
        /** @var SkillInstaller $installer */
        $installer = app(SkillInstaller::class);
        $result = $installer->installFromFile(
            $this->fixturesPath.'/valid-skill.skill',
            $this->teamId,
            $this->user->id,
        );

        $this->assertTrue($result->success);
        $skill = AgentSkill::where('team_id', $this->teamId)
            ->where('slug', 'financial-compliance-check')
            ->first();
        $this->assertNotNull($skill);

        // 2. Modify SKILL.md directly to simulate drift
        $skillMdPath = $skill->skill_path.'/SKILL.md';
        if (File::exists($skillMdPath)) {
            File::append($skillMdPath, "\n\n<!-- Modified content to trigger drift -->\n");
        }

        // 3. Detect drift
        /** @var SkillDriftDetector $detector */
        $detector = app(SkillDriftDetector::class);
        $driftResult = $detector->checkOnInvocation($skill);
        $this->assertTrue($driftResult->hasDrift);

        // 4. Mark as pending_review
        $skill->update(['status' => AgentSkill::STATUS_PENDING_REVIEW]);

        // 5. Resolve — drifted skill should not be included (not active)
        /** @var SkillResolver $resolver */
        $resolver = app(SkillResolver::class);
        $resolved = $resolver->resolve(
            'Check financial compliance',
            $this->teamId,
        );

        $resolvedNames = array_map(fn ($r) => $r->name, $resolved);
        $this->assertNotContains('financial-compliance-check', $resolvedNames);
    }

    public function test_elevated_skill_requires_trust_threshold(): void
    {
        // Install elevated risk skill
        $skill = AgentSkill::factory()->create([
            'team_id' => $this->teamId,
            'name' => 'elevated-test-skill',
            'slug' => 'elevated-test-skill',
            'description' => 'An elevated risk security scanning tool for auditing code',
            'risk_level' => AgentSkill::RISK_ELEVATED,
            'trigger_keywords' => ['security', 'audit', 'scanning', 'code'],
            'status' => AgentSkill::STATUS_ACTIVE,
        ]);

        // Low-trust delegatee (below 0.7 threshold for elevated)
        $lowTrustDelegatee = DelegateeProfile::factory()->create([
            'user_id' => $this->user->id,
            'trust_score' => 0.3,
        ]);

        /** @var SkillResolver $resolver */
        $resolver = app(SkillResolver::class);
        $resolved = $resolver->resolve(
            'Perform security audit and scanning of code',
            $this->teamId,
            $lowTrustDelegatee,
        );

        $resolvedNames = array_map(fn ($r) => $r->name, $resolved);
        $this->assertNotContains('elevated-test-skill', $resolvedNames);

        // High-trust delegatee (above 0.7)
        $highTrustDelegatee = DelegateeProfile::factory()->create([
            'user_id' => $this->user->id,
            'trust_score' => 0.9,
        ]);

        $resolved = $resolver->resolve(
            'Perform security audit and scanning of code',
            $this->teamId,
            $highTrustDelegatee,
        );

        $resolvedNames = array_map(fn ($r) => $r->name, $resolved);
        $this->assertContains('elevated-test-skill', $resolvedNames);
    }

    public function test_global_skill_accessible_across_teams(): void
    {
        // Install global skill under personal team
        $globalSkill = AgentSkill::factory()->global()->create([
            'team_id' => $this->teamId,
            'name' => 'global-test-skill',
            'slug' => 'global-test-skill',
            'description' => 'A globally available data analysis tool for processing',
            'trigger_keywords' => ['data', 'analysis', 'processing'],
            'status' => AgentSkill::STATUS_ACTIVE,
        ]);

        // Create different user with different team
        $otherUser = User::factory()->withPersonalTeam()->create();
        $otherTeamId = $otherUser->currentTeam->id;

        /** @var SkillResolver $resolver */
        $resolver = app(SkillResolver::class);
        $resolved = $resolver->resolve(
            'Run data analysis and processing',
            $otherTeamId,
        );

        $resolvedNames = array_map(fn ($r) => $r->name, $resolved);
        $this->assertContains('global-test-skill', $resolvedNames);
    }
}
