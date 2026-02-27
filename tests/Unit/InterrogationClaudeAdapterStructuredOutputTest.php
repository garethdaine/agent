<?php

namespace Tests\Unit;

use App\Support\Interrogation\Adapters\ClaudeAdapter;
use Tests\TestCase;

class InterrogationClaudeAdapterStructuredOutputTest extends TestCase
{
    public function test_parse_question_response_unwraps_structured_output_envelope(): void
    {
        $adapter = new ClaudeAdapter;

        $output = json_encode([
            'type' => 'result',
            'session_id' => 'session-structured-1',
            'structured_output' => [
                'question_text' => 'What is the first delivery milestone?',
                'answer_type' => 'freetext',
                'progress_estimate' => 20,
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $parsed = $adapter->parseQuestionResponse((string) $output);

        $this->assertIsArray($parsed);
        $this->assertSame('What is the first delivery milestone?', $parsed['question_text']);
        $this->assertSame('freetext', $parsed['answer_type']);
        $this->assertSame(20, $parsed['progress_estimate']);
        $this->assertSame('session-structured-1', $parsed['cli_session_id']);
    }

    public function test_parse_summary_response_unwraps_result_json_string(): void
    {
        $adapter = new ClaudeAdapter;

        $output = json_encode([
            'type' => 'result',
            'result' => json_encode([
                'summary_markdown' => 'Summary content',
                'goals' => ['Goal 1'],
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $parsed = $adapter->parseSummaryResponse((string) $output);

        $this->assertIsArray($parsed);
        $this->assertSame('Summary content', $parsed['summary_markdown']);
        $this->assertSame(['Goal 1'], $parsed['goals']);
    }
}
