<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\InterrogationSession;
use App\Support\Interrogation\Adapters\ClaudeAdapter;
use Tests\TestCase;

class InterrogationClaudeAdapterCommandTest extends TestCase
{
    public function test_discovery_command_preserves_prompt_argument_and_single_tools_token(): void
    {
        $adapter = new ClaudeAdapter;

        $session = new InterrogationSession([
            'runner_type' => 'claude',
        ]);

        $prompt = 'Inspect repository and produce discovery events.';

        $command = $adapter->buildDiscoveryCommand($session, $prompt, 'system');

        $this->assertContains('--tools=Read,Glob,Grep', $command);
        $this->assertContains('--verbose', $command);
        $this->assertSame(1, count(array_filter($command, fn ($token) => str_starts_with((string) $token, '--tools'))));
        $this->assertSame($prompt, $command[count($command) - 1]);
    }

    public function test_question_schema_requires_non_empty_reasoning(): void
    {
        $adapter = new ClaudeAdapter;
        $session = new InterrogationSession([
            'runner_type' => 'claude',
            'cli_session_id' => null,
        ]);

        $command = $adapter->buildQuestionCommand($session, 'Ask next question', 'system');
        $schemaFlagIndex = array_search('--json-schema', $command, true);

        $this->assertNotFalse($schemaFlagIndex);

        $schema = json_decode((string) ($command[(int) $schemaFlagIndex + 1] ?? '{}'), true);

        $this->assertIsArray($schema);
        $this->assertContains('reasoning', $schema['required'] ?? []);
        $this->assertSame(1, (int) ($schema['properties']['reasoning']['minLength'] ?? 0));
    }
}
