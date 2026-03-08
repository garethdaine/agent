<?php

declare(strict_types=1);

namespace Tests\Feature\Interrogation;

use App\Models\InterrogationBuildTask;
use App\Models\InterrogationSession;
use App\Models\User;
use App\Support\Interrogation\GitOperationsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class GitOperationsServiceTest extends TestCase
{
    use RefreshDatabase;

    private GitOperationsService $service;

    private User $user;

    private string $tempRepoDir;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        $this->service = new GitOperationsService;
        $this->user = User::factory()->create();

        // Create a temporary git repository
        $this->tempRepoDir = sys_get_temp_dir().'/agent-git-ops-'.uniqid();
        mkdir($this->tempRepoDir, 0777, true);
        $this->runGit('init');
        $this->runGit('config user.email "test@example.com"');
        $this->runGit('config user.name "Test"');
        file_put_contents($this->tempRepoDir.'/README.md', '# Test');
        $this->runGit('add .');
        $this->runGit('commit -m "initial commit"');
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tempRepoDir)) {
            $this->recursiveDelete($this->tempRepoDir);
        }

        parent::tearDown();
    }

    public function test_returns_defaults_when_no_git_settings(): void
    {
        $session = InterrogationSession::factory()
            ->for($this->user)
            ->create(['project_directory' => $this->tempRepoDir]);

        $task = InterrogationBuildTask::create([
            'interrogation_session_id' => $session->id,
            'sequence' => 1,
            'title' => 'Test task',
        ]);

        $result = $this->service->prepareForTask($session, $task);

        $this->assertSame($this->tempRepoDir, $result['working_directory']);
        $this->assertSame([], $result['env']);
        $this->assertNull($result['branch']);
        $this->assertNull($result['worktree_path']);
    }

    public function test_returns_defaults_when_not_a_git_repo(): void
    {
        $nonGitDir = sys_get_temp_dir().'/agent-no-git-'.uniqid();
        mkdir($nonGitDir, 0777, true);

        $session = InterrogationSession::factory()
            ->for($this->user)
            ->create(['project_directory' => $nonGitDir]);

        $task = InterrogationBuildTask::create([
            'interrogation_session_id' => $session->id,
            'sequence' => 1,
            'title' => 'Test task',
        ]);

        $result = $this->service->prepareForTask($session, $task);

        $this->assertSame($nonGitDir, $result['working_directory']);
        $this->assertSame([], $result['env']);
        $this->assertNull($result['branch']);
        $this->assertNull($result['worktree_path']);

        rmdir($nonGitDir);
    }

    public function test_sets_commit_env_vars_when_enabled(): void
    {
        $session = InterrogationSession::factory()
            ->for($this->user)
            ->withGitSettings(['commit_enabled' => true])
            ->create(['project_directory' => $this->tempRepoDir]);

        $task = InterrogationBuildTask::create([
            'interrogation_session_id' => $session->id,
            'sequence' => 1,
            'title' => 'Test task',
        ]);

        $result = $this->service->prepareForTask($session, $task);

        $this->assertSame('1', $result['env']['GIT_COMMIT_ENABLED']);
        $this->assertArrayNotHasKey('GIT_CONVENTIONAL_COMMITS', $result['env']);
    }

    public function test_sets_conventional_commits_env_var(): void
    {
        $session = InterrogationSession::factory()
            ->for($this->user)
            ->withGitSettings(['commit_enabled' => true, 'conventional_commits' => true])
            ->create(['project_directory' => $this->tempRepoDir]);

        $task = InterrogationBuildTask::create([
            'interrogation_session_id' => $session->id,
            'sequence' => 1,
            'title' => 'Test task',
        ]);

        $result = $this->service->prepareForTask($session, $task);

        $this->assertSame('1', $result['env']['GIT_COMMIT_ENABLED']);
        $this->assertSame('1', $result['env']['GIT_CONVENTIONAL_COMMITS']);
    }

    public function test_creates_feature_branch_with_prefix(): void
    {
        $session = InterrogationSession::factory()
            ->for($this->user)
            ->withGitSettings(['branching_enabled' => true, 'branch_prefix' => 'feat'])
            ->create(['project_directory' => $this->tempRepoDir]);

        $task = InterrogationBuildTask::create([
            'interrogation_session_id' => $session->id,
            'sequence' => 1,
            'title' => 'Add login form',
        ]);

        $result = $this->service->prepareForTask($session, $task);

        $this->assertNotNull($result['branch']);
        $this->assertStringStartsWith('feat/', $result['branch']);
        $this->assertStringContainsString('add-login-form', $result['branch']);
        $this->assertSame($result['branch'], $result['env']['GIT_BRANCH']);
    }

    public function test_creates_feature_branch_without_prefix(): void
    {
        $session = InterrogationSession::factory()
            ->for($this->user)
            ->withGitSettings(['branching_enabled' => true])
            ->create(['project_directory' => $this->tempRepoDir]);

        $task = InterrogationBuildTask::create([
            'interrogation_session_id' => $session->id,
            'sequence' => 2,
            'title' => 'Fix bug',
        ]);

        $result = $this->service->prepareForTask($session, $task);

        $this->assertNotNull($result['branch']);
        $this->assertStringContainsString(sprintf('s%d-t02', $session->id), $result['branch']);
    }

    public function test_checks_out_target_branch_in_trunk_mode(): void
    {
        // Create a branch to checkout
        $this->runGit('branch develop');

        $session = InterrogationSession::factory()
            ->for($this->user)
            ->withGitSettings(['target_branch' => 'develop'])
            ->create(['project_directory' => $this->tempRepoDir]);

        $task = InterrogationBuildTask::create([
            'interrogation_session_id' => $session->id,
            'sequence' => 1,
            'title' => 'Test',
        ]);

        $result = $this->service->prepareForTask($session, $task);

        $this->assertSame('develop', $result['branch']);
        $this->assertSame('develop', $result['env']['GIT_BRANCH']);
    }

    public function test_creates_worktree_and_changes_working_directory(): void
    {
        $session = InterrogationSession::factory()
            ->for($this->user)
            ->withGitSettings(['worktree_enabled' => true])
            ->create(['project_directory' => $this->tempRepoDir]);

        $task = InterrogationBuildTask::create([
            'interrogation_session_id' => $session->id,
            'sequence' => 1,
            'title' => 'Test task',
        ]);

        $result = $this->service->prepareForTask($session, $task);

        $expectedWorktree = $this->tempRepoDir.'/.agent-worktrees/s'.$session->id.'-t01';
        $this->assertSame($expectedWorktree, $result['worktree_path']);
        $this->assertSame($expectedWorktree, $result['working_directory']);
        $this->assertDirectoryExists($result['worktree_path']);

        // Cleanup
        $this->service->cleanupAfterTask($session, $task);
    }

    public function test_cleanup_removes_worktree(): void
    {
        $session = InterrogationSession::factory()
            ->for($this->user)
            ->withGitSettings(['worktree_enabled' => true])
            ->create(['project_directory' => $this->tempRepoDir]);

        $task = InterrogationBuildTask::create([
            'interrogation_session_id' => $session->id,
            'sequence' => 1,
            'title' => 'Cleanup test',
        ]);

        $result = $this->service->prepareForTask($session, $task);
        $this->assertDirectoryExists($result['worktree_path']);

        $this->service->cleanupAfterTask($session, $task);
        $this->assertDirectoryDoesNotExist($result['worktree_path']);
    }

    public function test_cleanup_is_noop_when_worktree_disabled(): void
    {
        $session = InterrogationSession::factory()
            ->for($this->user)
            ->create(['project_directory' => $this->tempRepoDir]);

        $task = InterrogationBuildTask::create([
            'interrogation_session_id' => $session->id,
            'sequence' => 1,
            'title' => 'Noop test',
        ]);

        // Should not throw or fail
        $this->service->cleanupAfterTask($session, $task);
        $this->assertTrue(true);
    }

    public function test_is_git_repo_detects_repository(): void
    {
        $this->assertTrue($this->service->isGitRepo($this->tempRepoDir));
    }

    public function test_is_git_repo_returns_false_for_non_repo(): void
    {
        $nonGitDir = sys_get_temp_dir().'/agent-not-repo-'.uniqid();
        mkdir($nonGitDir, 0777, true);

        $this->assertFalse($this->service->isGitRepo($nonGitDir));

        rmdir($nonGitDir);
    }

    public function test_is_git_repo_returns_false_for_empty_string(): void
    {
        $this->assertFalse($this->service->isGitRepo(''));
    }

    private function runGit(string $args): void
    {
        $process = proc_open(
            "git {$args}",
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            $this->tempRepoDir,
            ['GIT_TERMINAL_PROMPT' => '0']
        );

        if ($process) {
            fclose($pipes[1]);
            fclose($pipes[2]);
            proc_close($process);
        }
    }

    private function recursiveDelete(string $dir): void
    {
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($items as $item) {
            if ($item->isDir()) {
                @rmdir($item->getPathname());
            } else {
                @unlink($item->getPathname());
            }
        }

        @rmdir($dir);
    }
}
