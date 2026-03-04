<?php

namespace Tests\Unit;

use App\Models\InterrogationSession;
use App\Models\InterrogationBuildTask;
use App\Models\User;
use App\Support\Interrogation\AdapterFactory;
use App\Support\Interrogation\BuildTaskGenerator;
use App\Support\Interrogation\SystemPromptResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use Tests\TestCase;

class BuildTaskGeneratorPromptTest extends TestCase
{
    use RefreshDatabase;

    public function test_build_prompt_includes_test_first_code_field_and_project_rules_context(): void
    {
        $user = User::factory()->create();

        $session = InterrogationSession::query()->create([
            'user_id' => $user->id,
            'runner_type' => 'codex',
            'project_directory' => base_path(),
            'interrogation_type' => InterrogationSession::TYPE_FEATURE,
            'status' => InterrogationSession::STATUS_BUILD_TASKS,
            'phase' => InterrogationSession::PHASE_BUILD_TASKS,
            'summary_json' => [
                'summary_markdown' => 'Summary markdown.',
            ],
            'plan_json' => [
                'plan_markdown' => 'Plan markdown.',
                'sections' => ['Section A'],
            ],
            'metadata_json' => [
                'build' => [
                    'project_rules' => [
                        [
                            'title' => 'Security Rule',
                            'markdown' => 'Validate all user input.',
                            'source' => 'manual',
                        ],
                    ],
                ],
            ],
        ]);

        $generator = new BuildTaskGenerator(
            $this->mock(AdapterFactory::class),
            $this->mock(SystemPromptResolver::class),
        );

        $method = new ReflectionMethod($generator, 'buildPrompt');
        $method->setAccessible(true);
        $prompt = (string) $method->invoke($generator, $session, null);

        $this->assertStringContainsString('tests first', strtolower($prompt));
        $this->assertStringContainsString('code field', strtolower($prompt));
        $this->assertStringContainsString('state assumptions', strtolower($prompt));
        $this->assertStringContainsString('Security Rule', $prompt);
        $this->assertStringContainsString('Validate all user input.', $prompt);
    }

    public function test_task_regeneration_prompt_includes_additional_context_when_provided(): void
    {
        $user = User::factory()->create();

        $session = InterrogationSession::query()->create([
            'user_id' => $user->id,
            'runner_type' => 'codex',
            'project_directory' => base_path(),
            'interrogation_type' => InterrogationSession::TYPE_FEATURE,
            'status' => InterrogationSession::STATUS_BUILD_TASKS,
            'phase' => InterrogationSession::PHASE_BUILD_TASKS,
            'summary_json' => [
                'summary_markdown' => 'Summary markdown.',
            ],
            'plan_json' => [
                'plan_markdown' => 'Plan markdown.',
            ],
        ]);

        $task = InterrogationBuildTask::query()->create([
            'interrogation_session_id' => (int) $session->id,
            'sequence' => 1,
            'title' => 'Original task',
            'description' => 'Original description',
            'instructions_markdown' => 'Original instructions',
            'status' => InterrogationBuildTask::STATUS_PENDING,
        ]);

        $generator = new BuildTaskGenerator(
            $this->mock(AdapterFactory::class),
            $this->mock(SystemPromptResolver::class),
        );

        $method = new ReflectionMethod($generator, 'buildTaskRegenerationPrompt');
        $method->setAccessible(true);
        $prompt = (string) $method->invoke(
            $generator,
            $session,
            $task,
            'Update this task to include API contract validation.',
            'Ensure backward compatibility with the existing response payload keys.',
        );

        $this->assertStringContainsString('Update this task to include API contract validation.', $prompt);
        $this->assertStringContainsString('Additional user context', $prompt);
        $this->assertStringContainsString('Ensure backward compatibility with the existing response payload keys.', $prompt);
    }
}
