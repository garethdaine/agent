<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\InterrogationEvent;
use App\Models\InterrogationSession;
use App\Models\InterrogationSetting;
use App\Models\User;
use App\Support\Interrogation\SystemPromptResolver;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SystemPromptResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_prompt_is_runtime_safe_when_no_setting_exists(): void
    {
        $user = User::factory()->create();

        $session = InterrogationSession::query()->create([
            'user_id' => $user->id,
            'runner_type' => 'claude',
            'project_directory' => base_path(),
            'interrogation_type' => InterrogationSession::TYPE_FEATURE,
            'feature_brief' => 'Build delegation graph support.',
            'status' => InterrogationSession::STATUS_SETUP,
            'phase' => InterrogationSession::PHASE_SETUP,
        ]);

        $resolver = new SystemPromptResolver;
        $prompt = $resolver->resolveForPhase($session, 'interrogation');

        $this->assertStringContainsString('non-interactive CLI mode', $prompt);
        $this->assertStringContainsString('Return ONLY a single JSON object', $prompt);
        $this->assertStringNotContainsString('## Phase 0: Feature or General Interrogation', $prompt);
        $this->assertStringContainsString('Session Context:', $prompt);
        $this->assertStringContainsString('Interrogation Type: feature', $prompt);
        $this->assertStringContainsString('Feature Brief:', $prompt);
    }

    public function test_user_setting_overrides_default_base_prompt(): void
    {
        $user = User::factory()->create();

        $session = InterrogationSession::query()->create([
            'user_id' => $user->id,
            'runner_type' => 'claude',
            'project_directory' => base_path(),
            'interrogation_type' => InterrogationSession::TYPE_GENERAL,
            'status' => InterrogationSession::STATUS_SETUP,
            'phase' => InterrogationSession::PHASE_SETUP,
        ]);

        InterrogationSetting::setForUser((int) $user->id, 'interrogation.system_prompt', 'Custom runtime prompt.');

        $resolver = new SystemPromptResolver;
        $prompt = $resolver->resolveForPhase($session, 'interrogation');

        $this->assertStringContainsString('Custom runtime prompt.', $prompt);
        $this->assertStringContainsString('Session Context:', $prompt);
        $this->assertStringContainsString('Interrogation Type: general', $prompt);
    }

    public function test_general_interrogation_includes_session_brief_when_provided(): void
    {
        $user = User::factory()->create();

        $session = InterrogationSession::query()->create([
            'user_id' => $user->id,
            'runner_type' => 'claude',
            'project_directory' => base_path(),
            'interrogation_type' => InterrogationSession::TYPE_GENERAL,
            'feature_brief' => 'Refactor and bug-fix scope with explicit acceptance criteria.',
            'status' => InterrogationSession::STATUS_SETUP,
            'phase' => InterrogationSession::PHASE_SETUP,
        ]);

        $resolver = new SystemPromptResolver;
        $prompt = $resolver->resolveForPhase($session, 'interrogation');

        $this->assertStringContainsString('Interrogation Type: general', $prompt);
        $this->assertStringContainsString('Session Brief:', $prompt);
        $this->assertStringContainsString('Refactor and bug-fix scope with explicit acceptance criteria.', $prompt);
    }

    public function test_interrogation_prompt_includes_recent_discovery_findings(): void
    {
        $user = User::factory()->create();

        $session = InterrogationSession::query()->create([
            'user_id' => $user->id,
            'runner_type' => 'claude',
            'project_directory' => base_path(),
            'interrogation_type' => InterrogationSession::TYPE_GENERAL,
            'feature_brief' => 'Improve output fidelity and reliability.',
            'status' => InterrogationSession::STATUS_INTERROGATING,
            'phase' => InterrogationSession::PHASE_INTERROGATION,
        ]);

        InterrogationEvent::query()->create([
            'interrogation_session_id' => $session->id,
            'sequence' => 1,
            'event_type' => InterrogationEvent::TYPE_DISCOVERY_ACTIVITY,
            'payload' => ['message' => 'Mapped parser bridge and output generator hotspots.'],
            'event_ts' => CarbonImmutable::now('UTC'),
        ]);

        InterrogationEvent::query()->create([
            'interrogation_session_id' => $session->id,
            'sequence' => 2,
            'event_type' => InterrogationEvent::TYPE_DISCOVERY_ACTIVITY,
            'payload' => ['message' => 'Identified fixture parity failures in output snapshot tests.'],
            'event_ts' => CarbonImmutable::now('UTC')->addSecond(),
        ]);

        $resolver = new SystemPromptResolver;
        $prompt = $resolver->resolveForPhase($session, 'interrogation');

        $this->assertStringContainsString('Recent Discovery Findings:', $prompt);
        $this->assertStringContainsString('Mapped parser bridge and output generator hotspots.', $prompt);
        $this->assertStringContainsString('Identified fixture parity failures in output snapshot tests.', $prompt);
    }

    public function test_planning_prompt_explicitly_forbids_estimates_and_timelines(): void
    {
        $user = User::factory()->create();

        $session = InterrogationSession::query()->create([
            'user_id' => $user->id,
            'runner_type' => 'claude',
            'project_directory' => base_path(),
            'interrogation_type' => InterrogationSession::TYPE_GENERAL,
            'status' => InterrogationSession::STATUS_SETUP,
            'phase' => InterrogationSession::PHASE_SETUP,
        ]);

        $resolver = new SystemPromptResolver;
        $prompt = $resolver->resolveForPhase($session, 'planning');

        $this->assertStringContainsString('Never include estimates or timeline projections', $prompt);
        $this->assertStringContainsString('critical path', $prompt);
    }

    public function test_summary_prompt_explicitly_forbids_estimates_and_timelines(): void
    {
        $user = User::factory()->create();

        $session = InterrogationSession::query()->create([
            'user_id' => $user->id,
            'runner_type' => 'claude',
            'project_directory' => base_path(),
            'interrogation_type' => InterrogationSession::TYPE_GENERAL,
            'status' => InterrogationSession::STATUS_SETUP,
            'phase' => InterrogationSession::PHASE_SETUP,
        ]);

        $resolver = new SystemPromptResolver;
        $prompt = $resolver->resolveForPhase($session, 'summary');

        $this->assertStringContainsString('Never include estimates or timeline projections', $prompt);
        $this->assertStringContainsString('critical path', $prompt);
    }

    public function test_codex_interrogation_prompt_includes_parity_instructions(): void
    {
        $user = User::factory()->create();

        $session = InterrogationSession::query()->create([
            'user_id' => $user->id,
            'runner_type' => 'codex',
            'project_directory' => base_path(),
            'interrogation_type' => InterrogationSession::TYPE_FEATURE,
            'status' => InterrogationSession::STATUS_SETUP,
            'phase' => InterrogationSession::PHASE_SETUP,
        ]);

        $resolver = new SystemPromptResolver;
        $prompt = $resolver->resolveForPhase($session, 'interrogation');

        $this->assertStringContainsString('Codex parity rules', $prompt);
        $this->assertStringContainsString('Prefer answer_type="choice" with 3-5 concrete options', $prompt);
        $this->assertStringContainsString('Do not set is_complete=true until ambiguity is materially closed', $prompt);
    }

    public function test_claude_interrogation_prompt_excludes_codex_parity_instructions(): void
    {
        $user = User::factory()->create();

        $session = InterrogationSession::query()->create([
            'user_id' => $user->id,
            'runner_type' => 'claude',
            'project_directory' => base_path(),
            'interrogation_type' => InterrogationSession::TYPE_FEATURE,
            'status' => InterrogationSession::STATUS_SETUP,
            'phase' => InterrogationSession::PHASE_SETUP,
        ]);

        $resolver = new SystemPromptResolver;
        $prompt = $resolver->resolveForPhase($session, 'interrogation');

        $this->assertStringNotContainsString('Codex parity rules', $prompt);
    }

    public function test_build_tasks_prompt_includes_test_first_and_code_field_rules(): void
    {
        $user = User::factory()->create();

        $session = InterrogationSession::query()->create([
            'user_id' => $user->id,
            'runner_type' => 'codex',
            'project_directory' => base_path(),
            'interrogation_type' => InterrogationSession::TYPE_FEATURE,
            'status' => InterrogationSession::STATUS_BUILD_TASKS,
            'phase' => InterrogationSession::PHASE_BUILD_TASKS,
        ]);

        $resolver = new SystemPromptResolver;
        $prompt = $resolver->resolveForPhase($session, 'build_tasks');

        $this->assertStringContainsString('build task generation', $prompt);
        $this->assertStringContainsString('tests first', strtolower($prompt));
        $this->assertStringContainsString('code field', strtolower($prompt));
        $this->assertStringContainsString('assumptions', strtolower($prompt));
        $this->assertStringContainsString('happy path', strtolower($prompt));
    }
}
