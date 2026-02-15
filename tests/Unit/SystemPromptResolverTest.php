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
}
