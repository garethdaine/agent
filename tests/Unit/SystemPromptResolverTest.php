<?php

namespace Tests\Unit;

use App\Models\InterrogationSession;
use App\Models\InterrogationSetting;
use App\Models\User;
use App\Support\Interrogation\SystemPromptResolver;
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
}
