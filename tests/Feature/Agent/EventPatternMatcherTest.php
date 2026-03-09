<?php

declare(strict_types=1);

namespace Tests\Feature\Agent;

use App\Support\Agent\EventPatternMatcher;
use Tests\TestCase;

class EventPatternMatcherTest extends TestCase
{
    private EventPatternMatcher $matcher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->matcher = new EventPatternMatcher;
    }

    public function test_detects_approval_required(): void
    {
        $this->assertTrue($this->matcher->shouldMarkApprovalRequired('Agent needs permission to continue'));
        $this->assertTrue($this->matcher->shouldMarkApprovalRequired('Could you approve this change?'));
        $this->assertTrue($this->matcher->shouldMarkApprovalRequired('This requires your permission'));
    }

    public function test_ignores_approval_false_positives(): void
    {
        $this->assertFalse($this->matcher->shouldMarkApprovalRequired('approval likely required in active run output'));
        $this->assertFalse($this->matcher->shouldMarkApprovalRequired('| approval required | description'));
    }

    public function test_no_match_for_generic_text(): void
    {
        $this->assertFalse($this->matcher->shouldMarkApprovalRequired('Hello world'));
        $this->assertFalse($this->matcher->shouldMarkApprovalRequired('The function completed successfully'));
    }

    public function test_matches_permission_blocker_pattern(): void
    {
        $this->assertTrue($this->matcher->matchesPermissionBlockerPattern('Agent needs write permissions'));
        $this->assertTrue($this->matcher->matchesPermissionBlockerPattern('All file write operations are denied'));
        $this->assertFalse($this->matcher->matchesPermissionBlockerPattern('Files written successfully'));
    }

    public function test_matches_clarification_pattern(): void
    {
        $this->assertTrue($this->matcher->matchesClarificationPattern('Could you clarify the requirements?'));
        $this->assertTrue($this->matcher->matchesClarificationPattern('Can you confirm this approach?'));
        $this->assertTrue($this->matcher->matchesClarificationPattern('Should I proceed with this?'));
        $this->assertFalse($this->matcher->matchesClarificationPattern('Processing complete'));
    }

    public function test_detects_structured_rate_limit(): void
    {
        $this->assertTrue($this->matcher->shouldMarkRateLimitDetected(
            json_encode(['type' => 'error', 'message' => 'rate limited, try again later']) ?: ''
        ));
        $this->assertTrue($this->matcher->shouldMarkRateLimitDetected(
            json_encode(['type' => 'turn.failed', 'error' => 'too many requests']) ?: ''
        ));
    }

    public function test_ignores_non_structured_rate_limit_text(): void
    {
        $this->assertFalse($this->matcher->shouldMarkRateLimitDetected('rate limited'));
        $this->assertFalse($this->matcher->shouldMarkRateLimitDetected('some regular output'));
    }

    public function test_matches_rate_limit_pattern(): void
    {
        $this->assertTrue($this->matcher->matchesRateLimitPattern('Error: rate limited'));
        $this->assertTrue($this->matcher->matchesRateLimitPattern('HTTP 429 too many requests'));
        $this->assertTrue($this->matcher->matchesRateLimitPattern('quota exceeded'));
        $this->assertFalse($this->matcher->matchesRateLimitPattern('Normal output'));
    }

    public function test_is_likely_non_runtime_snippet(): void
    {
        $this->assertTrue($this->matcher->isLikelyNonRuntimeSnippet(
            json_encode(['type' => 'thread.started']) ?: ''
        ));
        $this->assertFalse($this->matcher->isLikelyNonRuntimeSnippet('Hello, this is a message'));
    }

    public function test_line_numbered_snippet_detected(): void
    {
        $this->assertTrue($this->matcher->isLikelyNonRuntimeSnippet("1→public function test()\n2→{"));
    }

    public function test_should_suppress_as_noise(): void
    {
        $mcpDump = implode(' ', array_map(fn ($i) => "mcp__server{$i}__tool{$i}", range(1, 6)));
        $this->assertTrue($this->matcher->shouldSuppressAsNoise($mcpDump));
    }

    public function test_should_not_suppress_regular_text(): void
    {
        $this->assertFalse($this->matcher->shouldSuppressAsNoise('A normal agent output message'));
    }

    public function test_extracts_mcp_unavailable_endpoints(): void
    {
        $this->assertSame([], $this->matcher->extractMcpUnavailableEndpoints('no mcp issues'));
    }

    public function test_extract_rate_limit_reset_iso8601(): void
    {
        $result = $this->matcher->extractRateLimitReset('Rate limit resets at 2026-03-08T12:00:00Z');
        $this->assertNotNull($result);
        $this->assertSame('UTC', $result['timezone']);
    }

    public function test_extract_rate_limit_reset_am_pm(): void
    {
        $result = $this->matcher->extractRateLimitReset('Reset at 3:00 pm');
        $this->assertNotNull($result);
        $this->assertSame('UTC', $result['timezone']);
    }

    public function test_extract_rate_limit_reset_24h(): void
    {
        $result = $this->matcher->extractRateLimitReset('Resets at 15:30');
        $this->assertNotNull($result);
    }

    public function test_extract_rate_limit_reset_with_timezone(): void
    {
        $result = $this->matcher->extractRateLimitReset('Resets at 3:00 pm (America/New_York)');
        $this->assertNotNull($result);
        $this->assertSame('America/New_York', $result['timezone']);
    }

    public function test_extract_rate_limit_reset_null_on_no_match(): void
    {
        $this->assertNull($this->matcher->extractRateLimitReset('No reset time here'));
    }

    public function test_normalize_clarification_excerpt(): void
    {
        $result = $this->matcher->normalizeClarificationExcerpt('Could you clarify?');
        $this->assertSame('Could you clarify?', $result);
    }

    public function test_normalize_clarification_excerpt_json(): void
    {
        $json = json_encode(['text' => 'What should I do?']);
        $result = $this->matcher->normalizeClarificationExcerpt($json ?: '');
        $this->assertSame('What should I do?', $result);
    }

    public function test_normalize_clarification_excerpt_escaped_newlines(): void
    {
        $result = $this->matcher->normalizeClarificationExcerpt('Line one\\nLine two');
        $this->assertStringContainsString("\n", $result);
    }

    public function test_extract_clarification_text_from_nested(): void
    {
        $payload = ['data' => ['text' => 'Please clarify']];
        $this->assertSame('Please clarify', $this->matcher->extractClarificationText($payload));
    }

    public function test_extract_clarification_text_returns_null_for_empty(): void
    {
        $this->assertNull($this->matcher->extractClarificationText(['id' => '123']));
    }

    public function test_decode_structured_event_json(): void
    {
        $json = json_encode(['type' => 'test']);
        $this->assertSame(['type' => 'test'], $this->matcher->decodeStructuredEvent($json ?: ''));
    }

    public function test_decode_structured_event_double_encoded(): void
    {
        $inner = json_encode(['type' => 'nested']);
        $outer = json_encode($inner);
        $result = $this->matcher->decodeStructuredEvent($outer ?: '');
        $this->assertSame(['type' => 'nested'], $result);
    }

    public function test_decode_structured_event_null_for_non_json(): void
    {
        $this->assertNull($this->matcher->decodeStructuredEvent('not json'));
        $this->assertNull($this->matcher->decodeStructuredEvent(''));
    }

    public function test_contains_tool_result_content(): void
    {
        $decoded = [
            'message' => [
                'content' => [
                    ['type' => 'tool_result', 'text' => 'result'],
                ],
            ],
        ];
        $this->assertTrue($this->matcher->containsToolResultContent($decoded));
    }

    public function test_does_not_contain_tool_result_content(): void
    {
        $decoded = [
            'message' => [
                'content' => [
                    ['type' => 'text', 'text' => 'hello'],
                ],
            ],
        ];
        $this->assertFalse($this->matcher->containsToolResultContent($decoded));
    }

    public function test_extract_readable_text(): void
    {
        $this->assertSame('Hello', $this->matcher->extractReadableText(['text' => 'Hello']));
        $this->assertSame('World', $this->matcher->extractReadableText(['message' => 'World']));
    }

    public function test_extract_readable_text_from_delta(): void
    {
        $decoded = ['delta' => ['text' => 'streaming']];
        $this->assertSame('streaming', $this->matcher->extractReadableText($decoded));
    }

    public function test_extract_readable_text_empty_for_type_only(): void
    {
        $this->assertSame('', $this->matcher->extractReadableText(['type' => 'ping']));
    }

    public function test_is_structured_stream_event(): void
    {
        $this->assertTrue($this->matcher->isStructuredStreamEvent(
            json_encode(['type' => 'content_block_delta']) ?: ''
        ));
        $this->assertTrue($this->matcher->isStructuredStreamEvent(
            json_encode(['type' => 'ping']) ?: ''
        ));
        $this->assertFalse($this->matcher->isStructuredStreamEvent('plain text'));
    }

    public function test_suppress_noise_disabled_by_config(): void
    {
        config()->set('agent.log_filtering.suppress_machine_noise', false);
        $mcpDump = implode(' ', array_map(fn ($i) => "mcp__server{$i}__tool{$i}", range(1, 6)));
        $this->assertFalse($this->matcher->shouldSuppressAsNoise($mcpDump));
    }
}
