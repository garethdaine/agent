<?php

namespace Tests\Feature\NlOrg;

use App\Models\NlOrgParseAttempt;
use App\Models\User;
use App\Support\NlOrg\NlOrgDiffApplier;
use App\Support\NlOrg\NlOrgParseResult;
use App\Support\NlOrg\NlOrgParserService;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class NlOrgControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['agent.org.enabled' => true]);
    }

    private function fakeLlmResponse(array $payload = []): void
    {
        $default = [
            'confidence' => 0.85,
            'changeset' => [
                [
                    'entity_type' => NlOrgParseResult::TYPE_AGENT_PROFILE,
                    'operation' => NlOrgParseResult::OP_ADD,
                    'payload' => ['name' => 'Test Agent', 'role_slug' => 'developer', 'role_description' => 'A developer'],
                    'temp_id' => 'new_agent_1',
                ],
            ],
            'annotations' => [],
            'resolved_references' => [],
        ];

        $body = array_merge($default, $payload);

        Http::fake([
            '*/messages' => Http::response([
                'content' => [
                    ['type' => 'text', 'text' => json_encode($body)],
                ],
            ]),
        ]);
    }

    // ----------------------------------------------------------------
    // POST /org/nl-parse tests
    // ----------------------------------------------------------------

    public function test_nl_parse_success(): void
    {
        $this->fakeLlmResponse();
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson('/agent/api/v1/org/nl-parse', [
                'input' => 'Create a developer agent',
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'parse_attempt_id',
                'changeset',
                'annotations',
                'confidence',
                'resolved_references',
            ]);

        $this->assertNotNull($response->json('parse_attempt_id'));
        $this->assertIsArray($response->json('changeset'));
        $this->assertIsFloat($response->json('confidence') + 0.0);
    }

    public function test_nl_parse_creates_parse_attempt_record(): void
    {
        $this->fakeLlmResponse();
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson('/agent/api/v1/org/nl-parse', [
                'input' => 'Create a developer agent',
            ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('nl_org_parse_attempts', [
            'user_id' => $user->id,
            'raw_input' => 'Create a developer agent',
        ]);

        $attempt = NlOrgParseAttempt::where('user_id', $user->id)->first();
        $this->assertNull($attempt->applied_at);
        $this->assertNotNull($attempt->parsed_result);
    }

    public function test_nl_parse_rejects_input_exceeding_max_length(): void
    {
        $user = User::factory()->create();
        $longInput = str_repeat('a', 2001);

        $response = $this->actingAs($user)
            ->postJson('/agent/api/v1/org/nl-parse', [
                'input' => $longInput,
            ]);

        $response->assertStatus(422);
    }

    public function test_nl_parse_requires_authentication(): void
    {
        $response = $this->postJson('/agent/api/v1/org/nl-parse', [
            'input' => 'Create a developer agent',
        ]);

        $response->assertStatus(401);
    }

    public function test_nl_parse_accepts_chat_history(): void
    {
        $this->fakeLlmResponse();
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson('/agent/api/v1/org/nl-parse', [
                'input' => 'Make that agent senior level',
                'chat_history' => [
                    ['role' => 'user', 'content' => 'Create a developer agent'],
                    ['role' => 'assistant', 'content' => 'Created 1 agent profile.'],
                ],
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['parse_attempt_id', 'changeset']);
    }

    // ----------------------------------------------------------------
    // POST /org/nl-apply tests
    // ----------------------------------------------------------------

    public function test_nl_apply_success(): void
    {
        $user = User::factory()->create();

        $attempt = NlOrgParseAttempt::create([
            'user_id' => $user->id,
            'raw_input' => 'Create a developer agent',
            'parsed_result' => [
                'confidence' => 0.85,
                'changeset' => [],
                'annotations' => [],
                'resolved_references' => [],
                'error' => null,
            ],
            'confidence' => 0.85,
            'applied_at' => null,
        ]);

        // Mock the applier so we don't need real org services
        $this->mock(NlOrgDiffApplier::class, function ($mock) {
            $mock->shouldReceive('apply')->once();
        });

        $response = $this->actingAs($user)
            ->postJson('/agent/api/v1/org/nl-apply', [
                'parse_attempt_id' => $attempt->id,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $attempt->refresh();
        $this->assertNotNull($attempt->applied_at);
    }

    public function test_nl_apply_rejects_wrong_user(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        $attempt = NlOrgParseAttempt::create([
            'user_id' => $owner->id,
            'raw_input' => 'Create a developer agent',
            'parsed_result' => [
                'confidence' => 0.85,
                'changeset' => [],
                'annotations' => [],
                'resolved_references' => [],
                'error' => null,
            ],
            'confidence' => 0.85,
            'applied_at' => null,
        ]);

        $response = $this->actingAs($other)
            ->postJson('/agent/api/v1/org/nl-apply', [
                'parse_attempt_id' => $attempt->id,
            ]);

        $response->assertStatus(403);
    }

    public function test_nl_apply_rejects_already_applied(): void
    {
        $user = User::factory()->create();

        $attempt = NlOrgParseAttempt::create([
            'user_id' => $user->id,
            'raw_input' => 'Create a developer agent',
            'parsed_result' => [
                'confidence' => 0.85,
                'changeset' => [],
                'annotations' => [],
                'resolved_references' => [],
                'error' => null,
            ],
            'confidence' => 0.85,
            'applied_at' => now(),
        ]);

        $response = $this->actingAs($user)
            ->postJson('/agent/api/v1/org/nl-apply', [
                'parse_attempt_id' => $attempt->id,
            ]);

        $response->assertStatus(422);
    }

    public function test_nl_apply_rejects_nonexistent_id(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson('/agent/api/v1/org/nl-apply', [
                'parse_attempt_id' => '00000000-0000-0000-0000-000000000000',
            ]);

        $response->assertStatus(422);
    }

    public function test_nl_apply_transaction_failure_leaves_attempt_unapplied(): void
    {
        $user = User::factory()->create();

        $attempt = NlOrgParseAttempt::create([
            'user_id' => $user->id,
            'raw_input' => 'Create a developer agent',
            'parsed_result' => [
                'confidence' => 0.85,
                'changeset' => [
                    [
                        'entity_type' => NlOrgParseResult::TYPE_AGENT_PROFILE,
                        'operation' => NlOrgParseResult::OP_ADD,
                        'payload' => ['name' => 'Test Agent', 'role_slug' => 'dev'],
                        'temp_id' => 'new_1',
                    ],
                ],
                'annotations' => [],
                'resolved_references' => [],
                'error' => null,
            ],
            'confidence' => 0.85,
            'applied_at' => null,
        ]);

        // Mock the applier to throw an exception
        $this->mock(NlOrgDiffApplier::class, function ($mock) {
            $mock->shouldReceive('apply')->once()->andThrow(new RuntimeException('DB failure'));
        });

        $response = $this->actingAs($user)
            ->postJson('/agent/api/v1/org/nl-apply', [
                'parse_attempt_id' => $attempt->id,
            ]);

        $response->assertStatus(422);

        $attempt->refresh();
        $this->assertNull($attempt->applied_at);
    }
}
