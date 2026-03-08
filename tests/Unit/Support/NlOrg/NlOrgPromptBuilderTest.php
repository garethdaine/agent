<?php

namespace Tests\Unit\Support\NlOrg;

use App\Support\NlOrg\Exceptions\NlOrgContextOverflowException;
use App\Support\NlOrg\NlOrgPromptBuilder;
use Tests\TestCase;

class NlOrgPromptBuilderTest extends TestCase
{
    private NlOrgPromptBuilder $builder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->builder = new NlOrgPromptBuilder;
    }

    public function test_build_output_contains_org_agent_profile_schema_definition(): void
    {
        $prompt = $this->builder->build('Create a lead agent.', []);

        $this->assertStringContainsString('org_agent_profile', $prompt);
    }

    public function test_build_output_contains_all_five_entity_type_descriptions(): void
    {
        $prompt = $this->builder->build('Create a team.', []);

        $this->assertStringContainsString('org_agent_profile', $prompt);
        $this->assertStringContainsString('org_reporting_edge', $prompt);
        $this->assertStringContainsString('org_ritual_template', $prompt);
        $this->assertStringContainsString('org_council_template', $prompt);
        $this->assertStringContainsString('org_cost_ledger', $prompt);
    }

    public function test_build_output_includes_serialized_org_state_when_agents_exist(): void
    {
        $orgState = [
            'agents' => [
                ['id' => 'agent-1', 'name' => 'Alpha Lead', 'role_slug' => 'lead'],
                ['id' => 'agent-2', 'name' => 'Beta Dev', 'role_slug' => 'developer'],
            ],
            'edges' => [
                ['subordinate_agent_id' => 'agent-2', 'manager_agent_id' => 'agent-1'],
            ],
        ];

        $prompt = $this->builder->build('Update the team.', $orgState);

        $this->assertStringContainsString('Alpha Lead', $prompt);
        $this->assertStringContainsString('Beta Dev', $prompt);
        $this->assertStringContainsString('agent-1', $prompt);
        $this->assertStringContainsString('agent-2', $prompt);
    }

    public function test_build_appends_chat_history_turns_when_provided(): void
    {
        $chatHistory = [
            ['role' => 'user', 'content' => 'Create a lead agent.'],
            ['role' => 'assistant', 'content' => 'Created 1 agent: Lead Agent.'],
        ];

        $prompt = $this->builder->build('Now add a developer.', [], $chatHistory);

        $this->assertStringContainsString('Create a lead agent.', $prompt);
        $this->assertStringContainsString('Created 1 agent: Lead Agent.', $prompt);
    }

    public function test_build_truncates_chat_history_to_max_turns(): void
    {
        config()->set('agent.nl_org.max_chat_history_turns', 10);

        // Create 15 individual turns (entries), config limits to last 10
        $chatHistory = [];
        for ($i = 1; $i <= 15; $i++) {
            $chatHistory[] = ['role' => ($i % 2 === 1) ? 'user' : 'assistant', 'content' => "Turn {$i} message."];
        }

        $prompt = $this->builder->build('Final command.', [], $chatHistory);

        // First 5 turns should be absent, last 10 turns (6-15) present
        $this->assertStringNotContainsString('Turn 1 message', $prompt);
        $this->assertStringNotContainsString('Turn 5 message', $prompt);
        $this->assertStringContainsString('Turn 6 message', $prompt);
        $this->assertStringContainsString('Turn 15 message', $prompt);
    }

    public function test_build_includes_missing_participants_instruction(): void
    {
        $prompt = $this->builder->build('Create a ritual.', []);

        $this->assertStringContainsString('missing_participants', $prompt);
        $this->assertStringContainsString('Do not infer participants', $prompt);
    }

    public function test_estimate_token_count_returns_strlen_divided_by_four(): void
    {
        // 100 chars -> 25 tokens
        $text = str_repeat('a', 100);
        $this->assertEquals(25, $this->builder->estimateTokenCount($text));

        // 101 chars -> ceil(101/4) = 26 tokens
        $text = str_repeat('b', 101);
        $this->assertEquals(26, $this->builder->estimateTokenCount($text));

        // empty -> 0 tokens
        $this->assertEquals(0, $this->builder->estimateTokenCount(''));
    }

    public function test_build_throws_overflow_exception_when_prompt_exceeds_budget(): void
    {
        // Set an artificially low context window so the prompt exceeds budget
        config()->set('memory.context_injection.default_context_window', 100);

        $this->expectException(NlOrgContextOverflowException::class);

        $this->builder->build('Create a massive org.', []);
    }

    public function test_overflow_exception_carries_estimated_and_budget_tokens(): void
    {
        config()->set('memory.context_injection.default_context_window', 100);

        try {
            $this->builder->build('Create an org.', []);
            $this->fail('Expected NlOrgContextOverflowException was not thrown.');
        } catch (NlOrgContextOverflowException $e) {
            $this->assertIsInt($e->estimatedTokens);
            $this->assertIsInt($e->budgetTokens);
            $this->assertGreaterThan($e->budgetTokens, $e->estimatedTokens);
            $this->assertEquals((int) (100 * 0.8), $e->budgetTokens);
        }
    }

    public function test_build_with_empty_org_state_produces_valid_prompt(): void
    {
        $prompt = $this->builder->build('Start from scratch.', []);

        // Should still contain schema and instructions, just empty state
        $this->assertStringContainsString('org_agent_profile', $prompt);
        $this->assertNotEmpty($prompt);
    }

    public function test_build_omits_chat_history_section_when_empty(): void
    {
        $prompt = $this->builder->build('Create a team.', [], []);

        // The prompt should not contain a chat history header when no history
        $this->assertStringNotContainsString('## Chat History', $prompt);
    }

    public function test_build_includes_few_shot_examples(): void
    {
        $prompt = $this->builder->build('Create a team.', []);

        // Should contain at least one few-shot example marker
        $this->assertStringContainsString('Example', $prompt);
    }

    public function test_build_contains_phase_graph_description(): void
    {
        $prompt = $this->builder->build('Create a ritual.', []);

        $this->assertStringContainsString('phase_graph', $prompt);
    }

    public function test_build_includes_user_input(): void
    {
        $prompt = $this->builder->build('Add a security reviewer agent.', []);

        $this->assertStringContainsString('Add a security reviewer agent.', $prompt);
    }
}
