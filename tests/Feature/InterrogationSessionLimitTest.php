<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\InterrogationSession;
use App\Models\InterrogationSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class InterrogationSessionLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_config_limit_blocks_fourth_active_session(): void
    {
        config()->set('agent.interrogation.max_active_sessions', 3);
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->seedActiveSessions($user->id, 3);

        $response = $this->postJson('/agent/api/v1/interrogation/sessions', $this->payload())
            ->assertStatus(422);

        $this->assertValidationMessage(
            $response,
            'runner_type',
            'You already have 3 active interrogation sessions.'
        );
    }

    public function test_user_setting_override_allows_creation_above_default_limit(): void
    {
        config()->set('agent.interrogation.max_active_sessions', 3);
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->seedActiveSessions($user->id, 3);
        InterrogationSetting::setForUser((int) $user->id, 'interrogation.max_active_sessions', 4);

        $this->postJson('/agent/api/v1/interrogation/sessions', $this->payload())
            ->assertStatus(202);
    }

    public function test_user_setting_override_can_lower_limit(): void
    {
        config()->set('agent.interrogation.max_active_sessions', 5);
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->seedActiveSessions($user->id, 2);
        InterrogationSetting::setForUser((int) $user->id, 'interrogation.max_active_sessions', 2);

        $response = $this->postJson('/agent/api/v1/interrogation/sessions', $this->payload())
            ->assertStatus(422);

        $this->assertValidationMessage(
            $response,
            'runner_type',
            'You already have 2 active interrogation sessions.'
        );
    }

    public function test_max_active_sessions_setting_validates_integer_range(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->putJson('/agent/api/v1/interrogation/settings/interrogation.max_active_sessions', [
            'value' => 0,
        ])->assertStatus(422);
        $this->assertValidationMessage($response, 'value');

        $response = $this->putJson('/agent/api/v1/interrogation/settings/interrogation.max_active_sessions', [
            'value' => 51,
        ])->assertStatus(422);
        $this->assertValidationMessage($response, 'value');

        $this->putJson('/agent/api/v1/interrogation/settings/interrogation.max_active_sessions', [
            'value' => 4,
        ])->assertOk()
            ->assertJsonPath('data.value', 4);
    }

    public function test_system_prompt_setting_allows_empty_value_to_reset_override(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->putJson('/agent/api/v1/interrogation/settings/interrogation.system_prompt', [
            'value' => '',
        ])->assertOk();
    }

    public function test_default_runner_setting_rejects_unknown_runner_values(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->putJson('/agent/api/v1/interrogation/settings/interrogation.default_runner', [
            'value' => 'gpt',
        ])->assertStatus(422);

        $this->assertValidationMessage($response, 'value');
    }

    private function seedActiveSessions(int $userId, int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            InterrogationSession::query()->create([
                'user_id' => $userId,
                'name' => 'Session '.$i,
                'runner_type' => 'claude',
                'project_directory' => base_path(),
                'interrogation_type' => InterrogationSession::TYPE_FEATURE,
                'feature_brief' => 'Seeded session',
                'status' => InterrogationSession::STATUS_SETUP,
                'phase' => InterrogationSession::PHASE_SETUP,
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(): array
    {
        return [
            'name' => 'New Session',
            'runner_type' => 'claude',
            'project_directory' => base_path(),
            'interrogation_type' => InterrogationSession::TYPE_FEATURE,
            'feature_brief' => 'Feature discovery scope.',
        ];
    }

    private function assertValidationMessage(TestResponse $response, string $field, ?string $expected = null): void
    {
        $details = $response->json('errors');
        if (! is_array($details)) {
            $details = $response->json('error.details');
        }

        $this->assertIsArray($details);
        $actual = data_get($details, $field.'.0');
        $this->assertNotNull($actual);

        if ($expected !== null) {
            $this->assertSame($expected, $actual);
        }
    }
}
