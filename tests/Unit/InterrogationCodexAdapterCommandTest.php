<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\InterrogationSession;
use App\Support\Interrogation\Adapters\CodexAdapter;
use Tests\TestCase;

class InterrogationCodexAdapterCommandTest extends TestCase
{
    public function test_build_environment_excludes_codex_thread_context_variables(): void
    {
        $originalEnv = $_ENV;

        try {
            $_ENV['CODEX_THREAD_ID'] = 'thread-123';
            $_ENV['CODEX_SESSION_ID'] = 'session-123';
            $_ENV['CODEX_INTERNAL_ORIGINATOR_OVERRIDE'] = 'desktop';
            $_ENV['SAFE_KEEP'] = 'yes';

            $adapter = new CodexAdapter;
            $session = (new InterrogationSession)->forceFill(['id' => 42]);

            $env = $adapter->buildEnvironment($session);

            $this->assertFalse($env['CODEX_THREAD_ID'] ?? null);
            $this->assertFalse($env['CODEX_SESSION_ID'] ?? null);
            $this->assertFalse($env['CODEX_INTERNAL_ORIGINATOR_OVERRIDE'] ?? null);
            $this->assertSame('yes', $env['SAFE_KEEP'] ?? null);
            $this->assertSame('42', $env['INTERROGATION_SESSION_ID'] ?? null);
        } finally {
            $_ENV = $originalEnv;
        }
    }

    public function test_parse_stream_event_decodes_json_string_lines(): void
    {
        $adapter = new CodexAdapter;

        $parsed = $adapter->parseStreamEvent(json_encode("line one\nline two") ?: '"line one"');

        $this->assertIsArray($parsed);
        $this->assertSame('message', $parsed['type'] ?? null);
        $this->assertSame("line one\nline two", $parsed['payload']['message'] ?? null);
    }

    public function test_parse_stream_event_decodes_double_encoded_json_object_lines(): void
    {
        $adapter = new CodexAdapter;
        $inner = json_encode([
            'type' => 'item.completed',
            'item' => [
                'type' => 'command_execution',
                'aggregated_output' => 'Read routes/api.php',
            ],
        ], JSON_UNESCAPED_SLASHES) ?: '{}';

        $parsed = $adapter->parseStreamEvent(json_encode($inner) ?: '"{}"');

        $this->assertIsArray($parsed);
        $this->assertSame('item.completed', $parsed['type'] ?? null);
        $this->assertSame('Read routes/api.php', $parsed['payload']['message'] ?? null);
    }

    public function test_parse_stream_event_summarizes_command_execution_without_dumping_large_output(): void
    {
        $adapter = new CodexAdapter;
        $rawLine = json_encode([
            'type' => 'item.completed',
            'item' => [
                'type' => 'command_execution',
                'command' => '/bin/zsh -lc "sed -n \'1,200p\' app/Http/Controllers/Api/V1/InterrogationSessionController.php"',
                'aggregated_output' => "<?php\n\nnamespace App\\Http\\Controllers\\Api\\V1;\nclass InterrogationSessionController\n{\n    public function index() {}\n}\n",
                'exit_code' => 0,
                'status' => 'completed',
            ],
        ], JSON_UNESCAPED_SLASHES) ?: '{}';

        $parsed = $adapter->parseStreamEvent($rawLine);

        $this->assertIsArray($parsed);
        $this->assertSame('item.completed', $parsed['type'] ?? null);
        $this->assertSame('Reading source files.', $parsed['payload']['message'] ?? null);
        $this->assertStringNotContainsString('<?php', (string) ($parsed['payload']['message'] ?? ''));
        $this->assertFalse(isset($parsed['payload']['raw']['item']['aggregated_output']));
        $this->assertTrue((bool) ($parsed['payload']['raw']['item']['output_omitted'] ?? false));
    }

    public function test_question_command_uses_schema_file_path_for_codex_cli(): void
    {
        $adapter = new CodexAdapter;
        $session = (new InterrogationSession)->forceFill(['id' => 99, 'cli_session_id' => null]);

        $command = $adapter->buildQuestionCommand($session, 'Ask first question', 'system');

        $execIndex = array_search('exec', $command, true);
        $this->assertNotFalse($execIndex);
        $this->assertNotContains('resume', $command);

        $schemaFlagIndex = array_search('--output-schema', $command, true);
        $this->assertNotFalse($schemaFlagIndex);

        $schemaPath = $command[(int) $schemaFlagIndex + 1] ?? null;
        $this->assertIsString($schemaPath);
        $this->assertStringContainsString('/storage/framework/interrogation-schemas/', $schemaPath);
        $this->assertStringEndsWith('.schema.json', $schemaPath);
        $this->assertFileExists((string) $schemaPath);

        $schema = json_decode((string) file_get_contents((string) $schemaPath), true);
        $this->assertIsArray($schema);
        $this->assertContains('question_id', $schema['required'] ?? []);
        $this->assertContains('question_text', $schema['required'] ?? []);
    }

    public function test_question_command_uses_exec_resume_when_session_id_exists(): void
    {
        $adapter = new CodexAdapter;
        $session = (new InterrogationSession)->forceFill(['id' => 99, 'cli_session_id' => 'session-123']);

        $command = $adapter->buildQuestionCommand($session, 'Ask next question', 'system');

        $execIndex = array_search('exec', $command, true);
        $this->assertNotFalse($execIndex);
        $resumeIndex = array_search('resume', $command, true);
        $this->assertSame((int) $execIndex + 1, $resumeIndex);
        $this->assertSame('session-123', $command[(int) $resumeIndex + 1] ?? null);
    }

    public function test_question_command_includes_configured_codex_model_flag(): void
    {
        config()->set('agent.interrogation.codex_model', 'gpt-5.3-codex');

        $adapter = new CodexAdapter;
        $session = (new InterrogationSession)->forceFill(['id' => 99, 'cli_session_id' => null]);

        $command = $adapter->buildQuestionCommand($session, 'Ask next question', 'system');

        $modelFlagIndex = array_search('--model', $command, true);
        $this->assertNotFalse($modelFlagIndex);
        $this->assertSame('gpt-5.3-codex', $command[(int) $modelFlagIndex + 1] ?? null);
    }

    public function test_question_command_composes_system_prompt_into_user_prompt(): void
    {
        $adapter = new CodexAdapter;
        $session = (new InterrogationSession)->forceFill(['id' => 99, 'cli_session_id' => null]);

        $command = $adapter->buildQuestionCommand($session, 'User prompt', 'System prompt');
        $finalPrompt = (string) (end($command) ?: '');

        $this->assertStringContainsString('<SYSTEM>', $finalPrompt);
        $this->assertStringContainsString('System prompt', $finalPrompt);
        $this->assertStringContainsString('<USER_REQUEST>', $finalPrompt);
        $this->assertStringContainsString('User prompt', $finalPrompt);
    }

    public function test_parse_question_response_extracts_json_from_codex_agent_message_event(): void
    {
        $adapter = new CodexAdapter;
        $output = implode("\n", [
            json_encode(['type' => 'thread.started', 'thread_id' => 't-1'], JSON_UNESCAPED_SLASHES) ?: '{}',
            json_encode([
                'type' => 'item.completed',
                'item' => [
                    'type' => 'agent_message',
                    'text' => json_encode([
                        'question_id' => 'q-auth',
                        'question_text' => 'How should users authenticate?',
                        'answer_type' => 'choice',
                        'options' => ['Email/password', 'SSO'],
                        'reasoning' => 'Need security model.',
                        'category' => 'security',
                        'progress_estimate' => 15,
                        'is_complete' => false,
                        'cli_session_id' => 's-1',
                    ], JSON_UNESCAPED_SLASHES),
                ],
            ], JSON_UNESCAPED_SLASHES) ?: '{}',
            json_encode(['type' => 'turn.completed'], JSON_UNESCAPED_SLASHES) ?: '{}',
        ]);

        $parsed = $adapter->parseQuestionResponse($output);

        $this->assertIsArray($parsed);
        $this->assertSame('q-auth', $parsed['question_id'] ?? null);
        $this->assertSame('How should users authenticate?', $parsed['question_text'] ?? null);
        $this->assertSame('choice', $parsed['answer_type'] ?? null);
    }

    public function test_parse_question_response_prefers_most_complete_structured_payload_from_stream(): void
    {
        $adapter = new CodexAdapter;

        $partialPayload = [
            'question_id' => 'q-flags',
            'question_text' => 'How should org feature flags roll out?',
            'answer_type' => 'freetext',
            'options' => [],
            'reasoning' => 'uires "regression tests proving no behavior change when org feature flags are disabled".',
            'category' => 'architecture',
            'progress_estimate' => 42,
            'is_complete' => false,
            'cli_session_id' => 'session-1',
        ];

        $fullPayload = [
            'question_id' => 'q-flags',
            'question_text' => 'How should org feature flags roll out?',
            'answer_type' => 'freetext',
            'options' => [],
            'reasoning' => 'Requires "regression tests proving no behavior change when org feature flags are disabled" and mentions "feature flags and warn-only rollout mode."',
            'category' => 'architecture',
            'progress_estimate' => 42,
            'is_complete' => false,
            'cli_session_id' => 'session-1',
        ];

        $output = implode("\n", [
            json_encode([
                'type' => 'item.completed',
                'item' => [
                    'type' => 'agent_message',
                    'text' => json_encode($partialPayload, JSON_UNESCAPED_SLASHES),
                ],
            ], JSON_UNESCAPED_SLASHES) ?: '{}',
            json_encode([
                'type' => 'item.completed',
                'item' => [
                    'type' => 'agent_message',
                    'text' => json_encode($fullPayload, JSON_UNESCAPED_SLASHES),
                ],
            ], JSON_UNESCAPED_SLASHES) ?: '{}',
            json_encode(['type' => 'turn.completed'], JSON_UNESCAPED_SLASHES) ?: '{}',
        ]);

        $parsed = $adapter->parseQuestionResponse($output);

        $this->assertIsArray($parsed);
        $this->assertSame('q-flags', $parsed['question_id'] ?? null);
        $this->assertSame('Requires "regression tests proving no behavior change when org feature flags are disabled" and mentions "feature flags and warn-only rollout mode."', $parsed['reasoning'] ?? null);
    }

    public function test_parse_stream_event_suppresses_known_codex_state_warnings(): void
    {
        $adapter = new CodexAdapter;

        $parsed = $adapter->parseStreamEvent(
            '2026-02-16T14:34:35.154354Z ERROR codex_core::rollout::list: state db missing rollout path for thread abc'
        );

        $this->assertIsArray($parsed);
        $this->assertSame('diagnostic', $parsed['type'] ?? null);
        $this->assertSame('', $parsed['payload']['message'] ?? null);
    }

    public function test_parse_summary_response_preserves_cli_session_id_from_structured_envelope(): void
    {
        $adapter = new CodexAdapter;

        $output = json_encode([
            'type' => 'result',
            'session_id' => 'codex-summary-session-1',
            'structured_output' => [
                'summary_markdown' => 'Code analysis summary',
                'goals' => ['Goal A'],
                'constraints' => [],
                'acceptance_criteria' => [],
                'open_questions' => [],
                'private_notes' => '',
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $parsed = $adapter->parseSummaryResponse((string) $output);

        $this->assertIsArray($parsed);
        $this->assertSame('Code analysis summary', $parsed['summary_markdown'] ?? null);
        $this->assertSame('codex-summary-session-1', $parsed['cli_session_id'] ?? null);
    }
}
