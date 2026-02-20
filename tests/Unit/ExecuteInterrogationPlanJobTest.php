<?php

namespace Tests\Unit;

use App\Jobs\ExecuteInterrogationPlanJob;
use App\Models\InterrogationEvent;
use App\Models\InterrogationSession;
use App\Models\User;
use App\Support\Interrogation\AdapterFactory;
use App\Support\Interrogation\Contracts\InterrogationRunnerAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExecuteInterrogationPlanJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_revision_failure_marks_revision_failed_without_failing_session(): void
    {
        $session = $this->planningSession();

        $adapter = $this->mock(InterrogationRunnerAdapter::class);
        $adapter->shouldReceive('buildPlanCommand')
            ->once()
            ->andReturn(['php', '-r', 'fwrite(STDERR, "revision command failed"); exit(1);']);
        $adapter->shouldReceive('buildEnvironment')
            ->once()
            ->andReturn([]);
        $adapter->shouldReceive('parsePlanResponse')->never();

        $factory = $this->mock(AdapterFactory::class);
        $factory->shouldReceive('make')
            ->once()
            ->andReturn($adapter);

        $job = new ExecuteInterrogationPlanJob((int) $session->id, 'Revise the plan');
        $this->app->call([$job, 'handle']);

        $session->refresh();

        $this->assertSame(InterrogationSession::STATUS_PLANNING, (string) $session->status);
        $this->assertSame(InterrogationSession::PHASE_PLANNING, (int) $session->phase);
        $this->assertSame('failed', (string) data_get($session->metadata_json, 'plan.revision_status'));
        $this->assertStringContainsString('revision command failed', (string) data_get($session->metadata_json, 'plan.revision_error'));
        $this->assertNull($session->finished_at);

        $this->assertTrue(
            InterrogationEvent::query()
                ->where('interrogation_session_id', $session->id)
                ->where('event_type', InterrogationEvent::TYPE_SYSTEM)
                ->where('payload->notice', 'plan_revision_failed')
                ->exists()
        );
    }

    public function test_non_revision_plan_failure_sets_session_failed(): void
    {
        $session = $this->planningSession();

        $adapter = $this->mock(InterrogationRunnerAdapter::class);
        $adapter->shouldReceive('buildPlanCommand')
            ->once()
            ->andReturn(['php', '-r', 'fwrite(STDERR, "plan command failed"); exit(1);']);
        $adapter->shouldReceive('buildEnvironment')
            ->once()
            ->andReturn([]);
        $adapter->shouldReceive('parsePlanResponse')->never();

        $factory = $this->mock(AdapterFactory::class);
        $factory->shouldReceive('make')
            ->once()
            ->andReturn($adapter);

        $job = new ExecuteInterrogationPlanJob((int) $session->id);
        $this->app->call([$job, 'handle']);

        $session->refresh();

        $this->assertSame(InterrogationSession::STATUS_FAILED, (string) $session->status);
        $this->assertSame('PLAN_COMMAND_FAILED', (string) $session->error_code);
        $this->assertSame('failed', (string) data_get($session->metadata_json, 'plan.generation_status'));
        $this->assertStringContainsString('plan command failed', (string) data_get($session->metadata_json, 'plan.generation_error'));
    }

    public function test_revision_plan_generation_uses_fresh_non_resumed_context(): void
    {
        $session = $this->planningSession();
        $session->cli_session_id = 'resume-session-id';
        $session->save();

        $adapter = $this->mock(InterrogationRunnerAdapter::class);
        $adapter->shouldReceive('buildPlanCommand')
            ->once()
            ->withArgs(function (InterrogationSession $sessionArg): bool {
                return (string) $sessionArg->cli_session_id === '';
            })
            ->andReturn(['php', '-r', 'echo json_encode(["plan_markdown" => "## Revised plan\n- Update app/Jobs/ExecuteInterrogationPlanJob.php", "sections" => ["Implementation"], "risks" => ["Risk"], "assumptions" => ["Assumption"]]);']);
        $adapter->shouldReceive('buildEnvironment')
            ->once()
            ->andReturn([]);
        $adapter->shouldReceive('parsePlanResponse')
            ->once()
            ->andReturn([
                'plan_markdown' => '## Revised plan'.PHP_EOL.'- Update app/Jobs/ExecuteInterrogationPlanJob.php',
                'sections' => ['Implementation'],
                'risks' => ['Risk'],
                'assumptions' => ['Assumption'],
            ]);

        $factory = $this->mock(AdapterFactory::class);
        $factory->shouldReceive('make')
            ->once()
            ->andReturn($adapter);

        $job = new ExecuteInterrogationPlanJob((int) $session->id, 'Revise the plan');
        $this->app->call([$job, 'handle']);

        $session->refresh();

        $this->assertSame(InterrogationSession::STATUS_PLANNING, (string) $session->status);
        $this->assertSame(
            'idle',
            (string) data_get($session->metadata_json, 'plan.revision_status'),
            'Revision error: '.(string) data_get($session->metadata_json, 'plan.revision_error')
        );
        $this->assertStringContainsString('## Revised plan', (string) data_get($session->plan_json, 'plan_markdown'));
    }

    public function test_codex_plan_prompt_includes_parity_depth_requirements(): void
    {
        config()->set('agent.interrogation.codex_plan_quality_retries', 0);
        config()->set('agent.interrogation.codex_plan_min_markdown_chars', 1);
        config()->set('agent.interrogation.codex_plan_min_sections', 1);
        config()->set('agent.interrogation.codex_plan_min_concrete_references', 1);

        $session = $this->planningSession('codex');

        $adapter = $this->mock(InterrogationRunnerAdapter::class);
        $adapter->shouldReceive('buildPlanCommand')
            ->once()
            ->withArgs(function (
                InterrogationSession $sessionArg,
                string $planningPrompt
            ) use ($session): bool {
                return (int) $sessionArg->id === (int) $session->id
                    && str_contains($planningPrompt, 'Codex parity requirements')
                    && str_contains($planningPrompt, 'implementation-ready');
            })
            ->andReturn(['php', '-r', 'echo json_encode(["plan_markdown" => "Codex detailed plan\n- Update app/Http/Controllers/Api/V1/InterrogationSessionController.php", "sections" => ["Architecture", "API Contracts", "Tests"], "risks" => ["Risk"], "assumptions" => ["Assumption"]]);']);
        $adapter->shouldReceive('buildEnvironment')
            ->once()
            ->andReturn([]);
        $adapter->shouldReceive('parsePlanResponse')
            ->once()
            ->andReturn([
                'plan_markdown' => 'Codex detailed plan'.PHP_EOL
                    .'- Update app/Http/Controllers/Api/V1/InterrogationSessionController.php'.PHP_EOL
                    .str_repeat('x', 320),
                'sections' => ['Architecture', 'API Contracts', 'Tests'],
                'risks' => ['Risk'],
                'assumptions' => ['Assumption'],
            ]);

        $factory = $this->mock(AdapterFactory::class);
        $factory->shouldReceive('make')
            ->once()
            ->andReturn($adapter);

        $job = new ExecuteInterrogationPlanJob((int) $session->id);
        $this->app->call([$job, 'handle']);

        $session->refresh();

        $this->assertStringContainsString('Codex detailed plan', (string) data_get($session->plan_json, 'plan_markdown'));
        $this->assertStringContainsString(
            'app/Http/Controllers/Api/V1/InterrogationSessionController.php',
            (string) data_get($session->plan_json, 'plan_markdown')
        );
    }

    public function test_codex_revision_quality_failure_preserves_existing_plan_and_marks_revision_failed(): void
    {
        config()->set('agent.interrogation.codex_plan_quality_retries', 0);
        config()->set('agent.interrogation.codex_plan_min_markdown_chars', 500);
        config()->set('agent.interrogation.codex_plan_min_sections', 8);
        config()->set('agent.interrogation.codex_plan_min_concrete_references', 6);
        config()->set('agent.interrogation.plan_payload_retry_attempts', 0);

        $session = $this->planningSession('codex');
        $session->plan_json = [
            'plan_markdown' => '## Existing Plan'.PHP_EOL.'- Keep me',
            'sections' => ['Scope', 'Implementation'],
            'risks' => ['Risk A'],
            'assumptions' => ['Assumption A'],
        ];
        $session->save();

        $adapter = $this->mock(InterrogationRunnerAdapter::class);
        $adapter->shouldReceive('buildPlanCommand')
            ->once()
            ->andReturn(['php', '-r', 'echo json_encode(["plan_markdown" => "I\'m revising the plan against the locked baseline now.", "sections" => [], "risks" => [], "assumptions" => []]);']);
        $adapter->shouldReceive('buildEnvironment')
            ->once()
            ->andReturn([]);
        $adapter->shouldReceive('parsePlanResponse')
            ->once()
            ->andReturn([
                'plan_markdown' => 'I\'m revising the plan against the locked baseline now.',
                'sections' => [],
                'risks' => [],
                'assumptions' => [],
            ]);

        $factory = $this->mock(AdapterFactory::class);
        $factory->shouldReceive('make')
            ->once()
            ->andReturn($adapter);

        $job = new ExecuteInterrogationPlanJob((int) $session->id, 'Rewrite the plan with stronger detail.');
        $this->app->call([$job, 'handle']);

        $session->refresh();

        $this->assertSame(InterrogationSession::STATUS_PLANNING, (string) $session->status);
        $this->assertSame('failed', (string) data_get($session->metadata_json, 'plan.revision_status'));
        $this->assertStringContainsString('Plan payload validation failed', (string) data_get($session->metadata_json, 'plan.revision_error'));
        $this->assertSame('## Existing Plan'.PHP_EOL.'- Keep me', (string) data_get($session->plan_json, 'plan_markdown'));
        $this->assertSame(['Scope', 'Implementation'], data_get($session->plan_json, 'sections'));

        $this->assertTrue(
            InterrogationEvent::query()
                ->where('interrogation_session_id', $session->id)
                ->where('event_type', InterrogationEvent::TYPE_ERROR)
                ->where('payload->code', 'PLAN_REVISION_PAYLOAD_INVALID')
                ->exists()
        );
    }

    public function test_plan_revision_retries_invalid_payload_and_persists_fixed_plan(): void
    {
        config()->set('agent.interrogation.plan_payload_retry_attempts', 2);
        config()->set('agent.interrogation.codex_plan_quality_retries', 0);
        config()->set('agent.interrogation.codex_plan_min_markdown_chars', 1);
        config()->set('agent.interrogation.codex_plan_min_sections', 1);
        config()->set('agent.interrogation.codex_plan_min_concrete_references', 1);

        $session = $this->planningSession('codex');
        $session->plan_json = [
            'plan_markdown' => '## Existing Plan'.PHP_EOL.'- Keep me',
            'sections' => ['Scope'],
            'risks' => ['Risk A'],
            'assumptions' => ['Assumption A'],
        ];
        $session->save();

        $adapter = $this->mock(InterrogationRunnerAdapter::class);
        $buildCall = 0;
        $adapter->shouldReceive('buildPlanCommand')
            ->twice()
            ->andReturnUsing(function (
                InterrogationSession $sessionArg,
                string $planningPrompt,
                string $systemPrompt
            ) use (&$buildCall): array {
                $buildCall++;
                $this->assertSame('', (string) $sessionArg->cli_session_id);
                $this->assertNotSame('', trim($systemPrompt));

                if ($buildCall === 1) {
                    return ['php', '-r', 'echo json_encode(["plan_markdown" => "I\'m revising the plan against the locked baseline now.", "sections" => [], "risks" => [], "assumptions" => []]);'];
                }

                $this->assertStringContainsString('failed validation on attempt 1/2', $planningPrompt);
                $this->assertStringContainsString('Do not include process narration', $planningPrompt);

                return ['php', '-r', 'echo json_encode(["plan_markdown" => "## Scope\\n- Update app/Jobs/ExecuteInterrogationPlanJob.php\\n\\n## Implementation\\n- Wire App\\\\Jobs\\\\ExecuteInterrogationPlanJob retry path\\n\\n## Test Strategy\\n- Add tests/Unit/ExecuteInterrogationPlanJobTest.php assertions\\n'.str_repeat('x', 360).'","sections" => ["Scope", "Implementation", "Test Strategy"], "risks" => ["Risk"], "assumptions" => ["Assumption"]]);'];
            });
        $adapter->shouldReceive('buildEnvironment')
            ->twice()
            ->andReturn([]);
        $parseCall = 0;
        $adapter->shouldReceive('parsePlanResponse')
            ->twice()
            ->andReturnUsing(function () use (&$parseCall): array {
                $parseCall++;

                if ($parseCall === 1) {
                    return [
                        'plan_markdown' => 'I\'m revising the plan against the locked baseline now.',
                        'sections' => [],
                        'risks' => [],
                        'assumptions' => [],
                    ];
                }

                return [
                    'plan_markdown' => '## Scope'.PHP_EOL
                        .'- Update app/Jobs/ExecuteInterrogationPlanJob.php'.PHP_EOL
                        .'## Implementation'.PHP_EOL
                        .'- Wire App\\Jobs\\ExecuteInterrogationPlanJob retry path'.PHP_EOL
                        .'## Test Strategy'.PHP_EOL
                        .'- Add tests/Unit/ExecuteInterrogationPlanJobTest.php assertions'.PHP_EOL
                        .str_repeat('x', 360),
                    'sections' => ['Scope', 'Implementation', 'Test Strategy'],
                    'risks' => ['Risk'],
                    'assumptions' => ['Assumption'],
                ];
            });

        $factory = $this->mock(AdapterFactory::class);
        $factory->shouldReceive('make')
            ->once()
            ->andReturn($adapter);

        $job = new ExecuteInterrogationPlanJob((int) $session->id, 'Rewrite the plan with stronger detail.');
        $this->app->call([$job, 'handle']);

        $session->refresh();

        $this->assertSame(
            'idle',
            (string) data_get($session->metadata_json, 'plan.revision_status'),
            'Revision error: '.(string) data_get($session->metadata_json, 'plan.revision_error')
        );
        $this->assertStringContainsString('Update app/Jobs/ExecuteInterrogationPlanJob.php', (string) data_get($session->plan_json, 'plan_markdown'));
        $this->assertSame(['Scope', 'Implementation', 'Test Strategy'], data_get($session->plan_json, 'sections'));
        $this->assertFalse(
            InterrogationEvent::query()
                ->where('interrogation_session_id', $session->id)
                ->where('event_type', InterrogationEvent::TYPE_ERROR)
                ->where('payload->code', 'PLAN_REVISION_PAYLOAD_INVALID')
                ->exists()
        );
    }

    public function test_codex_plan_quality_retry_rewrites_thin_plan_before_persisting(): void
    {
        config()->set('agent.interrogation.codex_plan_quality_retries', 1);
        config()->set('agent.interrogation.codex_plan_min_markdown_chars', 200);
        config()->set('agent.interrogation.codex_plan_min_concrete_references', 1);

        $session = $this->planningSession('codex');

        $adapter = $this->mock(InterrogationRunnerAdapter::class);
        $buildCall = 0;
        $adapter->shouldReceive('buildPlanCommand')
            ->twice()
            ->andReturnUsing(function (
                InterrogationSession $sessionArg,
                string $planningPrompt,
                string $systemPrompt
            ) use (&$buildCall): array {
                $buildCall++;
                $this->assertNotSame('', trim($systemPrompt));

                if ($buildCall === 1) {
                    return ['php', '-r', 'echo json_encode(["plan_markdown" => "Implementation plan\\n1. Do work\\n2. Test", "sections" => ["Scope"], "risks" => ["Risk"], "assumptions" => ["Assumption"]]);'];
                }

                $this->assertStringContainsString('Rewrite the plan with substantially more technical detail', $planningPrompt);

                return ['php', '-r', 'echo json_encode(["plan_markdown" => "Detailed plan\\n- Update app/Support/Interrogation/Adapters/CodexAdapter.php\\n- Add tests/Feature/InterrogationApiWorkflowTest.php assertions", "sections" => ["Scope", "Implementation", "Validation", "Testing", "Rollout", "Observability", "Risks", "Assumptions"], "risks" => ["Risk"], "assumptions" => ["Assumption"]]);'];
            });
        $adapter->shouldReceive('buildEnvironment')
            ->twice()
            ->andReturn([]);
        $parseCall = 0;
        $adapter->shouldReceive('parsePlanResponse')
            ->twice()
            ->andReturnUsing(function () use (&$parseCall): array {
                $parseCall++;

                if ($parseCall === 1) {
                    return [
                        'plan_markdown' => 'Implementation plan'.PHP_EOL.'1. Do work'.PHP_EOL.'2. Test',
                        'sections' => ['Scope'],
                        'risks' => ['Risk'],
                        'assumptions' => ['Assumption'],
                    ];
                }

                return [
                    'plan_markdown' => 'Detailed plan'.PHP_EOL
                        .'- Update app/Support/Interrogation/Adapters/CodexAdapter.php'.PHP_EOL
                        .'- Add tests/Feature/InterrogationApiWorkflowTest.php assertions'.PHP_EOL
                        .str_repeat('x', 220),
                    'sections' => ['Scope', 'Implementation', 'Validation', 'Testing', 'Rollout', 'Observability', 'Risks', 'Assumptions'],
                    'risks' => ['Risk'],
                    'assumptions' => ['Assumption'],
                ];
            });

        $factory = $this->mock(AdapterFactory::class);
        $factory->shouldReceive('make')
            ->once()
            ->andReturn($adapter);

        $job = new ExecuteInterrogationPlanJob((int) $session->id);
        $this->app->call([$job, 'handle']);

        $session->refresh();

        $this->assertStringContainsString('app/Support/Interrogation/Adapters/CodexAdapter.php', (string) data_get($session->plan_json, 'plan_markdown'));
        $this->assertSame('idle', (string) data_get($session->metadata_json, 'plan.generation_status'));
    }

    private function planningSession(string $runnerType = 'claude'): InterrogationSession
    {
        $user = User::factory()->create();

        return InterrogationSession::query()->create([
            'user_id' => $user->id,
            'name' => 'Plan job test session',
            'runner_type' => $runnerType,
            'project_directory' => base_path(),
            'interrogation_type' => InterrogationSession::TYPE_FEATURE,
            'status' => InterrogationSession::STATUS_PLANNING,
            'phase' => InterrogationSession::PHASE_PLANNING,
            'metadata_json' => [
                'plan' => [
                    'revision_status' => 'queued',
                ],
            ],
        ]);
    }
}
