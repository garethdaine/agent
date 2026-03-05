<?php

namespace Tests\Unit\Runtime;

use App\DTOs\Runtime\RuntimeContext;
use App\DTOs\Runtime\ToolResult;
use App\Enums\Runtime\RuntimeMode;
use App\Models\Runtime\RuntimeSession;
use App\Models\Runtime\RuntimeTurn;
use App\Models\User;
use PHPUnit\Framework\TestCase;

class ToolAdapterContractTest extends TestCase
{
    public function test_runtime_context_dto_has_required_properties(): void
    {
        $context = new RuntimeContext(
            session: $this->createMock(RuntimeSession::class),
            turn: $this->createMock(RuntimeTurn::class),
            user: $this->createMock(User::class),
            mode: RuntimeMode::Safe,
            policy: ['capabilities' => ['read']],
            workspaceRoot: '/tmp/workspace'
        );

        $this->assertInstanceOf(RuntimeSession::class, $context->session);
        $this->assertEquals(RuntimeMode::Safe, $context->mode);
        $this->assertEquals('/tmp/workspace', $context->workspaceRoot);
    }

    public function test_tool_result_dto_success_factory(): void
    {
        $result = ToolResult::success(
            data: ['content' => 'file contents'],
            durationMs: 150
        );

        $this->assertTrue($result->success);
        $this->assertEquals(['content' => 'file contents'], $result->data);
        $this->assertEquals(150, $result->durationMs);
        $this->assertNull($result->error);
    }

    public function test_tool_result_dto_failure_factory(): void
    {
        $result = ToolResult::failure(
            error: 'Permission denied',
            durationMs: 50
        );

        $this->assertFalse($result->success);
        $this->assertEquals('Permission denied', $result->error);
    }
}
