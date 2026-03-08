<?php

declare(strict_types=1);

namespace Tests\Unit\Runtime\Adapters;

use App\DTOs\Runtime\RuntimeContext;
use App\DTOs\Runtime\ToolResult;
use App\Enums\Runtime\RuntimeMode;
use App\Models\Runtime\RuntimeSession;
use App\Models\Runtime\RuntimeTurn;
use App\Models\User;
use App\Services\Runtime\Adapters\AbstractToolAdapter;
use Tests\TestCase;

class AbstractToolAdapterTest extends TestCase
{
    public function test_validate_path_within_workspace_allows_valid_path(): void
    {
        $adapter = $this->createConcreteAdapter();
        $context = $this->createContext('/tmp/workspace');

        // Create the workspace directory for the test
        @mkdir('/tmp/workspace', 0755, true);
        touch('/tmp/workspace/file.txt');

        $this->assertTrue($adapter->publicValidatePath($context, '/tmp/workspace/file.txt'));

        // Cleanup
        @unlink('/tmp/workspace/file.txt');
    }

    public function test_validate_path_rejects_path_outside_workspace(): void
    {
        $adapter = $this->createConcreteAdapter();
        $context = $this->createContext('/tmp/workspace');

        // Create the workspace directory for the test
        @mkdir('/tmp/workspace', 0755, true);

        $this->assertFalse($adapter->publicValidatePath($context, '/etc/passwd'));
    }

    public function test_validate_path_rejects_traversal_attempts(): void
    {
        $adapter = $this->createConcreteAdapter();
        $context = $this->createContext('/tmp/workspace');

        // Create the workspace directory for the test
        @mkdir('/tmp/workspace', 0755, true);

        $this->assertFalse($adapter->publicValidatePath($context, '/tmp/workspace/../../../etc/passwd'));
    }

    private function createConcreteAdapter(): object
    {
        return new class extends AbstractToolAdapter
        {
            public function name(): string
            {
                return 'test';
            }

            public function schema(): array
            {
                return [];
            }

            public function execute(RuntimeContext $context, array $args): ToolResult
            {
                return ToolResult::success([], 0);
            }

            public function publicValidatePath(RuntimeContext $context, string $path): bool
            {
                return $this->validatePathWithinWorkspace($context, $path);
            }
        };
    }

    private function createContext(string $workspaceRoot): RuntimeContext
    {
        return new RuntimeContext(
            session: $this->createMock(RuntimeSession::class),
            turn: $this->createMock(RuntimeTurn::class),
            user: $this->createMock(User::class),
            mode: RuntimeMode::Safe,
            policy: [],
            workspaceRoot: $workspaceRoot
        );
    }
}
