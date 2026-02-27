<?php

namespace Tests\Feature\Api;

use App\Models\NlParseAttempt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NlScheduleApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_parse_endpoint_returns_completed_for_high_confidence(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/internal/api/schedule/parse', [
                'input' => 'daily at 9am',
                'timezone' => 'UTC',
            ]);

        $response->assertOk()
            ->assertJsonStructure([
                'status',
                'parse_attempt_id',
                'result' => [
                    'cron_expression',
                    'timezone',
                    'explanation',
                    'confidence',
                    'parser_path',
                    'ambiguous',
                ],
            ])
            ->assertJson(['status' => 'completed']);
    }

    public function test_parse_endpoint_validates_input_length(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/internal/api/schedule/parse', [
                'input' => str_repeat('a', 250),
                'timezone' => 'UTC',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['input']);
    }

    public function test_parse_endpoint_validates_timezone(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/internal/api/schedule/parse', [
                'input' => 'daily at 9am',
                'timezone' => 'Invalid/Timezone',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['timezone']);
    }

    public function test_parse_endpoint_requires_authentication(): void
    {
        $response = $this->postJson('/internal/api/schedule/parse', [
            'input' => 'daily at 9am',
            'timezone' => 'UTC',
        ]);

        $response->assertUnauthorized();
    }

    public function test_show_endpoint_returns_parse_attempt_status(): void
    {
        $attempt = NlParseAttempt::create([
            'user_id' => $this->user->id,
            'input_text' => 'daily at 9am',
            'timezone' => 'UTC',
            'parser_path' => 'rule_based',
            'status' => 'completed',
            'confidence' => 0.95,
            'cron_result' => '0 9 * * *',
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/internal/api/schedule/parse/{$attempt->id}");

        $response->assertOk()
            ->assertJson([
                'status' => 'completed',
                'parse_attempt_id' => $attempt->id,
            ]);
    }

    public function test_show_endpoint_denies_other_users_attempts(): void
    {
        $otherUser = User::factory()->create();
        $attempt = NlParseAttempt::create([
            'user_id' => $otherUser->id,
            'input_text' => 'daily at 9am',
            'timezone' => 'UTC',
            'parser_path' => 'rule_based',
            'status' => 'completed',
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/internal/api/schedule/parse/{$attempt->id}");

        $response->assertForbidden();
    }
}
