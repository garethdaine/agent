<?php

namespace App\Services\Runtime\Adapters;

use App\Contracts\Runtime\ToolAdapterInterface;
use App\DTOs\Runtime\RuntimeContext;

abstract class AbstractToolAdapter implements ToolAdapterInterface
{
    /**
     * Check if the tool call is authorized based on mode capabilities.
     */
    public function authorize(RuntimeContext $context, array $args): bool
    {
        $modeConfig = config("runtime.modes.{$context->mode->value}");

        if ($modeConfig === null) {
            return false;
        }

        $requiredCapability = $this->getRequiredCapability($args);

        // Wildcard capability allows everything
        if (in_array('*', $modeConfig['capabilities'] ?? [], true)) {
            return true;
        }

        // Check if the required capability is present
        if (! in_array($requiredCapability, $modeConfig['capabilities'] ?? [], true)) {
            return false;
        }

        return true;
    }

    /**
     * Get the capability required for the given arguments.
     * Override in subclasses for more specific capability requirements.
     */
    protected function getRequiredCapability(array $args): string
    {
        return 'read';
    }

    /**
     * Validate that a path is within the workspace root.
     * Uses realpath() to prevent directory traversal attacks.
     */
    protected function validatePathWithinWorkspace(RuntimeContext $context, string $path): bool
    {
        if ($context->workspaceRoot === null) {
            return false;
        }

        $realWorkspace = realpath($context->workspaceRoot);
        if ($realWorkspace === false) {
            return false;
        }

        // Handle existing paths
        $realPath = realpath($path);
        if ($realPath !== false) {
            return str_starts_with($realPath, $realWorkspace);
        }

        // Handle non-existent paths by walking up to find first existing ancestor
        $current = $path;
        $remainingPath = [];

        while (($realCurrent = realpath($current)) === false) {
            $remainingPath[] = basename($current);
            $parent = dirname($current);

            // Prevent infinite loop if we reach filesystem root
            if ($parent === $current) {
                return false;
            }

            $current = $parent;
        }

        // Reconstruct the full path from the real ancestor and remaining components
        $reconstructedPath = $realCurrent;
        foreach (array_reverse($remainingPath) as $component) {
            $reconstructedPath .= DIRECTORY_SEPARATOR.$component;
        }

        return str_starts_with($reconstructedPath, $realWorkspace);
    }
}
