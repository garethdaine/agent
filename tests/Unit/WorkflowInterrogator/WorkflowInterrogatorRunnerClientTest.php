<?php

declare(strict_types=1);

namespace Tests\Unit\WorkflowInterrogator;

use App\Support\Interrogation\AdapterFactory;
use App\Support\WorkflowInterrogator\WorkflowInterrogatorPromptBuilder;
use App\Support\WorkflowInterrogator\WorkflowInterrogatorRunnerClient;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class WorkflowInterrogatorRunnerClientTest extends TestCase
{
    public function test_decode_structured_output_handles_stringified_item_text_payloads(): void
    {
        $payload = [
            'questions' => [
                [
                    'question_id' => 'q-1',
                    'prompt' => 'Who owns the workflow?',
                    'answer_type' => 'choice',
                    'options' => ['Ops', 'Finance'],
                    'required' => true,
                    'rationale' => 'Ownership affects approvals.',
                    'category' => 'ownership',
                ],
            ],
            'ambiguity_report' => [
                'needs_another_round' => true,
                'resolved_areas' => [],
                'open_ambiguities' => ['Workflow ownership is still unclear.'],
                'contradictions' => [],
                'coverage_gaps' => ['ownership'],
                'closure_reason' => 'Need one more answer before closure.',
            ],
            'summary' => [],
        ];

        $output = implode("\n", [
            json_encode(['type' => 'session.created', 'session_id' => 'codex-session-1'], JSON_THROW_ON_ERROR),
            json_encode([
                'type' => 'assistant',
                'item' => [
                    'text' => json_encode($payload, JSON_THROW_ON_ERROR),
                ],
            ], JSON_THROW_ON_ERROR),
        ]);

        $decoded = $this->invokeDecodeStructuredOutput(
            $output,
            ['questions', 'ambiguity_report', 'summary'],
            ['questions', 'ambiguity_report', 'summary', 'cli_session_id']
        );

        $this->assertIsArray($decoded);
        $this->assertSame('q-1', $decoded['questions'][0]['question_id']);
        $this->assertSame('codex-session-1', $decoded['cli_session_id']);
    }

    public function test_decode_structured_output_handles_stringified_result_payloads(): void
    {
        $output = json_encode([
            'session_id' => 'codex-session-2',
            'result' => json_encode([
                'action_plan_markdown' => "# Action Plan\n\nStart with a pilot.",
                'recommended_approach' => 'Pilot first',
                'recommended_tooling' => ['Codex', 'Claude'],
            ], JSON_THROW_ON_ERROR),
        ], JSON_THROW_ON_ERROR);

        $decoded = $this->invokeDecodeStructuredOutput(
            $output,
            ['action_plan_markdown'],
            ['action_plan_markdown', 'recommended_approach', 'recommended_tooling', 'pilot_recommendation', 'phases', 'risks', 'assumptions', 'cli_session_id']
        );

        $this->assertIsArray($decoded);
        $this->assertSame('Pilot first', $decoded['recommended_approach']);
        $this->assertSame('codex-session-2', $decoded['cli_session_id']);
    }

    public function test_decode_structured_output_handles_content_part_text_payloads(): void
    {
        $payload = [
            'questions' => [],
            'ambiguity_report' => [
                'needs_another_round' => false,
                'resolved_areas' => ['ownership'],
                'open_ambiguities' => [],
                'contradictions' => [],
                'coverage_gaps' => [],
                'closure_reason' => 'Material ambiguity exhausted.',
            ],
            'summary' => [
                'summary_markdown' => "# Findings\n\nOwnership is clear.",
                'goals' => [],
                'actors' => ['Operations'],
                'systems' => ['ERP'],
                'constraints' => [],
                'risks' => [],
                'notes' => [],
            ],
        ];

        $output = implode("\n", [
            json_encode(['type' => 'thread.started', 'thread_id' => 'thread-1'], JSON_THROW_ON_ERROR),
            json_encode([
                'type' => 'item.completed',
                'session_id' => 'codex-session-3',
                'item' => [
                    'type' => 'message',
                    'content' => [
                        ['type' => 'output_text', 'text' => json_encode($payload, JSON_THROW_ON_ERROR)],
                    ],
                ],
            ], JSON_THROW_ON_ERROR),
        ]);

        $decoded = $this->invokeDecodeStructuredOutput(
            $output,
            ['questions', 'ambiguity_report', 'summary'],
            ['questions', 'ambiguity_report', 'summary', 'cli_session_id']
        );

        $this->assertIsArray($decoded);
        $this->assertSame('Material ambiguity exhausted.', $decoded['ambiguity_report']['closure_reason']);
        $this->assertSame('codex-session-3', $decoded['cli_session_id']);
    }

    public function test_decode_structured_output_normalizes_streamed_agent_message_round_batches(): void
    {
        $payload = [
            'questions' => [
                [
                    'category' => 'scope',
                    'decision_axis' => 'workflow entry conditions',
                    'prompt' => 'What event should start a workflow interrogation session?',
                    'answer_type' => 'choice',
                    'options' => [
                        'A new brief is submitted',
                        'A workflow owner requests discovery',
                    ],
                ],
            ],
        ];

        $output = implode("\n", [
            json_encode(['type' => 'thread.started', 'thread_id' => 'thread-2'], JSON_THROW_ON_ERROR),
            json_encode(['type' => 'turn.started'], JSON_THROW_ON_ERROR),
            json_encode([
                'type' => 'item.completed',
                'item' => [
                    'id' => 'item_0',
                    'type' => 'agent_message',
                    'text' => json_encode($payload, JSON_THROW_ON_ERROR),
                ],
            ], JSON_THROW_ON_ERROR),
        ]);

        $decoded = $this->invokeDecodeStructuredOutput(
            $output,
            ['questions', 'ambiguity_report', 'summary'],
            ['questions', 'ambiguity_report', 'summary', 'cli_session_id']
        );

        $this->assertIsArray($decoded);
        $this->assertSame('workflow_entry_conditions', $decoded['questions'][0]['question_id']);
        $this->assertSame('scope', $decoded['questions'][0]['category']);
        $this->assertTrue($decoded['ambiguity_report']['needs_another_round']);
        $this->assertSame([], $decoded['summary']);
    }

    public function test_decode_structured_output_generates_unique_question_ids_when_missing(): void
    {
        $payload = [
            'questions' => [
                [
                    'category' => 'scope',
                    'decision_axis' => 'closure rule',
                    'prompt' => 'What exact condition allows the interrogation loop to stop?',
                    'answer_type' => 'choice',
                    'options' => ['All ambiguities resolved'],
                ],
                [
                    'category' => 'scope',
                    'decision_axis' => 'closure rule',
                    'prompt' => 'What exact condition allows the interrogation loop to stop?',
                    'answer_type' => 'choice',
                    'options' => ['Explicit user approval'],
                ],
            ],
        ];

        $decoded = $this->invokeDecodeStructuredOutput(
            json_encode($payload, JSON_THROW_ON_ERROR),
            ['questions', 'ambiguity_report', 'summary'],
            ['questions', 'ambiguity_report', 'summary', 'cli_session_id']
        );

        $this->assertIsArray($decoded);
        $this->assertSame('closure_rule', $decoded['questions'][0]['question_id']);
        $this->assertNotSame($decoded['questions'][0]['question_id'], $decoded['questions'][1]['question_id']);
    }

    /**
     * @param  array<int, string>  $requiredKeys
     * @param  array<int, string>  $signalKeys
     * @return array<string, mixed>|null
     */
    private function invokeDecodeStructuredOutput(string $output, array $requiredKeys, array $signalKeys): ?array
    {
        $client = new WorkflowInterrogatorRunnerClient(
            $this->createStub(AdapterFactory::class),
            $this->createStub(WorkflowInterrogatorPromptBuilder::class),
        );

        $reflection = new ReflectionClass($client);
        $method = $reflection->getMethod('decodeStructuredOutput');

        $result = $method->invoke($client, $output, $requiredKeys, $signalKeys);

        return is_array($result) ? $result : null;
    }
}
