<?php

declare(strict_types=1);

namespace Tests\Integration\Documentation;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;
use Symfony\Component\Yaml\Yaml;
use Tests\TestCase;

class DocsAutomationFlowTest extends TestCase
{
    use RefreshDatabase;

    private string $repoDocsRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repoDocsRoot = storage_path('framework/testing/docs-automation-flow');
        File::deleteDirectory($this->repoDocsRoot);
        File::ensureDirectoryExists($this->repoDocsRoot);

        config()->set('documentation.paths.product', $this->repoDocsRoot.'/product');
        config()->set('documentation.paths.api', $this->repoDocsRoot.'/api');
        config()->set('documentation.paths.tooltips', $this->repoDocsRoot.'/tooltips');
        config()->set('documentation.sync.deploy.reindex.max_retries', 3);
        config()->set('documentation.sync.deploy.reindex.retry_interval_seconds', 30);
        config()->set('documentation.sync.deploy.reindex.timeout_seconds', 120);

        File::ensureDirectoryExists(config('documentation.paths.product'));
        File::ensureDirectoryExists(config('documentation.paths.api'));
        File::ensureDirectoryExists(config('documentation.paths.tooltips'));
    }

    public function test_commit_mode_regenerates_stale_artifacts_and_auto_stages_them(): void
    {
        $sharedScript = base_path('scripts/docs/sync.sh');
        $this->assertFileExists($sharedScript);

        $repoPath = storage_path('framework/testing/docs-automation-git-repo');
        File::deleteDirectory($repoPath);
        File::ensureDirectoryExists($repoPath.'/docs');
        File::ensureDirectoryExists($repoPath.'/docs-sync');
        File::ensureDirectoryExists($repoPath.'/bin');

        File::put($repoPath.'/docs/generated.md', "stale\n");
        File::put($repoPath.'/docs-sync/manifest.json', "{}\n");

        $this->runProcess(['git', 'init'], $repoPath);
        $this->runProcess(['git', 'config', 'user.email', 'docs-bot@example.com'], $repoPath);
        $this->runProcess(['git', 'config', 'user.name', 'Docs Bot'], $repoPath);
        $this->runProcess(['git', 'add', '.'], $repoPath);
        $this->runProcess(['git', 'commit', '-m', 'baseline'], $repoPath);

        $fakeSyncCommand = $repoPath.'/bin/fake-docs-sync.sh';
        File::put($fakeSyncCommand, <<<'BASH'
#!/usr/bin/env bash
set -euo pipefail

printf "fresh\n" > docs/generated.md
printf '{"mode":"commit"}\n' > docs-sync/manifest.json
BASH
        );
        chmod($fakeSyncCommand, 0755);

        $result = $this->runProcess(
            ['bash', $sharedScript, '--mode=commit', '--source=repo'],
            $repoPath,
            ['DOCS_SYNC_ARTISAN_BIN' => $fakeSyncCommand]
        );

        $this->assertSame(0, $result->getExitCode(), $result->getErrorOutput());

        $staged = $this->runProcess(['git', 'diff', '--cached', '--name-only'], $repoPath);
        $this->assertStringContainsString('docs/generated.md', $staged->getOutput());
        $this->assertStringContainsString('docs-sync/manifest.json', $staged->getOutput());
    }

    public function test_commit_mode_sync_failure_does_not_block_commit_hook(): void
    {
        $sharedScript = base_path('scripts/docs/sync.sh');
        $this->assertFileExists($sharedScript);

        $repoPath = storage_path('framework/testing/docs-automation-git-repo-failure');
        File::deleteDirectory($repoPath);
        File::ensureDirectoryExists($repoPath.'/bin');

        $this->runProcess(['git', 'init'], $repoPath);
        $this->runProcess(['git', 'config', 'user.email', 'docs-bot@example.com'], $repoPath);
        $this->runProcess(['git', 'config', 'user.name', 'Docs Bot'], $repoPath);

        $fakeSyncCommand = $repoPath.'/bin/fake-docs-sync-failure.sh';
        File::put($fakeSyncCommand, <<<'BASH'
#!/usr/bin/env bash
set -euo pipefail
exit 7
BASH
        );
        chmod($fakeSyncCommand, 0755);

        $result = $this->runProcess(
            ['bash', $sharedScript, '--mode=commit', '--source=repo'],
            $repoPath,
            ['DOCS_SYNC_ARTISAN_BIN' => $fakeSyncCommand]
        );

        $this->assertSame(0, $result->getExitCode(), 'Commit hook mode must not block on docs sync failure.');
    }

    public function test_deploy_mode_retries_three_times_with_thirty_second_interval_then_fails(): void
    {
        $this->writeMarkdownDoc(
            'product/dashboard/overview.md',
            $this->frontMatter([
                'slug' => 'dashboard-overview',
                'title' => 'Dashboard Overview',
                'summary' => 'Docs summary',
                'section' => 'dashboard',
                'route_names' => ['dashboard'],
                'setting_keys' => ['dashboard.default_range'],
                'feature_flags' => ['docs_center_enabled'],
            ]),
            "# Dashboard Overview\n\nBody."
        );
        $this->writeTooltipFile('tooltips/surfaces.yaml', [
            [
                'ui_key' => 'dashboard.load_time',
                'short_text' => 'Shows load time.',
                'severity' => 'info',
                'links' => [
                    ['label' => 'Laravel Docs', 'url' => 'https://laravel.com/docs/12.x'],
                ],
                'learn_more_slug' => 'dashboard-overview',
                'route_names' => ['dashboard'],
                'setting_keys' => ['dashboard.default_range'],
                'feature_flags' => ['docs_center_enabled'],
                'metadata' => [
                    'owner' => 'docs-team',
                    'locale' => 'en',
                ],
            ],
        ]);

        $state = (object) ['attempts' => 0, 'sleeps' => []];

        $this->app->bind('App\\Support\\Documentation\\DocsReindexExecutor', function () use ($state) {
            return new class($state) implements \App\Support\Documentation\DocsReindexExecutor
            {
                public function __construct(private object $state) {}

                /**
                 * @param  array<int, int>  $entryIds
                 * @param  array<int, int>  $fragmentIds
                 * @param  array<int, int>  $apiArtifactIds
                 */
                public function execute(array $entryIds, array $fragmentIds, array $apiArtifactIds = []): void
                {
                    $this->state->attempts++;

                    throw new \RuntimeException('typesense timeout');
                }
            };
        });

        $this->app->bind('App\\Support\\Documentation\\DocsSyncSleeper', function () use ($state) {
            return new class($state) implements \App\Support\Documentation\DocsSyncSleeper
            {
                public function __construct(private object $state) {}

                public function sleep(int $seconds): void
                {
                    $this->state->sleeps[] = $seconds;
                }
            };
        });

        $this->artisan('docs:sync', ['--mode' => 'deploy', '--source' => 'repo'])
            ->assertFailed();

        $this->assertSame(4, $state->attempts, 'Deploy mode should run initial attempt + 3 retries.');
        $this->assertSame([30, 30, 30], $state->sleeps, 'Deploy mode should sleep 30 seconds between retries.');
    }

    public function test_hook_and_workflow_entrypoints_use_shared_sync_pipeline(): void
    {
        $this->assertFileExists(base_path('.githooks/pre-commit'));
        $this->assertFileExists(base_path('.claude/hooks/pre-commit'));
        $this->assertFileExists(base_path('.codex/hooks/pre-commit'));
        $this->assertFileExists(base_path('.github/workflows/docs-deploy-sync.yml'));

        $this->assertStringContainsString('scripts/docs/sync.sh', (string) File::get(base_path('.githooks/pre-commit')));
        $this->assertStringContainsString('scripts/docs/sync.sh', (string) File::get(base_path('.claude/hooks/pre-commit')));
        $this->assertStringContainsString('scripts/docs/sync.sh', (string) File::get(base_path('.codex/hooks/pre-commit')));
        $this->assertStringContainsString(
            'php artisan docs:sync --mode=deploy --source=repo',
            (string) File::get(base_path('.github/workflows/docs-deploy-sync.yml'))
        );
    }

    /**
     * @param  array<string, mixed>  $frontMatter
     */
    private function writeMarkdownDoc(string $relativePath, array $frontMatter, string $body): void
    {
        $absolutePath = $this->repoDocsRoot.'/'.$relativePath;
        File::ensureDirectoryExists(dirname($absolutePath));

        $yaml = Yaml::dump($frontMatter, 4, 2);
        File::put(
            $absolutePath,
            "---\n{$yaml}---\n\n{$body}\n"
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $fragments
     */
    private function writeTooltipFile(string $relativePath, array $fragments): void
    {
        $absolutePath = $this->repoDocsRoot.'/'.$relativePath;
        File::ensureDirectoryExists(dirname($absolutePath));
        File::put($absolutePath, Yaml::dump($fragments, 6, 2));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function frontMatter(array $overrides = []): array
    {
        return array_merge([
            'slug' => 'dashboard-overview',
            'title' => 'Dashboard Overview',
            'summary' => 'Dashboard docs summary.',
            'section' => 'dashboard',
            'audience' => 'operator',
            'status' => 'published',
            'version' => '1.0.0',
            'tags' => ['dashboard'],
            'owner' => 'docs-team',
            'route_names' => ['dashboard'],
            'setting_keys' => ['dashboard.default_range'],
            'feature_flags' => ['docs_center_enabled'],
            'locale' => 'en',
            'reviewed_at' => '2026-03-02',
        ], $overrides);
    }

    /**
     * @param  array<int, string>  $command
     * @param  array<string, string>  $env
     */
    private function runProcess(array $command, string $workingDirectory, array $env = []): Process
    {
        $process = new Process($command, $workingDirectory, array_merge($_ENV, $_SERVER, $env));
        $process->run();

        $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput() ?: $process->getOutput());

        return $process;
    }
}
