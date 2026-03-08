<?php

declare(strict_types=1);

namespace App\Services\Runtime\Adapters;

use App\DTOs\Runtime\RuntimeContext;
use App\DTOs\Runtime\ToolResult;
use App\Enums\Runtime\RuntimeMode;

/**
 * Filesystem tool adapter with workspace boundary enforcement.
 *
 * Supports operations: read, write, edit, delete, list, move
 *
 * Security constraints:
 * - All paths validated against workspace_root
 * - Directory traversal attacks blocked via realpath()
 * - Mutation operations (write, edit, delete, move) require 'write' capability
 * - Read/list operations only require 'read' capability
 *
 * Mode behavior:
 * - Safe mode: Only read/list operations allowed
 * - Standard mode: All operations allowed (mutations require approval at gateway level)
 * - Full mode: All operations allowed
 */
class FsToolAdapter extends AbstractToolAdapter
{
    /**
     * Operations that mutate filesystem state.
     */
    private const MUTATION_OPERATIONS = ['write', 'edit', 'delete', 'move'];

    /**
     * Operations that only read filesystem state.
     */
    private const READ_OPERATIONS = ['read', 'list']; // @phpstan-ignore classConstant.unused

    public function name(): string
    {
        return 'fs';
    }

    public function schema(): array
    {
        return [
            'operations' => ['read', 'write', 'edit', 'delete', 'list', 'move'],
            'parameters' => [
                'operation' => ['type' => 'string', 'required' => true],
                'path' => ['type' => 'string', 'required' => true],
                'content' => ['type' => 'string', 'required_for' => ['write']],
                'search' => ['type' => 'string', 'required_for' => ['edit']],
                'replace' => ['type' => 'string', 'required_for' => ['edit']],
                'destination' => ['type' => 'string', 'required_for' => ['move']],
            ],
        ];
    }

    /**
     * Check if the operation is authorized based on mode capabilities.
     *
     * Safe mode: Only read/list operations allowed (no mutations)
     * Standard/Full mode: All operations allowed (mutations may require approval upstream)
     */
    public function authorize(RuntimeContext $context, array $args): bool
    {
        $operation = $args['operation'] ?? 'read';

        // Safe mode: mutations are not authorized
        if ($context->mode === RuntimeMode::Safe && in_array($operation, self::MUTATION_OPERATIONS, true)) {
            return false;
        }

        return parent::authorize($context, $args);
    }

    /**
     * Get the capability required for the given operation.
     */
    protected function getRequiredCapability(array $args): string
    {
        $operation = $args['operation'] ?? 'read';

        if (in_array($operation, self::MUTATION_OPERATIONS, true)) {
            return 'write';
        }

        return 'read';
    }

    /**
     * Execute the filesystem operation.
     */
    public function execute(RuntimeContext $context, array $args): ToolResult
    {
        $startTime = hrtime(true);
        $operation = $args['operation'] ?? 'read';
        $path = $args['path'] ?? '';

        // Validate path within workspace
        if (! $this->validatePathWithinWorkspace($context, $path)) {
            return ToolResult::failure(
                "Path {$path} is outside workspace {$context->workspaceRoot}",
                $this->duration($startTime)
            );
        }

        return match ($operation) {
            'read' => $this->read($path, $startTime),
            'write' => $this->write($path, $args['content'] ?? '', $startTime),
            'edit' => $this->edit($path, $args['search'] ?? '', $args['replace'] ?? '', $startTime),
            'delete' => $this->delete($path, $startTime),
            'list' => $this->list($path, $startTime),
            'move' => $this->move($path, $args['destination'] ?? '', $context, $startTime),
            default => ToolResult::failure("Unknown operation: {$operation}", $this->duration($startTime)),
        };
    }

    /**
     * Read file contents.
     */
    private function read(string $path, int $startTime): ToolResult
    {
        if (! file_exists($path)) {
            return ToolResult::failure("File not found: {$path}", $this->duration($startTime));
        }

        if (! is_file($path)) {
            return ToolResult::failure("Path is not a file: {$path}", $this->duration($startTime));
        }

        $content = file_get_contents($path);

        if ($content === false) {
            return ToolResult::failure("Failed to read file: {$path}", $this->duration($startTime));
        }

        return ToolResult::success([
            'content' => $content,
            'path' => $path,
            'size' => strlen($content),
        ], $this->duration($startTime));
    }

    /**
     * Write content to a file, creating directories as needed.
     */
    private function write(string $path, string $content, int $startTime): ToolResult
    {
        $dir = dirname($path);

        if (! is_dir($dir)) {
            if (! mkdir($dir, 0755, true)) {
                return ToolResult::failure("Failed to create directory: {$dir}", $this->duration($startTime));
            }
        }

        $bytesWritten = file_put_contents($path, $content);

        if ($bytesWritten === false) {
            return ToolResult::failure("Failed to write file: {$path}", $this->duration($startTime));
        }

        return ToolResult::success([
            'path' => $path,
            'bytes_written' => $bytesWritten,
        ], $this->duration($startTime));
    }

    /**
     * Edit a file by searching and replacing content.
     */
    private function edit(string $path, string $search, string $replace, int $startTime): ToolResult
    {
        if (! file_exists($path)) {
            return ToolResult::failure("File not found: {$path}", $this->duration($startTime));
        }

        if (! is_file($path)) {
            return ToolResult::failure("Path is not a file: {$path}", $this->duration($startTime));
        }

        $content = file_get_contents($path);

        if ($content === false) {
            return ToolResult::failure("Failed to read file: {$path}", $this->duration($startTime));
        }

        // Check if search pattern exists
        if (strpos($content, $search) === false) {
            return ToolResult::failure(
                "Search pattern not found in file: {$path}",
                $this->duration($startTime)
            );
        }

        $newContent = str_replace($search, $replace, $content, $count);

        $bytesWritten = file_put_contents($path, $newContent);

        if ($bytesWritten === false) {
            return ToolResult::failure("Failed to write file: {$path}", $this->duration($startTime));
        }

        return ToolResult::success([
            'path' => $path,
            'replacements' => $count,
            'bytes_written' => $bytesWritten,
        ], $this->duration($startTime));
    }

    /**
     * Delete a file.
     */
    private function delete(string $path, int $startTime): ToolResult
    {
        if (! file_exists($path)) {
            return ToolResult::failure("File not found: {$path}", $this->duration($startTime));
        }

        if (! is_file($path)) {
            return ToolResult::failure("Path is not a file: {$path}", $this->duration($startTime));
        }

        if (! unlink($path)) {
            return ToolResult::failure("Failed to delete file: {$path}", $this->duration($startTime));
        }

        return ToolResult::success([
            'path' => $path,
            'deleted' => true,
        ], $this->duration($startTime));
    }

    /**
     * List directory contents.
     */
    private function list(string $path, int $startTime): ToolResult
    {
        if (! is_dir($path)) {
            return ToolResult::failure("Not a directory: {$path}", $this->duration($startTime));
        }

        $entries = [];
        $items = scandir($path);

        if ($items === false) {
            return ToolResult::failure("Failed to read directory: {$path}", $this->duration($startTime));
        }

        foreach ($items as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $fullPath = $path.DIRECTORY_SEPARATOR.$entry;
            $isFile = is_file($fullPath);

            $entries[] = [
                'name' => $entry,
                'type' => $isFile ? 'file' : 'directory',
                'size' => $isFile ? filesize($fullPath) : null,
            ];
        }

        return ToolResult::success([
            'entries' => $entries,
            'path' => $path,
            'count' => count($entries),
        ], $this->duration($startTime));
    }

    /**
     * Move or rename a file.
     */
    private function move(string $source, string $destination, RuntimeContext $context, int $startTime): ToolResult
    {
        if (! file_exists($source)) {
            return ToolResult::failure("Source file not found: {$source}", $this->duration($startTime));
        }

        // Validate destination is also within workspace
        if (! $this->validatePathWithinWorkspace($context, $destination)) {
            return ToolResult::failure(
                "Destination path {$destination} is outside workspace {$context->workspaceRoot}",
                $this->duration($startTime)
            );
        }

        // Create destination directory if needed
        $destDir = dirname($destination);
        if (! is_dir($destDir)) {
            if (! mkdir($destDir, 0755, true)) {
                return ToolResult::failure("Failed to create directory: {$destDir}", $this->duration($startTime));
            }
        }

        if (! rename($source, $destination)) {
            return ToolResult::failure(
                "Failed to move {$source} to {$destination}",
                $this->duration($startTime)
            );
        }

        return ToolResult::success([
            'source' => $source,
            'destination' => $destination,
            'moved' => true,
        ], $this->duration($startTime));
    }

    /**
     * Calculate duration in milliseconds from start time.
     */
    private function duration(int $startTime): int
    {
        return (int) ((hrtime(true) - $startTime) / 1_000_000);
    }
}
