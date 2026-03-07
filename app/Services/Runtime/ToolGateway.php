<?php

namespace App\Services\Runtime;

use App\Contracts\Runtime\ToolAdapterInterface;
use App\DTOs\Runtime\RuntimeContext;
use App\DTOs\Runtime\ToolResult;
use App\Enums\Runtime\RuntimeToolCallStatus;
use App\Models\Runtime\RuntimeToolCall;
use App\Support\Agent\AuditLogger;

class ToolGateway
{
    /**
     * Registered tool adapters indexed by name.
     *
     * @var array<string, ToolAdapterInterface>
     */
    private array $adapters = [];

    public function __construct(
        private PolicyEngine $policyEngine,
        private ApprovalGate $approvalGate,
        private AuditLogger $auditLogger,
    ) {}

    /**
     * Register a tool adapter with the gateway.
     *
     * Adapters are indexed by their name() value for fast lookup during
     * tool call routing.
     */
    public function register(ToolAdapterInterface $adapter): void
    {
        $this->adapters[$adapter->name()] = $adapter;
    }

    /**
     * Check if an adapter is registered for the given tool name.
     */
    public function hasAdapter(string $name): bool
    {
        return isset($this->adapters[$name]);
    }

    /**
     * Get tool definitions for all registered adapters in Anthropic Messages API format.
     *
     * @return array<int, array{name: string, description: string, input_schema: array}>
     */
    public function getToolSchemasForLlm(): array
    {
        $tools = [];
        foreach ($this->adapters as $adapter) {
            $name = $adapter->name();
            if (! $this->policyEngine->isToolAllowedByPolicy($name, $name)) {
                continue;
            }
            $raw = $adapter->schema();
            $operations = $raw['operations'] ?? [];
            $params = $raw['parameters'] ?? [];
            if (isset($raw['description']) && $raw['description'] !== '') {
                $description = $raw['description'];
            } else {
                $description = 'Tool: '.$adapter->name();
                if ($operations !== []) {
                    $description .= '. Operations: '.implode(', ', $operations);
                }
            }
            $properties = [];
            $required = [];
            foreach ($params as $key => $config) {
                if (! is_array($config)) {
                    continue;
                }
                $properties[$key] = [
                    'type' => $config['type'] ?? 'string',
                    'description' => $config['description'] ?? $key,
                ];
                if (! empty($config['required']) || ! empty($config['required_for'])) {
                    $required[] = $key;
                }
            }
            $tools[] = [
                'name' => $name,
                'description' => $description,
                'input_schema' => [
                    'type' => 'object',
                    'properties' => $properties,
                    'required' => array_values(array_unique($required)),
                ],
            ];
        }

        return $tools;
    }

    /**
     * Execute a tool call through the gateway.
     *
     * This method handles:
     * - Recording the tool call attempt
     * - Checking adapter existence
     * - Checking capability authorization via adapter
     * - Checking if approval is required via ApprovalGate
     * - Executing the tool or blocking for approval
     * - Recording the result
     *
     * @param  string  $toolName  The name of the tool to call
     * @param  RuntimeContext  $context  The runtime context for this call
     * @param  array  $args  Arguments to pass to the tool
     * @return ToolResult The result of the tool call
     */
    public function call(string $toolName, RuntimeContext $context, array $args): ToolResult
    {
        $startTime = hrtime(true);

        // Record tool call attempt
        $toolCall = $this->createToolCallRecord($context, $toolName, $args);

        // Check if adapter exists
        if (! $this->hasAdapter($toolName)) {
            return $this->recordFailure($toolCall, "Unknown tool: {$toolName}", $startTime);
        }

        $qualifiedName = $this->resolveQualifiedToolName($toolName, $args);
        if (! $this->policyEngine->isToolAllowedByPolicy($toolName, $qualifiedName)) {
            return $this->recordFailure($toolCall, "Tool {$toolName} is denied by runtime policy (tool_deny / tool_allow)", $startTime);
        }

        $adapter = $this->adapters[$toolName];

        // Check capability authorization via adapter
        if (! $adapter->authorize($context, $args)) {
            return $this->recordFailure(
                $toolCall,
                "Tool {$toolName} not allowed in {$context->mode->value} mode",
                $startTime
            );
        }

        // Check if approval is required via ApprovalGate
        if ($this->approvalGate->requiresApproval($context->mode, $qualifiedName, $args)) {
            $toolCall->update([
                'status' => RuntimeToolCallStatus::PendingApproval,
                'requires_approval' => true,
            ]);

            $this->approvalGate->createApprovalRequest($toolCall, $context->user);

            $this->auditLogger->recordSystemAction(
                'runtime.tool_call.pending_approval',
                'runtime_tool_call',
                $toolCall->id,
                ['status', 'requires_approval'],
            );

            return ToolResult::success(
                ['pending_approval' => true, 'tool_call_id' => $toolCall->id],
                $this->calculateDuration($startTime)
            );
        }

        return $this->executeTool($adapter, $toolCall, $context, $args, $startTime);
    }

    /**
     * Execute a previously-approved tool call.
     *
     * Called after an approval decision grants execution. Validates that the
     * tool call is in Approved status, then delegates to the adapter.
     */
    public function executeApproved(RuntimeToolCall $toolCall, RuntimeContext $context): ToolResult
    {
        $startTime = hrtime(true);

        if ($toolCall->status !== RuntimeToolCallStatus::Approved) {
            return ToolResult::failure(
                "Tool call {$toolCall->id} is not in Approved status (current: {$toolCall->status->value})",
                $this->calculateDuration($startTime)
            );
        }

        $toolName = $toolCall->tool_name;

        if (! $this->hasAdapter($toolName)) {
            return $this->recordFailure($toolCall, "Unknown tool: {$toolName}", $startTime);
        }

        $args = (array) ($toolCall->arguments_json ?? []);
        $qualifiedName = $this->resolveQualifiedToolName($toolName, $args);
        if (! $this->policyEngine->isToolAllowedByPolicy($toolName, $qualifiedName)) {
            return $this->recordFailure($toolCall, "Tool {$toolName} is denied by runtime policy", $startTime);
        }

        $adapter = $this->adapters[$toolName];

        $this->auditLogger->recordSystemAction(
            'runtime.tool_call.execute_approved',
            'runtime_tool_call',
            $toolCall->id,
            ['status'],
        );

        return $this->executeTool($adapter, $toolCall, $context, $args, $startTime);
    }

    /**
     * Execute a tool call through the registered adapter.
     */
    private function executeTool(
        ToolAdapterInterface $adapter,
        RuntimeToolCall $toolCall,
        RuntimeContext $context,
        array $args,
        int $startTime
    ): ToolResult {
        $toolCall->update(['status' => RuntimeToolCallStatus::Running]);

        try {
            $result = $adapter->execute($context, $args);
            $this->recordSuccess($toolCall, $result, $startTime);

            $this->auditLogger->recordSystemAction(
                'runtime.tool_call.completed',
                'runtime_tool_call',
                $toolCall->id,
                ['status', 'result_json', 'duration_ms'],
            );

            return $result;
        } catch (\Throwable $e) {
            $this->auditLogger->recordSystemAction(
                'runtime.tool_call.failed',
                'runtime_tool_call',
                $toolCall->id,
                ['status', 'result_json', 'duration_ms'],
                outcome: 'failure',
                errorMessage: $e->getMessage(),
            );

            return $this->recordFailure($toolCall, $e->getMessage(), $startTime);
        }
    }

    /**
     * Resolve a qualified tool name from adapter name and args.
     *
     * Adapters that multiplex operations (e.g. 'fs' with 'operation' arg)
     * are resolved to their dotted form (e.g. 'fs.write') so ApprovalGate
     * tool lists match correctly.
     */
    private function resolveQualifiedToolName(string $toolName, array $args): string
    {
        if (isset($args['operation']) && is_string($args['operation']) && $args['operation'] !== '') {
            return $toolName.'.'.$args['operation'];
        }

        return $toolName;
    }

    /**
     * Create the initial tool call record in Running status.
     */
    private function createToolCallRecord(RuntimeContext $context, string $toolName, array $args): RuntimeToolCall
    {
        return RuntimeToolCall::create([
            'runtime_turn_id' => $context->turn->id,
            'tool_name' => $toolName,
            'arguments_json' => $args,
            'status' => RuntimeToolCallStatus::Running,
            'requires_approval' => false,
        ]);
    }

    /**
     * Record a successful tool execution.
     */
    private function recordSuccess(RuntimeToolCall $toolCall, ToolResult $result, int $startTime): void
    {
        $toolCall->update([
            'status' => RuntimeToolCallStatus::Completed,
            'result_json' => $result->data,
            'duration_ms' => $this->calculateDuration($startTime),
        ]);
    }

    /**
     * Record a failed tool execution and return a failure result.
     */
    private function recordFailure(RuntimeToolCall $toolCall, string $error, int $startTime): ToolResult
    {
        $duration = $this->calculateDuration($startTime);

        $toolCall->update([
            'status' => RuntimeToolCallStatus::Failed,
            'result_json' => ['error' => $error],
            'duration_ms' => $duration,
        ]);

        return ToolResult::failure($error, $duration);
    }

    /**
     * Calculate duration in milliseconds from hrtime start.
     */
    private function calculateDuration(int $startTime): int
    {
        return (int) ((hrtime(true) - $startTime) / 1_000_000);
    }
}
