<?php

declare(strict_types=1);

namespace Tests\Unit\Support\RepoAnalysis;

use App\Support\RepoAnalysis\SnapshotBuilder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Tests\TestCase;

class SnapshotBuilderTest extends TestCase
{
    private string $fixtureRoot;

    private string $workspaceRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fixtureRoot = base_path('tests/Fixtures/CodeAnalysis/SnapshotBuilder/repo-root');
        $this->workspaceRoot = storage_path('framework/testing/code-analysis-snapshot-builder/'.Str::uuid()->toString());

        File::deleteDirectory($this->workspaceRoot);
        File::ensureDirectoryExists($this->workspaceRoot);
        File::copyDirectory($this->fixtureRoot, $this->workspaceRoot);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->workspaceRoot);

        parent::tearDown();
    }

    public function test_build_returns_sorted_canonical_paths_and_applies_filters(): void
    {
        $builder = new SnapshotBuilder;

        $snapshot = $builder->build($this->workspaceRoot, [
            'include_paths' => ['src/', 'docs/readme.md'],
            'exclude_paths' => ['src/zeta.txt'],
        ]);

        $paths = array_map(
            static fn (array $file): string => (string) $file['path'],
            $snapshot['manifest']['files']
        );

        $this->assertSame([
            'docs/readme.md',
            'src/alpha.txt',
            'src/nested/beta.txt',
        ], $paths);

        $this->assertNotContains('vendor/pkg/ignored.php', $paths);
        $this->assertNotContains('node_modules/lib/index.js', $paths);
        $this->assertNotContains('storage/logs/app.log', $paths);
        $this->assertNotContains('bootstrap/cache/config.php', $paths);
        $this->assertNotContains('.git/HEAD', $paths);
    }

    public function test_manifest_stores_mtime_but_snapshot_hash_ignores_mtime_changes(): void
    {
        $builder = new SnapshotBuilder;

        $first = $builder->build($this->workspaceRoot);
        $firstAlpha = collect($first['manifest']['files'])->firstWhere('path', 'src/alpha.txt');

        $this->assertIsArray($firstAlpha);
        $this->assertArrayHasKey('mtime', $firstAlpha);
        $this->assertIsInt($firstAlpha['mtime']);

        $alphaPath = $this->workspaceRoot.'/src/alpha.txt';
        $updatedMtime = ((int) $firstAlpha['mtime']) + 120;
        touch($alphaPath, $updatedMtime);
        clearstatcache(true, $alphaPath);

        $second = $builder->build($this->workspaceRoot);
        $secondAlpha = collect($second['manifest']['files'])->firstWhere('path', 'src/alpha.txt');

        $this->assertIsArray($secondAlpha);
        $this->assertSame($first['snapshot_hash'], $second['snapshot_hash']);
        $this->assertNotSame($firstAlpha['mtime'], $secondAlpha['mtime']);
    }

    public function test_manifest_json_serialization_is_deterministic_for_same_inputs(): void
    {
        $builder = new SnapshotBuilder;

        $first = $builder->build($this->workspaceRoot);
        $second = $builder->build($this->workspaceRoot);

        $this->assertSame($first['manifest_json'], $second['manifest_json']);
        $this->assertSame($first['snapshot_hash'], $second['snapshot_hash']);
    }

    public function test_build_handles_symlink_unreadable_oversize_and_path_escape_guards(): void
    {
        $builder = new SnapshotBuilder;
        $outsidePath = $this->workspaceRoot.'/../outside-'.Str::uuid()->toString().'.txt';
        File::put($outsidePath, 'outside-data');

        File::ensureDirectoryExists($this->workspaceRoot.'/links');

        if (! @symlink($outsidePath, $this->workspaceRoot.'/links/outside-link')) {
            $this->markTestSkipped('Unable to create symlink in this environment.');
        }

        if (! @symlink($this->workspaceRoot.'/src/alpha.txt', $this->workspaceRoot.'/links/inside-link')) {
            $this->markTestSkipped('Unable to create symlink in this environment.');
        }

        $unreadablePath = $this->workspaceRoot.'/src/unreadable.txt';
        File::put($unreadablePath, 'private');
        chmod($unreadablePath, 0000);

        $oversizePath = $this->workspaceRoot.'/src/oversize.bin';
        File::put($oversizePath, str_repeat('A', 64));

        $snapshot = $builder->build($this->workspaceRoot, [
            'include_paths' => ['src/', 'links/', '../escape-attempt'],
            'max_file_size_bytes' => 16,
        ]);

        chmod($unreadablePath, 0600);
        File::delete($outsidePath);

        $skipped = collect($snapshot['manifest']['skipped']);

        $this->assertTrue($skipped->contains(
            static fn (array $entry): bool => ($entry['path'] ?? null) === 'src/unreadable.txt'
                && ($entry['reason'] ?? null) === 'unreadable'
        ));

        $this->assertTrue($skipped->contains(
            static fn (array $entry): bool => ($entry['path'] ?? null) === 'src/oversize.bin'
                && ($entry['reason'] ?? null) === 'oversize'
                && ((int) ($entry['size_bytes'] ?? 0)) > 16
        ));

        $this->assertTrue($skipped->contains(
            static fn (array $entry): bool => ($entry['path'] ?? null) === 'links/inside-link'
                && ($entry['reason'] ?? null) === 'symlink'
        ));

        $this->assertTrue($skipped->contains(
            static fn (array $entry): bool => ($entry['path'] ?? null) === 'links/outside-link'
                && ($entry['reason'] ?? null) === 'path_escape'
        ));

        $this->assertContains(
            '../escape-attempt',
            $snapshot['manifest']['guards']['path_escape_attempts'] ?? []
        );
    }
}
