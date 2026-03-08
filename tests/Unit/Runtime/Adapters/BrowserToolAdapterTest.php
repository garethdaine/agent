<?php

declare(strict_types=1);

namespace Tests\Unit\Runtime\Adapters;

use App\DTOs\Runtime\RuntimeContext;
use App\Enums\Runtime\RuntimeMode;
use App\Models\Runtime\RuntimeSession;
use App\Models\Runtime\RuntimeTurn;
use App\Models\User;
use App\Services\Runtime\Adapters\BrowserToolAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class BrowserToolAdapterTest extends TestCase
{
    use RefreshDatabase;

    private BrowserToolAdapter $adapter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->adapter = new BrowserToolAdapter;
    }

    // --- name & schema ---

    public function test_name_returns_browser(): void
    {
        $this->assertEquals('browser', $this->adapter->name());
    }

    public function test_schema_has_description_and_command_parameter(): void
    {
        $schema = $this->adapter->schema();

        $this->assertArrayHasKey('description', $schema);
        $this->assertStringContainsString('agent-browser', $schema['description']);
        $this->assertArrayHasKey('parameters', $schema);
        $this->assertArrayHasKey('command', $schema['parameters']);
        $this->assertTrue($schema['parameters']['command']['required']);
    }

    // --- tokenizeCommand ---

    public function test_tokenize_simple_command(): void
    {
        $tokens = BrowserToolAdapter::tokenizeCommand('open https://example.com');
        $this->assertEquals(['open', 'https://example.com'], $tokens);
    }

    public function test_tokenize_double_quoted_string(): void
    {
        $tokens = BrowserToolAdapter::tokenizeCommand('fill @e3 "hello world"');
        $this->assertEquals(['fill', '@e3', 'hello world'], $tokens);
    }

    public function test_tokenize_single_quoted_string(): void
    {
        $tokens = BrowserToolAdapter::tokenizeCommand("type @e1 'some text'");
        $this->assertEquals(['type', '@e1', 'some text'], $tokens);
    }

    public function test_tokenize_escaped_quote_in_double_quotes(): void
    {
        $tokens = BrowserToolAdapter::tokenizeCommand('eval "document.querySelector(\\"h1\\").textContent"');
        $this->assertEquals(['eval', 'document.querySelector("h1").textContent'], $tokens);
    }

    public function test_tokenize_empty_string(): void
    {
        $tokens = BrowserToolAdapter::tokenizeCommand('');
        $this->assertEquals([], $tokens);
    }

    public function test_tokenize_extra_whitespace(): void
    {
        $tokens = BrowserToolAdapter::tokenizeCommand('  click   @e2  ');
        $this->assertEquals(['click', '@e2'], $tokens);
    }

    public function test_tokenize_flags(): void
    {
        $tokens = BrowserToolAdapter::tokenizeCommand('snapshot -i -c');
        $this->assertEquals(['snapshot', '-i', '-c'], $tokens);
    }

    public function test_tokenize_long_form_flags(): void
    {
        $tokens = BrowserToolAdapter::tokenizeCommand('screenshot --full --annotate page.png');
        $this->assertEquals(['screenshot', '--full', '--annotate', 'page.png'], $tokens);
    }

    // --- authorize ---

    public function test_authorize_read_command_in_safe_mode(): void
    {
        $context = $this->createContext(RuntimeMode::Safe);

        $this->assertTrue($this->adapter->authorize($context, ['command' => 'screenshot']));
        $this->assertTrue($this->adapter->authorize($context, ['command' => 'snapshot -i']));
        $this->assertTrue($this->adapter->authorize($context, ['command' => 'get text @e1']));
        $this->assertTrue($this->adapter->authorize($context, ['command' => 'is visible @e2']));
    }

    public function test_authorize_mutation_command_blocked_in_safe_mode(): void
    {
        $context = $this->createContext(RuntimeMode::Safe);

        $this->assertFalse($this->adapter->authorize($context, ['command' => 'open https://example.com']));
        $this->assertFalse($this->adapter->authorize($context, ['command' => 'click @e1']));
        $this->assertFalse($this->adapter->authorize($context, ['command' => 'fill @e3 test']));
        $this->assertFalse($this->adapter->authorize($context, ['command' => 'eval document.title']));
    }

    public function test_authorize_all_commands_in_standard_mode(): void
    {
        $context = $this->createContext(RuntimeMode::Standard);

        $this->assertTrue($this->adapter->authorize($context, ['command' => 'open https://example.com']));
        $this->assertTrue($this->adapter->authorize($context, ['command' => 'click @e1']));
        $this->assertTrue($this->adapter->authorize($context, ['command' => 'screenshot']));
        $this->assertTrue($this->adapter->authorize($context, ['command' => 'snapshot -i']));
    }

    public function test_authorize_all_commands_in_full_mode(): void
    {
        $context = $this->createContext(RuntimeMode::Full);

        $this->assertTrue($this->adapter->authorize($context, ['command' => 'open https://example.com']));
        $this->assertTrue($this->adapter->authorize($context, ['command' => 'fill @e3 hello']));
        $this->assertTrue($this->adapter->authorize($context, ['command' => 'screenshot --full']));
    }

    // --- execute ---

    public function test_execute_empty_command_fails(): void
    {
        $context = $this->createContext(RuntimeMode::Standard);
        $result = $this->adapter->execute($context, ['command' => '']);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('cannot be empty', $result->error);
    }

    public function test_execute_denied_command_fails(): void
    {
        config(['runtime.browser.denied_commands' => ['install', 'connect']]);

        $context = $this->createContext(RuntimeMode::Standard);
        $result = $this->adapter->execute($context, ['command' => 'install']);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('denied', $result->error);
    }

    public function test_execute_missing_binary_fails(): void
    {
        config(['runtime.browser.sidecar_binary' => '/nonexistent/binary']);

        $context = $this->createContext(RuntimeMode::Standard);
        $result = $this->adapter->execute($context, ['command' => 'open https://example.com']);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('not configured or not executable', $result->error);
    }

    public function test_execute_successful_command(): void
    {
        $binary = $this->createFakeBinary('echo "page loaded"');
        config([
            'runtime.browser.sidecar_binary' => $binary,
            'runtime.browser.denied_commands' => [],
        ]);

        Process::fake([
            '*' => Process::result(output: 'page loaded', exitCode: 0),
        ]);

        $context = $this->createContext(RuntimeMode::Standard);
        $result = $this->adapter->execute($context, ['command' => 'open https://example.com']);

        $this->assertTrue($result->success);
        $this->assertEquals('open', $result->data['command']);
        $this->assertEquals('page loaded', $result->data['output']);
    }

    public function test_execute_failed_process(): void
    {
        $binary = $this->createFakeBinary('');
        config([
            'runtime.browser.sidecar_binary' => $binary,
            'runtime.browser.denied_commands' => [],
        ]);

        Process::fake([
            '*' => Process::result(output: '', errorOutput: 'element not found', exitCode: 1),
        ]);

        $context = $this->createContext(RuntimeMode::Standard);
        $result = $this->adapter->execute($context, ['command' => 'click @e99']);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('element not found', $result->error);
    }

    public function test_execute_includes_headed_flag_when_configured(): void
    {
        $binary = $this->createFakeBinary('');
        config([
            'runtime.browser.sidecar_binary' => $binary,
            'runtime.browser.headed' => true,
            'runtime.browser.session_name' => null,
            'runtime.browser.denied_commands' => [],
        ]);

        Process::fake([
            '*' => Process::result(output: 'ok', exitCode: 0),
        ]);

        $context = $this->createContext(RuntimeMode::Standard);
        $this->adapter->execute($context, ['command' => 'open https://example.com']);

        Process::assertRan(function ($process) {
            $cmd = $process->command;

            return in_array('--headed', $cmd, true)
                && in_array('open', $cmd, true)
                && in_array('https://example.com', $cmd, true);
        });
    }

    public function test_execute_includes_session_when_configured(): void
    {
        $binary = $this->createFakeBinary('');
        config([
            'runtime.browser.sidecar_binary' => $binary,
            'runtime.browser.headed' => false,
            'runtime.browser.session_name' => 'my-session',
            'runtime.browser.denied_commands' => [],
        ]);

        Process::fake([
            '*' => Process::result(output: 'ok', exitCode: 0),
        ]);

        $context = $this->createContext(RuntimeMode::Standard);
        $this->adapter->execute($context, ['command' => 'snapshot -i']);

        Process::assertRan(function ($process) {
            $cmd = $process->command;

            return in_array('--session', $cmd, true)
                && in_array('my-session', $cmd, true)
                && in_array('snapshot', $cmd, true)
                && in_array('-i', $cmd, true);
        });
    }

    public function test_execute_uses_configured_timeout(): void
    {
        $binary = $this->createFakeBinary('');
        config([
            'runtime.browser.sidecar_binary' => $binary,
            'runtime.browser.timeout' => 120,
            'runtime.browser.denied_commands' => [],
        ]);

        Process::fake([
            '*' => Process::result(output: 'ok', exitCode: 0),
        ]);

        $context = $this->createContext(RuntimeMode::Standard);
        $this->adapter->execute($context, ['command' => 'open https://example.com']);

        Process::assertRan(fn ($process) => $process->timeout === 120);
    }

    // --- helpers ---

    private function createContext(RuntimeMode $mode): RuntimeContext
    {
        $session = RuntimeSession::factory()->make(['mode' => $mode, 'workspace_root' => '/tmp']);
        $turn = RuntimeTurn::factory()->make();
        $user = User::factory()->make();

        return new RuntimeContext(
            session: $session,
            turn: $turn,
            user: $user,
            mode: $mode,
            policy: config("runtime.modes.{$mode->value}"),
            workspaceRoot: '/tmp',
        );
    }

    /**
     * Create a temporary executable file to satisfy is_executable() checks.
     */
    private function createFakeBinary(string $content = ''): string
    {
        $path = sys_get_temp_dir().'/fake_agent_browser_'.uniqid();
        file_put_contents($path, "#!/bin/sh\n{$content}");
        chmod($path, 0755);

        // Clean up after test
        $this->afterTest(function () use ($path) {
            @unlink($path);
        });

        return $path;
    }

    /**
     * Register a callback to run after the test completes.
     */
    private function afterTest(\Closure $callback): void
    {
        $this->beforeApplicationDestroyed($callback);
    }
}
