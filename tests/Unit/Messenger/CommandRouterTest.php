<?php

declare(strict_types=1);

namespace Tests\Unit\Messenger;

use App\Services\Messenger\CommandRouter;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Unit tests for CommandRouter slash command parsing and routing.
 *
 * These tests verify:
 * - Command detection via isCommand()
 * - Command name and argument parsing via parseCommand()
 * - Regular messages are not detected as commands
 */
final class CommandRouterTest extends TestCase
{
    private CommandRouter $router;

    protected function setUp(): void
    {
        parent::setUp();
        $this->router = app(CommandRouter::class);
    }

    #[Test]
    public function is_command_returns_true_for_slash_commands(): void
    {
        $this->assertTrue($this->router->isCommand('/status'));
        $this->assertTrue($this->router->isCommand('/runs active'));
        $this->assertTrue($this->router->isCommand('/sessions stop session-123'));
        $this->assertTrue($this->router->isCommand('/jobs list'));
        $this->assertTrue($this->router->isCommand('/mode safe'));
        $this->assertTrue($this->router->isCommand('/approve abc123'));
        $this->assertTrue($this->router->isCommand('/deny abc123'));
        $this->assertTrue($this->router->isCommand('/browser start'));
        $this->assertTrue($this->router->isCommand('/ask what is the weather'));
    }

    #[Test]
    public function is_command_returns_false_for_regular_messages(): void
    {
        $this->assertFalse($this->router->isCommand('Hello, help me with a task'));
        $this->assertFalse($this->router->isCommand('status of my jobs'));
        $this->assertFalse($this->router->isCommand(''));
    }

    #[Test]
    public function is_command_handles_whitespace(): void
    {
        $this->assertTrue($this->router->isCommand('  /status'));
        $this->assertTrue($this->router->isCommand("\t/mode safe"));
        $this->assertFalse($this->router->isCommand('   '));
    }

    #[Test]
    public function parse_command_extracts_name_and_args(): void
    {
        [$name, $args] = $this->router->parseCommand('/mode standard');
        $this->assertEquals('mode', $name);
        $this->assertEquals(['standard'], $args);

        [$name, $args] = $this->router->parseCommand('/approve abc-123-def');
        $this->assertEquals('approve', $name);
        $this->assertEquals(['abc-123-def'], $args);
    }

    #[Test]
    public function parse_command_handles_no_args(): void
    {
        [$name, $args] = $this->router->parseCommand('/status');
        $this->assertEquals('status', $name);
        $this->assertEquals([], $args);
    }

    #[Test]
    public function parse_command_handles_multiple_args(): void
    {
        [$name, $args] = $this->router->parseCommand('/browser start --headless');
        $this->assertEquals('browser', $name);
        $this->assertEquals(['start', '--headless'], $args);
    }

    #[Test]
    public function parse_command_handles_whitespace_in_content(): void
    {
        [$name, $args] = $this->router->parseCommand('  /mode   safe');
        $this->assertEquals('mode', $name);
        $this->assertEquals(['safe'], $args);
    }
}
