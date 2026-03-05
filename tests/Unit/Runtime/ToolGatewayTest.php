<?php

namespace Tests\Unit\Runtime;

use App\Contracts\Runtime\ToolAdapterInterface;
use App\DTOs\Runtime\RuntimeContext;
use App\DTOs\Runtime\ToolResult;
use App\Enums\Runtime\RuntimeMode;
use App\Enums\Runtime\RuntimeToolCallStatus;
use App\Models\Runtime\RuntimeSession;
use App\Models\Runtime\RuntimeToolCall;
use App\Models\Runtime\RuntimeTurn;
use App\Services\Runtime\ToolGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ToolGatewayTest extends TestCase
{
    use RefreshDatabase;

    private ToolGateway $gateway;

    protected function setUp(): void
    {
        parent::setUp();
        $this->gateway = app(ToolGateway::class);
    }

    public function test_register_adapter_adds_to_registry(): void
    {
        $adapter = $this->createMock(ToolAdapterInterface::class);
        $adapter->method('name')->willReturn('fs.test');

        $this->gateway->register($adapter);

        $this->assertTrue($this->gateway->hasAdapter('fs.test'));
    }

    public function test_has_adapter_returns_false_for_unregistered_adapter(): void
    {
        $this->assertFalse($this->gateway->hasAdapter('nonexistent.adapter'));
    }

    public function test_call_creates_tool_call_record(): void
    {
        $session = RuntimeSession::factory()->create(['mode' => RuntimeMode::Safe]);
        $turn = RuntimeTurn::factory()->create(['runtime_session_id' => $session->id]);
        $context = $this->createContext($session, $turn);

        // Register a mock adapter that authorizes and executes successfully
        $adapter = $this->createMock(ToolAdapterInterface::class);
        $adapter->method('name')->willReturn('fs.read');
        $adapter->method('authorize')->willReturn(true);
        $adapter->method('execute')->willReturn(ToolResult::success(['content' => 'test'], 10));
        $this->gateway->register($adapter);

        $result = $this->gateway->call('fs.read', $context, ['path' => '/tmp/test.txt']);

        $toolCall = RuntimeToolCall::where('runtime_turn_id', $turn->id)->first();
        $this->assertNotNull($toolCall);
        $this->assertEquals('fs.read', $toolCall->tool_name);
    }

    public function test_call_returns_failure_for_unknown_tool(): void
    {
        $session = RuntimeSession::factory()->create(['mode' => RuntimeMode::Safe]);
        $turn = RuntimeTurn::factory()->create(['runtime_session_id' => $session->id]);
        $context = $this->createContext($session, $turn);

        $result = $this->gateway->call('unknown.tool', $context, ['arg' => 'value']);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Unknown tool', $result->error);
    }

    public function test_call_blocks_unauthorized_capability(): void
    {
        $session = RuntimeSession::factory()->create(['mode' => RuntimeMode::Safe]);
        $turn = RuntimeTurn::factory()->create(['runtime_session_id' => $session->id]);
        $context = $this->createContext($session, $turn);

        // Register an adapter that denies authorization (e.g., write in safe mode)
        $adapter = $this->createMock(ToolAdapterInterface::class);
        $adapter->method('name')->willReturn('fs.write');
        $adapter->method('authorize')->willReturn(false);
        $this->gateway->register($adapter);

        $result = $this->gateway->call('fs.write', $context, ['path' => '/tmp/test.txt', 'content' => 'test']);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('not allowed', $result->error);
    }

    public function test_call_requires_approval_in_standard_mode(): void
    {
        $session = RuntimeSession::factory()->create(['mode' => RuntimeMode::Standard]);
        $turn = RuntimeTurn::factory()->create(['runtime_session_id' => $session->id]);
        $context = $this->createContext($session, $turn);

        // Register an adapter that authorizes the call
        $adapter = $this->createMock(ToolAdapterInterface::class);
        $adapter->method('name')->willReturn('fs.write');
        $adapter->method('authorize')->willReturn(true);
        $this->gateway->register($adapter);

        $result = $this->gateway->call('fs.write', $context, ['path' => '/tmp/test.txt', 'content' => 'test']);

        $toolCall = RuntimeToolCall::where('runtime_turn_id', $turn->id)->first();
        $this->assertEquals(RuntimeToolCallStatus::PendingApproval, $toolCall->status);
        $this->assertTrue($toolCall->requires_approval);
    }

    public function test_call_executes_tool_when_no_approval_required(): void
    {
        $session = RuntimeSession::factory()->create(['mode' => RuntimeMode::Safe]);
        $turn = RuntimeTurn::factory()->create(['runtime_session_id' => $session->id]);
        $context = $this->createContext($session, $turn);

        // Register an adapter that authorizes and executes successfully
        $adapter = $this->createMock(ToolAdapterInterface::class);
        $adapter->method('name')->willReturn('fs.read');
        $adapter->method('authorize')->willReturn(true);
        $adapter->method('execute')->willReturn(ToolResult::success(['content' => 'file content'], 5));
        $this->gateway->register($adapter);

        $result = $this->gateway->call('fs.read', $context, ['path' => '/tmp/test.txt']);

        $this->assertTrue($result->success);
        $this->assertEquals(['content' => 'file content'], $result->data);

        $toolCall = RuntimeToolCall::where('runtime_turn_id', $turn->id)->first();
        $this->assertEquals(RuntimeToolCallStatus::Completed, $toolCall->status);
    }

    public function test_call_records_duration_ms(): void
    {
        $session = RuntimeSession::factory()->create(['mode' => RuntimeMode::Safe]);
        $turn = RuntimeTurn::factory()->create(['runtime_session_id' => $session->id]);
        $context = $this->createContext($session, $turn);

        $adapter = $this->createMock(ToolAdapterInterface::class);
        $adapter->method('name')->willReturn('fs.read');
        $adapter->method('authorize')->willReturn(true);
        $adapter->method('execute')->willReturn(ToolResult::success(['content' => 'test'], 15));
        $this->gateway->register($adapter);

        $this->gateway->call('fs.read', $context, ['path' => '/tmp/test.txt']);

        $toolCall = RuntimeToolCall::where('runtime_turn_id', $turn->id)->first();
        $this->assertNotNull($toolCall->duration_ms);
        $this->assertIsInt($toolCall->duration_ms);
        $this->assertGreaterThanOrEqual(0, $toolCall->duration_ms);
    }

    public function test_call_records_failure_when_adapter_throws(): void
    {
        $session = RuntimeSession::factory()->create(['mode' => RuntimeMode::Safe]);
        $turn = RuntimeTurn::factory()->create(['runtime_session_id' => $session->id]);
        $context = $this->createContext($session, $turn);

        $adapter = $this->createMock(ToolAdapterInterface::class);
        $adapter->method('name')->willReturn('fs.read');
        $adapter->method('authorize')->willReturn(true);
        $adapter->method('execute')->willThrowException(new \RuntimeException('File not found'));
        $this->gateway->register($adapter);

        $result = $this->gateway->call('fs.read', $context, ['path' => '/nonexistent/file.txt']);

        $this->assertFalse($result->success);
        $this->assertEquals('File not found', $result->error);

        $toolCall = RuntimeToolCall::where('runtime_turn_id', $turn->id)->first();
        $this->assertEquals(RuntimeToolCallStatus::Failed, $toolCall->status);
    }

    public function test_call_stores_arguments_json(): void
    {
        $session = RuntimeSession::factory()->create(['mode' => RuntimeMode::Safe]);
        $turn = RuntimeTurn::factory()->create(['runtime_session_id' => $session->id]);
        $context = $this->createContext($session, $turn);

        $adapter = $this->createMock(ToolAdapterInterface::class);
        $adapter->method('name')->willReturn('fs.read');
        $adapter->method('authorize')->willReturn(true);
        $adapter->method('execute')->willReturn(ToolResult::success(['content' => 'test'], 5));
        $this->gateway->register($adapter);

        $args = ['path' => '/tmp/test.txt', 'encoding' => 'utf-8'];
        $this->gateway->call('fs.read', $context, $args);

        $toolCall = RuntimeToolCall::where('runtime_turn_id', $turn->id)->first();
        $this->assertEquals($args, $toolCall->arguments_json);
    }

    public function test_call_stores_result_json_on_success(): void
    {
        $session = RuntimeSession::factory()->create(['mode' => RuntimeMode::Safe]);
        $turn = RuntimeTurn::factory()->create(['runtime_session_id' => $session->id]);
        $context = $this->createContext($session, $turn);

        $adapter = $this->createMock(ToolAdapterInterface::class);
        $adapter->method('name')->willReturn('fs.read');
        $adapter->method('authorize')->willReturn(true);
        $adapter->method('execute')->willReturn(ToolResult::success(['content' => 'hello world'], 5));
        $this->gateway->register($adapter);

        $this->gateway->call('fs.read', $context, ['path' => '/tmp/test.txt']);

        $toolCall = RuntimeToolCall::where('runtime_turn_id', $turn->id)->first();
        $this->assertEquals(['content' => 'hello world'], $toolCall->result_json);
    }

    public function test_call_creates_approval_request_when_approval_required(): void
    {
        $session = RuntimeSession::factory()->create(['mode' => RuntimeMode::Standard]);
        $turn = RuntimeTurn::factory()->create(['runtime_session_id' => $session->id]);
        $context = $this->createContext($session, $turn);

        $adapter = $this->createMock(ToolAdapterInterface::class);
        $adapter->method('name')->willReturn('fs.write');
        $adapter->method('authorize')->willReturn(true);
        $this->gateway->register($adapter);

        $result = $this->gateway->call('fs.write', $context, ['path' => '/tmp/test.txt', 'content' => 'test']);

        // Verify approval request was created
        $toolCall = RuntimeToolCall::where('runtime_turn_id', $turn->id)->first();
        $this->assertNotNull($toolCall->approval);
        $this->assertEquals($session->user_id, $toolCall->approval->requested_by);
    }

    public function test_call_returns_pending_approval_data(): void
    {
        $session = RuntimeSession::factory()->create(['mode' => RuntimeMode::Standard]);
        $turn = RuntimeTurn::factory()->create(['runtime_session_id' => $session->id]);
        $context = $this->createContext($session, $turn);

        $adapter = $this->createMock(ToolAdapterInterface::class);
        $adapter->method('name')->willReturn('fs.write');
        $adapter->method('authorize')->willReturn(true);
        $this->gateway->register($adapter);

        $result = $this->gateway->call('fs.write', $context, ['path' => '/tmp/test.txt', 'content' => 'test']);

        $this->assertTrue($result->success);
        $this->assertArrayHasKey('pending_approval', $result->data);
        $this->assertTrue($result->data['pending_approval']);
        $this->assertArrayHasKey('tool_call_id', $result->data);
    }

    public function test_call_returns_failure_when_tool_denied_by_policy(): void
    {
        config(['runtime.tool_deny' => ['fs.write'], 'runtime.tool_allow' => []]);

        $session = RuntimeSession::factory()->create(['mode' => RuntimeMode::Standard]);
        $turn = RuntimeTurn::factory()->create(['runtime_session_id' => $session->id]);
        $context = $this->createContext($session, $turn);

        $adapter = $this->createMock(ToolAdapterInterface::class);
        $adapter->method('name')->willReturn('fs');
        $adapter->method('authorize')->willReturn(true);
        $this->gateway->register($adapter);

        $result = $this->gateway->call('fs', $context, ['operation' => 'write', 'path' => '/tmp/out.txt']);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('denied by runtime policy', $result->error);
    }

    private function createContext(RuntimeSession $session, RuntimeTurn $turn): RuntimeContext
    {
        return new RuntimeContext(
            session: $session,
            turn: $turn,
            user: $session->user,
            mode: $session->mode,
            policy: config("runtime.modes.{$session->mode->value}"),
            workspaceRoot: $session->workspace_root
        );
    }
}
