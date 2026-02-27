<?php

namespace Tests\Unit;

use App\Support\Interrogation\Adapters\ClaudeAdapter;
use RuntimeException;
use Tests\TestCase;

class ClaudeAdapterReviewerTest extends TestCase
{
    public function test_reviewer_schema_returns_valid_json_schema(): void
    {
        $adapter = new ClaudeAdapter;
        $schema = $adapter->reviewerSchema();

        $this->assertIsArray($schema);
        $this->assertArrayHasKey('type', $schema);
        $this->assertSame('object', $schema['type']);
        $this->assertArrayHasKey('properties', $schema);
        $this->assertArrayHasKey('verdict', $schema['properties']);
        $this->assertArrayHasKey('issues', $schema['properties']);
        $this->assertArrayHasKey('confidence', $schema['properties']);
    }

    public function test_reviewer_schema_includes_all_required_fields(): void
    {
        $adapter = new ClaudeAdapter;
        $schema = $adapter->reviewerSchema();

        $this->assertArrayHasKey('required', $schema);
        $this->assertContains('verdict', $schema['required']);
        $this->assertContains('issues', $schema['required']);
        $this->assertContains('confidence', $schema['required']);
    }

    public function test_reviewer_schema_verdict_has_correct_enum_values(): void
    {
        $adapter = new ClaudeAdapter;
        $schema = $adapter->reviewerSchema();

        $verdictSchema = $schema['properties']['verdict'];
        $this->assertArrayHasKey('enum', $verdictSchema);
        $this->assertContains('pass', $verdictSchema['enum']);
        $this->assertContains('revise', $verdictSchema['enum']);
        $this->assertContains('needs_clarification', $verdictSchema['enum']);
    }

    public function test_reviewer_schema_issues_has_correct_structure(): void
    {
        $adapter = new ClaudeAdapter;
        $schema = $adapter->reviewerSchema();

        $issuesSchema = $schema['properties']['issues'];
        $this->assertSame('array', $issuesSchema['type']);
        $this->assertArrayHasKey('items', $issuesSchema);

        $issueItem = $issuesSchema['items'];
        $this->assertSame('object', $issueItem['type']);
        $this->assertArrayHasKey('properties', $issueItem);
        $this->assertArrayHasKey('type', $issueItem['properties']);
        $this->assertArrayHasKey('severity', $issueItem['properties']);
        $this->assertArrayHasKey('message', $issueItem['properties']);
    }

    public function test_build_reviewer_command_includes_json_schema_flag(): void
    {
        $adapter = new ClaudeAdapter;
        $command = $adapter->buildReviewerCommand('/path/to/project', 'Test prompt');

        $this->assertIsArray($command);
        $this->assertContains('--json-schema', $command);
    }

    public function test_build_reviewer_command_includes_required_tools(): void
    {
        $adapter = new ClaudeAdapter;
        $command = $adapter->buildReviewerCommand('/path/to/project', 'Test prompt');

        $toolsArg = null;
        foreach ($command as $arg) {
            if (str_starts_with($arg, '--tools=')) {
                $toolsArg = $arg;
                break;
            }
        }

        $this->assertNotNull($toolsArg);
        $this->assertStringContainsString('Read', $toolsArg);
        $this->assertStringContainsString('Glob', $toolsArg);
        $this->assertStringContainsString('Grep', $toolsArg);
    }

    public function test_build_reviewer_command_excludes_resume_flag(): void
    {
        $adapter = new ClaudeAdapter;
        $command = $adapter->buildReviewerCommand('/path/to/project', 'Test prompt');

        $this->assertNotContains('--resume', $command);
    }

    public function test_build_reviewer_command_does_not_include_cwd_flag(): void
    {
        $adapter = new ClaudeAdapter;
        $command = $adapter->buildReviewerCommand('/path/to/project', 'Test prompt');

        $this->assertNotContains('--cwd', $command);
        $this->assertNotContains('/path/to/project', $command);
    }

    public function test_build_reviewer_command_includes_prompt(): void
    {
        $adapter = new ClaudeAdapter;
        $command = $adapter->buildReviewerCommand('/path/to/project', 'Test prompt for review');

        $this->assertContains('Test prompt for review', $command);
    }

    public function test_parse_reviewer_response_extracts_payload(): void
    {
        $adapter = new ClaudeAdapter;
        $rawOutput = json_encode([
            'verdict' => 'pass',
            'issues' => [],
            'required_changes' => [],
            'clarification_questions' => [],
            'confidence' => 0.9,
            'review_notes' => 'All good',
        ]);

        $result = $adapter->parseReviewerResponse($rawOutput);

        $this->assertSame('pass', $result['verdict']);
        $this->assertSame(0.9, $result['confidence']);
        $this->assertSame([], $result['issues']);
    }

    public function test_parse_reviewer_response_handles_wrapped_response(): void
    {
        $adapter = new ClaudeAdapter;
        $rawOutput = json_encode([
            'result' => [
                'verdict' => 'revise',
                'issues' => [
                    [
                        'type' => 'missing_requirement',
                        'severity' => 'high',
                        'message' => 'Missing error handling',
                    ],
                ],
                'confidence' => 0.75,
            ],
        ]);

        $result = $adapter->parseReviewerResponse($rawOutput);

        $this->assertSame('revise', $result['verdict']);
        $this->assertSame(0.75, $result['confidence']);
        $this->assertCount(1, $result['issues']);
    }

    public function test_parse_reviewer_response_handles_structured_output_wrapper(): void
    {
        $adapter = new ClaudeAdapter;
        $rawOutput = json_encode([
            'structured_output' => [
                'verdict' => 'needs_clarification',
                'issues' => [],
                'clarification_questions' => ['What is the expected behavior?'],
                'confidence' => 0.5,
            ],
            'session_id' => 'session-123',
        ]);

        $result = $adapter->parseReviewerResponse($rawOutput);

        $this->assertSame('needs_clarification', $result['verdict']);
        $this->assertSame(0.5, $result['confidence']);
    }

    public function test_parse_reviewer_response_handles_multiline_stream_json(): void
    {
        $adapter = new ClaudeAdapter;
        $rawOutput = '{"type":"init","session_id":"abc"}
{"type":"assistant","message":"Reviewing..."}
{"verdict":"pass","issues":[],"confidence":0.85}';

        $result = $adapter->parseReviewerResponse($rawOutput);

        $this->assertSame('pass', $result['verdict']);
        $this->assertSame(0.85, $result['confidence']);
    }

    public function test_parse_reviewer_response_throws_on_invalid_json(): void
    {
        $adapter = new ClaudeAdapter;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to parse reviewer response');
        $adapter->parseReviewerResponse('not valid json');
    }

    public function test_parse_reviewer_response_throws_on_missing_verdict(): void
    {
        $adapter = new ClaudeAdapter;
        $rawOutput = json_encode([
            'issues' => [],
            'confidence' => 0.9,
        ]);

        $this->expectException(RuntimeException::class);
        $adapter->parseReviewerResponse($rawOutput);
    }

    public function test_parse_reviewer_response_throws_on_empty_output(): void
    {
        $adapter = new ClaudeAdapter;

        $this->expectException(RuntimeException::class);
        $adapter->parseReviewerResponse('');
    }
}
