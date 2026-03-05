<?php

namespace Tests\Unit\Runtime\Adapters;

use App\DTOs\Runtime\RuntimeContext;
use App\Enums\Runtime\RuntimeMode;
use App\Models\Runtime\RuntimeSession;
use App\Models\Runtime\RuntimeTurn;
use App\Models\User;
use App\Services\Runtime\Adapters\FsToolAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class FsToolAdapterTest extends TestCase
{
    use RefreshDatabase;

    private FsToolAdapter $adapter;

    private string $testDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->adapter = new FsToolAdapter;
        $this->testDir = sys_get_temp_dir().'/fs_adapter_test_'.uniqid();
        mkdir($this->testDir, 0755, true);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->testDir);
        parent::tearDown();
    }

    public function test_name_returns_fs(): void
    {
        $this->assertEquals('fs', $this->adapter->name());
    }

    public function test_schema_includes_all_operations(): void
    {
        $schema = $this->adapter->schema();

        $this->assertArrayHasKey('operations', $schema);
        $this->assertContains('read', $schema['operations']);
        $this->assertContains('write', $schema['operations']);
        $this->assertContains('edit', $schema['operations']);
        $this->assertContains('delete', $schema['operations']);
        $this->assertContains('list', $schema['operations']);
        $this->assertContains('move', $schema['operations']);
    }

    public function test_read_returns_file_contents(): void
    {
        $filePath = $this->testDir.'/test.txt';
        file_put_contents($filePath, 'Hello, World!');

        $context = $this->createContext($this->testDir, RuntimeMode::Safe);
        $result = $this->adapter->execute($context, [
            'operation' => 'read',
            'path' => $filePath,
        ]);

        $this->assertTrue($result->success);
        $this->assertEquals('Hello, World!', $result->data['content']);
    }

    public function test_read_fails_for_nonexistent_file(): void
    {
        $context = $this->createContext($this->testDir, RuntimeMode::Safe);
        $result = $this->adapter->execute($context, [
            'operation' => 'read',
            'path' => $this->testDir.'/nonexistent.txt',
        ]);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('not found', $result->error);
    }

    public function test_read_fails_for_path_outside_workspace(): void
    {
        $context = $this->createContext($this->testDir, RuntimeMode::Safe);
        $result = $this->adapter->execute($context, [
            'operation' => 'read',
            'path' => '/etc/passwd',
        ]);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('outside workspace', $result->error);
    }

    public function test_read_fails_for_directory_traversal_attempt(): void
    {
        // Create a file outside the workspace
        $outsideDir = sys_get_temp_dir().'/fs_adapter_outside_'.uniqid();
        mkdir($outsideDir, 0755, true);
        file_put_contents($outsideDir.'/secret.txt', 'secret');

        try {
            $context = $this->createContext($this->testDir, RuntimeMode::Safe);
            $result = $this->adapter->execute($context, [
                'operation' => 'read',
                'path' => $this->testDir.'/../'.basename($outsideDir).'/secret.txt',
            ]);

            $this->assertFalse($result->success);
            $this->assertStringContainsString('outside workspace', $result->error);
        } finally {
            File::deleteDirectory($outsideDir);
        }
    }

    public function test_write_creates_file(): void
    {
        $filePath = $this->testDir.'/new.txt';
        $context = $this->createContext($this->testDir, RuntimeMode::Standard);

        $result = $this->adapter->execute($context, [
            'operation' => 'write',
            'path' => $filePath,
            'content' => 'New content',
        ]);

        $this->assertTrue($result->success);
        $this->assertFileExists($filePath);
        $this->assertEquals('New content', file_get_contents($filePath));
    }

    public function test_write_creates_nested_directories(): void
    {
        $filePath = $this->testDir.'/nested/deep/dir/file.txt';
        $context = $this->createContext($this->testDir, RuntimeMode::Standard);

        $result = $this->adapter->execute($context, [
            'operation' => 'write',
            'path' => $filePath,
            'content' => 'Nested content',
        ]);

        $this->assertTrue($result->success);
        $this->assertFileExists($filePath);
        $this->assertEquals('Nested content', file_get_contents($filePath));
    }

    public function test_write_overwrites_existing_file(): void
    {
        $filePath = $this->testDir.'/existing.txt';
        file_put_contents($filePath, 'Original content');
        $context = $this->createContext($this->testDir, RuntimeMode::Standard);

        $result = $this->adapter->execute($context, [
            'operation' => 'write',
            'path' => $filePath,
            'content' => 'Overwritten content',
        ]);

        $this->assertTrue($result->success);
        $this->assertEquals('Overwritten content', file_get_contents($filePath));
    }

    public function test_edit_replaces_content_in_file(): void
    {
        $filePath = $this->testDir.'/edit.txt';
        file_put_contents($filePath, 'Hello, World!');
        $context = $this->createContext($this->testDir, RuntimeMode::Standard);

        $result = $this->adapter->execute($context, [
            'operation' => 'edit',
            'path' => $filePath,
            'search' => 'World',
            'replace' => 'PHP',
        ]);

        $this->assertTrue($result->success);
        $this->assertEquals('Hello, PHP!', file_get_contents($filePath));
    }

    public function test_edit_fails_for_nonexistent_file(): void
    {
        $context = $this->createContext($this->testDir, RuntimeMode::Standard);
        $result = $this->adapter->execute($context, [
            'operation' => 'edit',
            'path' => $this->testDir.'/nonexistent.txt',
            'search' => 'foo',
            'replace' => 'bar',
        ]);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('not found', $result->error);
    }

    public function test_edit_fails_when_search_pattern_not_found(): void
    {
        $filePath = $this->testDir.'/edit.txt';
        file_put_contents($filePath, 'Hello, World!');
        $context = $this->createContext($this->testDir, RuntimeMode::Standard);

        $result = $this->adapter->execute($context, [
            'operation' => 'edit',
            'path' => $filePath,
            'search' => 'nonexistent pattern',
            'replace' => 'replacement',
        ]);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('not found', $result->error);
    }

    public function test_delete_removes_file(): void
    {
        $filePath = $this->testDir.'/to_delete.txt';
        file_put_contents($filePath, 'Delete me');
        $context = $this->createContext($this->testDir, RuntimeMode::Standard);

        $result = $this->adapter->execute($context, [
            'operation' => 'delete',
            'path' => $filePath,
        ]);

        $this->assertTrue($result->success);
        $this->assertFileDoesNotExist($filePath);
    }

    public function test_delete_fails_for_nonexistent_file(): void
    {
        $context = $this->createContext($this->testDir, RuntimeMode::Standard);
        $result = $this->adapter->execute($context, [
            'operation' => 'delete',
            'path' => $this->testDir.'/nonexistent.txt',
        ]);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('not found', $result->error);
    }

    public function test_list_returns_directory_contents(): void
    {
        file_put_contents($this->testDir.'/file1.txt', 'a');
        file_put_contents($this->testDir.'/file2.txt', 'b');
        mkdir($this->testDir.'/subdir');

        $context = $this->createContext($this->testDir, RuntimeMode::Safe);
        $result = $this->adapter->execute($context, [
            'operation' => 'list',
            'path' => $this->testDir,
        ]);

        $this->assertTrue($result->success);
        $this->assertCount(3, $result->data['entries']);

        $names = array_column($result->data['entries'], 'name');
        $this->assertContains('file1.txt', $names);
        $this->assertContains('file2.txt', $names);
        $this->assertContains('subdir', $names);
    }

    public function test_list_includes_entry_types(): void
    {
        file_put_contents($this->testDir.'/file.txt', 'content');
        mkdir($this->testDir.'/dir');

        $context = $this->createContext($this->testDir, RuntimeMode::Safe);
        $result = $this->adapter->execute($context, [
            'operation' => 'list',
            'path' => $this->testDir,
        ]);

        $this->assertTrue($result->success);
        $entries = collect($result->data['entries'])->keyBy('name');

        $this->assertEquals('file', $entries['file.txt']['type']);
        $this->assertEquals('directory', $entries['dir']['type']);
    }

    public function test_list_fails_for_nonexistent_directory(): void
    {
        $context = $this->createContext($this->testDir, RuntimeMode::Safe);
        $result = $this->adapter->execute($context, [
            'operation' => 'list',
            'path' => $this->testDir.'/nonexistent',
        ]);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Not a directory', $result->error);
    }

    public function test_list_fails_for_file_path(): void
    {
        $filePath = $this->testDir.'/file.txt';
        file_put_contents($filePath, 'content');

        $context = $this->createContext($this->testDir, RuntimeMode::Safe);
        $result = $this->adapter->execute($context, [
            'operation' => 'list',
            'path' => $filePath,
        ]);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Not a directory', $result->error);
    }

    public function test_move_renames_file(): void
    {
        $srcPath = $this->testDir.'/source.txt';
        $dstPath = $this->testDir.'/destination.txt';
        file_put_contents($srcPath, 'Move me');

        $context = $this->createContext($this->testDir, RuntimeMode::Standard);
        $result = $this->adapter->execute($context, [
            'operation' => 'move',
            'path' => $srcPath,
            'destination' => $dstPath,
        ]);

        $this->assertTrue($result->success);
        $this->assertFileDoesNotExist($srcPath);
        $this->assertFileExists($dstPath);
        $this->assertEquals('Move me', file_get_contents($dstPath));
    }

    public function test_move_fails_for_nonexistent_source(): void
    {
        $context = $this->createContext($this->testDir, RuntimeMode::Standard);
        $result = $this->adapter->execute($context, [
            'operation' => 'move',
            'path' => $this->testDir.'/nonexistent.txt',
            'destination' => $this->testDir.'/dest.txt',
        ]);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('not found', $result->error);
    }

    public function test_move_fails_when_destination_outside_workspace(): void
    {
        $srcPath = $this->testDir.'/source.txt';
        file_put_contents($srcPath, 'Move me');

        $context = $this->createContext($this->testDir, RuntimeMode::Standard);
        $result = $this->adapter->execute($context, [
            'operation' => 'move',
            'path' => $srcPath,
            'destination' => '/tmp/outside_workspace.txt',
        ]);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('outside workspace', $result->error);
        // Source file should remain untouched
        $this->assertFileExists($srcPath);
    }

    public function test_authorize_returns_true_for_read_in_safe_mode(): void
    {
        $context = $this->createContext($this->testDir, RuntimeMode::Safe);

        $authorized = $this->adapter->authorize($context, [
            'operation' => 'read',
            'path' => $this->testDir.'/test.txt',
        ]);

        $this->assertTrue($authorized);
    }

    public function test_authorize_returns_true_for_list_in_safe_mode(): void
    {
        $context = $this->createContext($this->testDir, RuntimeMode::Safe);

        $authorized = $this->adapter->authorize($context, [
            'operation' => 'list',
            'path' => $this->testDir,
        ]);

        $this->assertTrue($authorized);
    }

    public function test_authorize_returns_false_for_write_in_safe_mode(): void
    {
        $context = $this->createContext($this->testDir, RuntimeMode::Safe);

        $authorized = $this->adapter->authorize($context, [
            'operation' => 'write',
            'path' => $this->testDir.'/test.txt',
        ]);

        $this->assertFalse($authorized);
    }

    public function test_authorize_returns_false_for_edit_in_safe_mode(): void
    {
        $context = $this->createContext($this->testDir, RuntimeMode::Safe);

        $authorized = $this->adapter->authorize($context, [
            'operation' => 'edit',
            'path' => $this->testDir.'/test.txt',
        ]);

        $this->assertFalse($authorized);
    }

    public function test_authorize_returns_false_for_delete_in_safe_mode(): void
    {
        $context = $this->createContext($this->testDir, RuntimeMode::Safe);

        $authorized = $this->adapter->authorize($context, [
            'operation' => 'delete',
            'path' => $this->testDir.'/test.txt',
        ]);

        $this->assertFalse($authorized);
    }

    public function test_authorize_returns_false_for_move_in_safe_mode(): void
    {
        $context = $this->createContext($this->testDir, RuntimeMode::Safe);

        $authorized = $this->adapter->authorize($context, [
            'operation' => 'move',
            'path' => $this->testDir.'/test.txt',
            'destination' => $this->testDir.'/dest.txt',
        ]);

        $this->assertFalse($authorized);
    }

    public function test_authorize_returns_true_for_write_in_standard_mode(): void
    {
        $context = $this->createContext($this->testDir, RuntimeMode::Standard);

        $authorized = $this->adapter->authorize($context, [
            'operation' => 'write',
            'path' => $this->testDir.'/test.txt',
        ]);

        $this->assertTrue($authorized);
    }

    public function test_authorize_returns_true_for_all_operations_in_full_mode(): void
    {
        $context = $this->createContext($this->testDir, RuntimeMode::Full);

        foreach (['read', 'write', 'edit', 'delete', 'list', 'move'] as $operation) {
            $authorized = $this->adapter->authorize($context, [
                'operation' => $operation,
                'path' => $this->testDir.'/test.txt',
                'destination' => $this->testDir.'/dest.txt',
            ]);

            $this->assertTrue($authorized, "Operation '$operation' should be authorized in full mode");
        }
    }

    public function test_execute_returns_unknown_operation_error(): void
    {
        $context = $this->createContext($this->testDir, RuntimeMode::Standard);
        $result = $this->adapter->execute($context, [
            'operation' => 'unknown_op',
            'path' => $this->testDir.'/test.txt',
        ]);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Unknown operation', $result->error);
    }

    private function createContext(string $workspaceRoot, RuntimeMode $mode): RuntimeContext
    {
        $session = RuntimeSession::factory()->make(['mode' => $mode, 'workspace_root' => $workspaceRoot]);
        $turn = RuntimeTurn::factory()->make();
        $user = User::factory()->make();

        return new RuntimeContext(
            session: $session,
            turn: $turn,
            user: $user,
            mode: $mode,
            policy: config("runtime.modes.{$mode->value}"),
            workspaceRoot: $workspaceRoot
        );
    }
}
