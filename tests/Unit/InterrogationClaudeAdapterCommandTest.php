<?php

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
}
