<?php

declare(strict_types=1);

namespace App\Services\Runtime\Adapters;

use App\DTOs\Runtime\RuntimeContext;
use App\DTOs\Runtime\ToolResult;
use App\Enums\Runtime\RuntimeMode;

/**
 * MCP (Model Context Protocol) tool adapter - forwards tool calls to an external MCP server via stdio.
 *
 * Feature-flagged via config('runtime.mcp.enabled'). When disabled, all calls are rejected.
 * When enabled, requires runtime.mcp.server_command or server_path to be configured.
 */
class McpToolAdapter extends AbstractToolAdapter
{
    public function name(): string
    {
        return 'mcp';
    }

    public function schema(): array
    {
        return [
            'operations' => ['call'],
            'parameters' => [
                'operation' => ['type' => 'string', 'required' => true],
                'tool_name' => ['type' => 'string', 'required' => true],
                'arguments' => ['type' => 'object', 'optional' => true],
            ],
        ];
    }

    public function authorize(RuntimeContext $context, array $args): bool
    {
        if (! config('runtime.mcp.enabled', false)) {
            return false;
        }

        if ($context->mode === RuntimeMode::Safe) {
            return false;
        }

        return parent::authorize($context, $args);
    }

    protected function getRequiredCapability(array $args): string
    {
        return 'query';
    }

    public function execute(RuntimeContext $context, array $args): ToolResult
    {
        $startTime = hrtime(true);

        if (! config('runtime.mcp.enabled', false)) {
            return ToolResult::failure(
                'MCP integration is disabled (set MCP_ENABLED=true to enable)',
                $this->duration($startTime)
            );
        }

        $serverCommand = config('runtime.mcp.server_command');
        if ($serverCommand === null || $serverCommand === '') {
            return ToolResult::failure(
                'MCP server not configured (runtime.mcp.server_command)',
                $this->duration($startTime)
            );
        }

        return ToolResult::failure(
            'MCP stdio tool execution is not yet implemented; server command is configured.',
            $this->duration($startTime)
        );
    }

    private function duration(int $startTime): int
    {
        return (int) ((hrtime(true) - $startTime) / 1_000_000);
    }
}
