<?php

declare(strict_types=1);

namespace Tests\Unit\Messenger;

use App\Messenger\ChatAction\ChatActionContext;
use App\Messenger\ChatAction\Handlers\GeneralTaskHandler;
use App\Models\User;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class GeneralTaskHandlerStreamingTest extends TestCase
{
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->make(['id' => 1]);
    }

    public function test_handle_streaming_delivers_chunks_via_callback(): void
    {
        Process::fake([
            '*' => Process::result(output: 'Hello, how can I help you today?'),
        ]);

        $handler = new GeneralTaskHandler;
        $context = new ChatActionContext(
            user: $this->user,
            parameters: ['task_description' => 'Say hello'],
            action: 'general.task',
        );

        $chunks = [];
        $result = $handler->handleStreaming($context, function (string $chunk) use (&$chunks) {
            $chunks[] = $chunk;
        });

        $this->assertTrue($result->isSuccess());
        $this->assertEquals('Hello, how can I help you today?', $result->getMessage());
        $this->assertNotEmpty($chunks);
    }

    public function test_handle_streaming_returns_failure_on_empty_task(): void
    {
        $handler = new GeneralTaskHandler;
        $context = new ChatActionContext(
            user: $this->user,
            parameters: ['task_description' => ''],
            action: 'general.task',
        );

        $chunks = [];
        $result = $handler->handleStreaming($context, function (string $chunk) use (&$chunks) {
            $chunks[] = $chunk;
        });

        $this->assertFalse($result->isSuccess());
        $this->assertStringContainsString('No task description', $result->getMessage());
        $this->assertEmpty($chunks);
    }

    public function test_handle_streaming_returns_failure_on_process_error(): void
    {
        Process::fake([
            '*' => Process::result(output: '', errorOutput: 'CLI error', exitCode: 1),
        ]);

        $handler = new GeneralTaskHandler;
        $context = new ChatActionContext(
            user: $this->user,
            parameters: ['task_description' => 'Do something'],
            action: 'general.task',
        );

        $chunks = [];
        $result = $handler->handleStreaming($context, function (string $chunk) use (&$chunks) {
            $chunks[] = $chunk;
        });

        $this->assertFalse($result->isSuccess());
        $this->assertStringContainsString('encountered an issue', $result->getMessage());
    }

    public function test_handle_streaming_returns_failure_on_empty_output(): void
    {
        Process::fake([
            '*' => Process::result(output: ''),
        ]);

        $handler = new GeneralTaskHandler;
        $context = new ChatActionContext(
            user: $this->user,
            parameters: ['task_description' => 'What is 1+1?'],
            action: 'general.task',
        );

        $chunks = [];
        $result = $handler->handleStreaming($context, function (string $chunk) use (&$chunks) {
            $chunks[] = $chunk;
        });

        $this->assertFalse($result->isSuccess());
        $this->assertStringContainsString('empty response', $result->getMessage());
    }

    public function test_handle_streaming_includes_attachment_context(): void
    {
        Process::fake([
            '*' => Process::result(output: 'I see you attached a config file.'),
        ]);

        $handler = new GeneralTaskHandler;
        $context = new ChatActionContext(
            user: $this->user,
            parameters: [
                'task_description' => 'Analyse this file',
                'attachment_context' => '- config.yaml (text/yaml, 1024 bytes)',
            ],
            action: 'general.task',
        );

        $chunks = [];
        $result = $handler->handleStreaming($context, function (string $chunk) use (&$chunks) {
            $chunks[] = $chunk;
        });

        $this->assertTrue($result->isSuccess());

        Process::assertRan(function ($process) {
            $command = implode(' ', $process->command);

            return str_contains($command, 'config.yaml');
        });
    }

    public function test_non_streaming_handle_still_works(): void
    {
        Process::fake([
            '*' => Process::result(output: 'Sync response'),
        ]);

        $handler = new GeneralTaskHandler;
        $context = new ChatActionContext(
            user: $this->user,
            parameters: ['task_description' => 'Hello'],
            action: 'general.task',
        );

        $result = $handler->handle($context);

        $this->assertTrue($result->isSuccess());
        $this->assertEquals('Sync response', $result->getMessage());
    }
}
