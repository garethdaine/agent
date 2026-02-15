<?php

namespace Tests\Unit;

use App\Support\Interrogation\Adapters\ClaudeAdapter;
use Tests\TestCase;

class InterrogationClaudeAdapterStreamParsingTest extends TestCase
{
    public function test_parse_stream_event_handles_nested_message_arrays_without_exception(): void
    {
        $adapter = new ClaudeAdapter;

        $line = json_encode([
            'type' => 'assistant',
            'session_id' => 'session-123',
            'message' => [
                'id' => 'msg-1',
                'content' => [
                    ['type' => 'text', 'text' => 'Inspecting repository'],
                    ['type' => 'text', 'text' => 'Reading relevant files'],
                ],
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $parsed = $adapter->parseStreamEvent((string) $line);

        $this->assertIsArray($parsed);
        $this->assertSame('assistant', $parsed['type']);
        $this->assertSame('session-123', $parsed['payload']['cli_session_id']);
        $this->assertStringContainsString('Inspecting repository', $parsed['payload']['message']);
        $this->assertStringContainsString('Reading relevant files', $parsed['payload']['message']);
    }

    public function test_parse_stream_event_summarizes_tool_use_without_usage_noise(): void
    {
        $adapter = new ClaudeAdapter;

        $line = json_encode([
            'type' => 'assistant',
            'session_id' => 'session-456',
            'message' => [
                'content' => [
                    [
                        'type' => 'tool_use',
                        'name' => 'Read',
                        'input' => ['file_path' => '/Users/garethdaine/Code/agent/docs/plans/requirements-discovery-feature.md'],
                    ],
                ],
                'usage' => [
                    'output_tokens' => 42,
                    'service_tier' => 'standard',
                    'inference_geo' => 'not_available',
                ],
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $parsed = $adapter->parseStreamEvent((string) $line);

        $this->assertIsArray($parsed);
        $this->assertSame('Reading requirements-discovery-feature.md', $parsed['payload']['message']);
        $this->assertStringNotContainsString('not_available', $parsed['payload']['message']);
        $this->assertArrayNotHasKey('raw', $parsed['payload']);
    }

    public function test_parse_stream_event_summarizes_tool_result_file_counts(): void
    {
        $adapter = new ClaudeAdapter;

        $line = json_encode([
            'type' => 'user',
            'session_id' => 'session-789',
            'message' => [
                'content' => [
                    [
                        'type' => 'tool_result',
                        'tool_use_id' => 'toolu_123',
                        'content' => "/tmp/a.php\n/tmp/b.php\n/tmp/c.php",
                    ],
                ],
            ],
            'tool_use_result' => [
                'numFiles' => 3,
                'truncated' => true,
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $parsed = $adapter->parseStreamEvent((string) $line);

        $this->assertIsArray($parsed);
        $this->assertSame('Tool result: 3 files (truncated).', $parsed['payload']['message']);
    }

    public function test_parse_stream_event_ignores_transient_sibling_tool_error_message(): void
    {
        $adapter = new ClaudeAdapter;

        $line = json_encode([
            'type' => 'user',
            'session_id' => 'session-999',
            'message' => [
                'content' => [
                    [
                        'type' => 'tool_result',
                        'tool_use_id' => 'toolu_999',
                        'content' => '<tool_use_error>Sibling tool call errored</tool_use_error>',
                    ],
                ],
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $parsed = $adapter->parseStreamEvent((string) $line);

        $this->assertIsArray($parsed);
        $this->assertSame('', $parsed['payload']['message']);
    }

    public function test_parse_stream_event_does_not_flag_php_read_output_with_errorenvelope_as_tool_error(): void
    {
        $adapter = new ClaudeAdapter;

        $line = json_encode([
            'type' => 'user',
            'session_id' => 'session-php-read',
            'message' => [
                'content' => [
                    [
                        'type' => 'tool_result',
                        'tool_use_id' => 'toolu_php_read',
                        'content' => "1→<?php\n2→\n3→namespace App\\Http\\Controllers\\Api\\V1;\n10→use App\\Support\\Agent\\ErrorEnvelope;",
                    ],
                ],
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $parsed = $adapter->parseStreamEvent((string) $line);

        $this->assertIsArray($parsed);
        $this->assertSame('', $parsed['payload']['message']);
    }
}
