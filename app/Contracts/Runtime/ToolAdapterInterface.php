<?php

namespace App\Contracts\Runtime;

use App\DTOs\Runtime\RuntimeContext;
use App\DTOs\Runtime\ToolResult;

interface ToolAdapterInterface
{
    /**
     * Get the unique name for this tool adapter.
     */
    public function name(): string;

    /**
     * Get the JSON schema describing the tool's parameters.
     */
    public function schema(): array;

    /**
     * Check if the tool call is authorized in the given context.
     */
    public function authorize(RuntimeContext $context, array $args): bool;

    /**
     * Execute the tool with the given arguments.
     */
    public function execute(RuntimeContext $context, array $args): ToolResult;
}
