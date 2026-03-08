<?php

namespace Tests\Unit\Support\NlOrg;

use App\Models\OrgAgentProfile;
use App\Models\User;
use App\Support\NlOrg\Exceptions\NlOrgContextOverflowException;
use App\Support\NlOrg\NlOrgParseResult;
use App\Support\NlOrg\NlOrgParserService;
use App\Support\NlOrg\NlOrgPromptBuilder;
use App\Support\Org\OrgAgentProfileService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Tests\TestCase;

class NlOrgParserServiceTest extends TestCase
{
    use RefreshDatabase;

    private NlOrgParserService $service;

    private NlOrgPromptBuilder $promptBuilder;

    private OrgAgentProfileService $profileService;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->promptBuilder = $this->app->make(NlOrgPromptBuilder::class);
        $this->profileService = $this->app->make(OrgAgentProfileService::class);
        $credentialsManager = $this->app->make(\App\Services\Credentials\CredentialsManager::class);
        $this->service = new NlOrgParserService($this->promptBuilder, $this->profileService, $credentialsManager);

        // Set a fake API key so callLlm() doesn't short-circuit before reaching Http::fake
        config(['runtime.llm.anthropic.api_key' => 'test-fake-key']);
    }

    /**
     * Helper to fake the Anthropic messages API with a given changeset response.
     */
    private function fakeLlmResponse(array $responseBody): void
    {
        Http::fake([
            '*/messages' => Http::response([
                'content' => [
                    ['type' => 'text', 'text' => json_encode($responseBody)],
                ],
            ]),
        ]);
    }

    // ---------------------------------------------------------------
    // Fixture 1: Holistic creation
    // ---------------------------------------------------------------
    public function test_holistic_creation_returns_all_entities_as_op_add(): void
    {
        $llmResponse = [
            'confidence' => 0.95,
            'changeset' => [
                ['entity_type' => 'org_agent_profile', 'operation' => 'add', 'payload' => ['name' => 'Lead', 'role_slug' => 'lead'], 'temp_id' => 'new_lead'],
                ['entity_type' => 'org_agent_profile', 'operation' => 'add', 'payload' => ['name' => 'Dev 1', 'role_slug' => 'developer'], 'temp_id' => 'new_dev1'],
                ['entity_type' => 'org_agent_profile', 'operation' => 'add', 'payload' => ['name' => 'Dev 2', 'role_slug' => 'developer'], 'temp_id' => 'new_dev2'],
                ['entity_type' => 'org_reporting_edge', 'operation' => 'add', 'payload' => ['subordinate_agent_id' => 'new_dev1', 'manager_agent_id' => 'new_lead']],
                ['entity_type' => 'org_reporting_edge', 'operation' => 'add', 'payload' => ['subordinate_agent_id' => 'new_dev2', 'manager_agent_id' => 'new_lead']],
            ],
            'annotations' => [],
            'resolved_references' => [],
        ];

        $this->fakeLlmResponse($llmResponse);

        $result = $this->service->parse(
            $this->user,
            'Create a team with a lead and two developers. Devs report to lead.'
        );

        $this->assertInstanceOf(NlOrgParseResult::class, $result);
        $this->assertFalse($result->hasError());
        $this->assertCount(5, $result->changeset);

        foreach ($result->changeset as $entry) {
            $this->assertEquals(NlOrgParseResult::OP_ADD, $entry['operation']);
        }
    }

    // ---------------------------------------------------------------
    // Fixture 2: Incremental add
    // ---------------------------------------------------------------
    public function test_incremental_add_returns_only_delta_entities(): void
    {
        // Seed 2 existing agents
        OrgAgentProfile::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Agent Alpha',
            'role_slug' => 'alpha',
        ]);
        OrgAgentProfile::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Agent Beta',
            'role_slug' => 'beta',
        ]);

        $llmResponse = [
            'confidence' => 0.9,
            'changeset' => [
                ['entity_type' => 'org_agent_profile', 'operation' => 'add', 'payload' => ['name' => 'Security Reviewer', 'role_slug' => 'security-reviewer'], 'temp_id' => 'new_sec'],
                ['entity_type' => 'org_reporting_edge', 'operation' => 'add', 'payload' => ['subordinate_agent_id' => 'new_sec', 'manager_agent_id' => 'uuid-alpha']],
            ],
            'annotations' => [],
            'resolved_references' => ['Agent Alpha' => 'uuid-alpha'],
        ];

        $this->fakeLlmResponse($llmResponse);

        $result = $this->service->parse(
            $this->user,
            'Add a security reviewer reporting to Agent Alpha.'
        );

        $this->assertFalse($result->hasError());
        $this->assertCount(2, $result->changeset);
    }

    // ---------------------------------------------------------------
    // Fixture 3: Incremental remove
    // ---------------------------------------------------------------
    public function test_incremental_remove_returns_op_remove(): void
    {
        OrgAgentProfile::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Agent Beta',
            'role_slug' => 'beta',
        ]);

        $llmResponse = [
            'confidence' => 0.85,
            'changeset' => [
                ['entity_type' => 'org_cost_ledger', 'operation' => 'remove', 'payload' => ['org_agent_id' => 'uuid-beta']],
            ],
            'annotations' => [],
            'resolved_references' => ['Agent Beta' => 'uuid-beta'],
        ];

        $this->fakeLlmResponse($llmResponse);

        $result = $this->service->parse(
            $this->user,
            'Remove the cost ledger from Agent Beta.'
        );

        $this->assertFalse($result->hasError());
        $this->assertCount(1, $result->changeset);
        $this->assertEquals(NlOrgParseResult::OP_REMOVE, $result->changeset[0]['operation']);
    }

    // ---------------------------------------------------------------
    // Fixture 4: Entity reference resolution
    // ---------------------------------------------------------------
    public function test_entity_reference_resolves_to_seeded_profile_id(): void
    {
        $qaAgent = OrgAgentProfile::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'QA Agent',
            'role_slug' => 'qa',
        ]);

        $llmResponse = [
            'confidence' => 0.9,
            'changeset' => [
                ['entity_type' => 'org_agent_profile', 'operation' => 'add', 'payload' => ['name' => 'Testing Lead', 'role_slug' => 'testing-lead'], 'temp_id' => 'new_tl'],
            ],
            'annotations' => [],
            'resolved_references' => ['QA Agent' => 'placeholder'],
        ];

        $this->fakeLlmResponse($llmResponse);

        $result = $this->service->parse(
            $this->user,
            'Use the QA Agent as basis for a testing lead.'
        );

        $this->assertFalse($result->hasError());
        $this->assertArrayHasKey('QA Agent', $result->resolvedReferences);
        $this->assertEquals($qaAgent->id, $result->resolvedReferences['QA Agent']);
    }

    // ---------------------------------------------------------------
    // Fixture 5: Ambiguous input
    // ---------------------------------------------------------------
    public function test_ambiguous_input_includes_ambiguity_annotation(): void
    {
        $llmResponse = [
            'confidence' => 0.5,
            'changeset' => [
                ['entity_type' => 'org_agent_profile', 'operation' => 'add', 'payload' => ['name' => 'Reviewer/Auditor', 'role_slug' => 'reviewer']],
            ],
            'annotations' => [
                ['message' => 'Ambiguous agent name: could be Reviewer or Auditor.', 'type' => 'ambiguity', 'char_offset_start' => null, 'char_offset_end' => null, 'suggestion' => 'Specify a single name.'],
            ],
            'resolved_references' => [],
        ];

        $this->fakeLlmResponse($llmResponse);

        $result = $this->service->parse(
            $this->user,
            'Add an agent called either Reviewer or Auditor.'
        );

        $this->assertFalse($result->hasError());
        $ambiguities = array_filter($result->annotations, fn ($a) => $a['type'] === 'ambiguity');
        $this->assertNotEmpty($ambiguities);
        $first = array_values($ambiguities)[0];
        $this->assertArrayHasKey('suggestion', $first);
    }

    // ---------------------------------------------------------------
    // Fixture 6: Ritual without participants
    // ---------------------------------------------------------------
    public function test_ritual_without_participants_is_withheld_with_annotation(): void
    {
        $llmResponse = [
            'confidence' => 0.7,
            'changeset' => [
                ['entity_type' => 'org_ritual_template', 'operation' => 'add', 'payload' => ['name' => 'Daily Standup', 'phase_graph' => ['phases' => []], 'phase_role_mappings' => []]],
            ],
            'annotations' => [],
            'resolved_references' => [],
        ];

        $this->fakeLlmResponse($llmResponse);

        $result = $this->service->parse(
            $this->user,
            'Add a daily standup ritual.'
        );

        // Ritual should be withheld from changeset
        $rituals = $result->getChangesetByEntityType(NlOrgParseResult::TYPE_RITUAL_TEMPLATE);
        $this->assertEmpty($rituals);

        // Missing participants annotation should be present
        $missingParticipants = array_filter($result->annotations, fn ($a) => $a['type'] === 'missing_participants');
        $this->assertNotEmpty($missingParticipants);
    }

    // ---------------------------------------------------------------
    // Fixture 7: Context overflow
    // ---------------------------------------------------------------
    public function test_context_overflow_returns_error_result(): void
    {
        // Use a mock PromptBuilder that throws overflow
        $mockBuilder = $this->createMock(NlOrgPromptBuilder::class);
        $mockBuilder->method('build')
            ->willThrowException(new NlOrgContextOverflowException(500000, 160000));

        $credentialsManager = $this->app->make(\App\Services\Credentials\CredentialsManager::class);
        $service = new NlOrgParserService($mockBuilder, $this->profileService, $credentialsManager);

        $result = $service->parse($this->user, 'Create a massive org.');

        $this->assertTrue($result->hasError());
        $this->assertStringContainsString('incremental', strtolower($result->error));
    }

    // ---------------------------------------------------------------
    // Fixture 8: Conversational follow-up
    // ---------------------------------------------------------------
    public function test_conversational_followup_produces_op_update(): void
    {
        $llmResponse = [
            'confidence' => 0.85,
            'changeset' => [
                ['entity_type' => 'org_agent_profile', 'operation' => 'update', 'payload' => ['id' => 'existing-agent-id', 'role_slug' => 'senior-developer']],
            ],
            'annotations' => [],
            'resolved_references' => [],
        ];

        $this->fakeLlmResponse($llmResponse);

        $chatHistory = [
            ['role' => 'user', 'content' => 'Create a developer agent.'],
            ['role' => 'assistant', 'content' => 'Created 1 agent: Developer (id: existing-agent-id).'],
        ];

        $result = $this->service->parse(
            $this->user,
            'Make that agent senior level.',
            $chatHistory
        );

        $this->assertFalse($result->hasError());
        $this->assertCount(1, $result->changeset);
        $this->assertEquals(NlOrgParseResult::OP_UPDATE, $result->changeset[0]['operation']);
    }

    // ---------------------------------------------------------------
    // Fixture 9: Mixed complete/incomplete
    // ---------------------------------------------------------------
    public function test_mixed_complete_and_incomplete_entities(): void
    {
        $llmResponse = [
            'confidence' => 0.8,
            'changeset' => [
                ['entity_type' => 'org_agent_profile', 'operation' => 'add', 'payload' => ['name' => 'Lead Agent', 'role_slug' => 'lead'], 'temp_id' => 'new_lead'],
                ['entity_type' => 'org_reporting_edge', 'operation' => 'add', 'payload' => ['subordinate_agent_id' => 'new_dev', 'manager_agent_id' => 'new_lead']],
                ['entity_type' => 'org_ritual_template', 'operation' => 'add', 'payload' => ['name' => 'Daily Standup', 'phase_graph' => [], 'phase_role_mappings' => []]],
            ],
            'annotations' => [],
            'resolved_references' => [],
        ];

        $this->fakeLlmResponse($llmResponse);

        $result = $this->service->parse(
            $this->user,
            'Add a lead agent, a daily standup ritual, and a reporting edge from dev to lead.'
        );

        // Agent and edge should be in changeset
        $agents = $result->getChangesetByEntityType(NlOrgParseResult::TYPE_AGENT_PROFILE);
        $edges = $result->getChangesetByEntityType(NlOrgParseResult::TYPE_REPORTING_EDGE);
        $this->assertNotEmpty($agents);
        $this->assertNotEmpty($edges);

        // Ritual should be withheld
        $rituals = $result->getChangesetByEntityType(NlOrgParseResult::TYPE_RITUAL_TEMPLATE);
        $this->assertEmpty($rituals);

        // Missing participants annotation
        $missingParticipants = array_filter($result->annotations, fn ($a) => $a['type'] === 'missing_participants');
        $this->assertNotEmpty($missingParticipants);
    }

    // ---------------------------------------------------------------
    // Fixture 10: Input exceeds max length
    // ---------------------------------------------------------------
    public function test_input_exceeding_max_length_throws_exception(): void
    {
        config()->set('agent.nl_org.max_input_length', 100);

        $this->expectException(InvalidArgumentException::class);

        $this->service->parse($this->user, str_repeat('a', 101));
    }

    // ---------------------------------------------------------------
    // Fixture 11: Malformed LLM response
    // ---------------------------------------------------------------
    public function test_malformed_llm_response_returns_error_annotation(): void
    {
        Http::fake([
            '*/messages' => Http::response([
                'content' => [
                    ['type' => 'text', 'text' => 'This is not valid JSON {{{'],
                ],
            ]),
        ]);

        $result = $this->service->parse(
            $this->user,
            'Create a team.'
        );

        $this->assertTrue($result->hasError());
        $this->assertEmpty($result->changeset);
    }

    // ---------------------------------------------------------------
    // Fixture 12: LLM HTTP error returns error result
    // ---------------------------------------------------------------
    public function test_llm_http_error_returns_error_result(): void
    {
        Http::fake([
            '*/messages' => Http::response(['error' => 'Internal server error'], 500),
        ]);

        $result = $this->service->parse(
            $this->user,
            'Create a team.'
        );

        $this->assertTrue($result->hasError());
        $this->assertEmpty($result->changeset);
    }
}
