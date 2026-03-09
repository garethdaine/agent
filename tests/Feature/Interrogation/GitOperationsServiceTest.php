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

    // ── prepareForTask tests ────────────────────────────────────────────────

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
        $this->assertNull($result['baseline_head']);

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

    public function test_prepare_for_task_returns_baseline_head(): void
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

        $this->assertNotNull($result['baseline_head']);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{40}$/', $result['baseline_head']);
    }

    // ── commitAfterTask tests ──────────────────────────────────────────────

    public function test_commit_after_task_commits_uncommitted_changes(): void
    {
        $session = InterrogationSession::factory()
            ->for($this->user)
            ->withGitSettings(['commit_enabled' => true])
            ->create(['project_directory' => $this->tempRepoDir]);

        $task = InterrogationBuildTask::create([
            'interrogation_session_id' => $session->id,
            'sequence' => 1,
            'title' => 'Add login form',
        ]);

        // Simulate AI runner leaving uncommitted changes
        file_put_contents($this->tempRepoDir.'/login.php', '<?php echo "login";');

        $result = $this->service->commitAfterTask($session, $task);

        $this->assertTrue($result['committed']);
        $this->assertNotNull($result['message']);
        $this->assertNull($result['error']);

        // Verify file is committed
        $process = proc_open('git status --porcelain', [1 => ['pipe', 'w']], $pipes, $this->tempRepoDir);
        $output = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        proc_close($process);
        $this->assertSame('', trim($output));
    }

    public function test_commit_after_task_uses_conventional_format(): void
    {
        $session = InterrogationSession::factory()
            ->for($this->user)
            ->withGitSettings(['commit_enabled' => true, 'conventional_commits' => true])
            ->create(['project_directory' => $this->tempRepoDir]);

        $task = InterrogationBuildTask::create([
            'interrogation_session_id' => $session->id,
            'sequence' => 3,
            'title' => 'Add user authentication',
        ]);

        file_put_contents($this->tempRepoDir.'/auth.php', '<?php echo "auth";');

        $result = $this->service->commitAfterTask($session, $task);

        $this->assertTrue($result['committed']);
        $this->assertMatchesRegularExpression('/^(feat|fix|refactor|test|docs|style|chore|perf|ci|build)\(/', $result['message']);
        $this->assertStringContainsString(sprintf('s%d-t03', $session->id), $result['message']);
    }

    public function test_commit_after_task_uses_plain_format_without_conventional(): void
    {
        $session = InterrogationSession::factory()
            ->for($this->user)
            ->withGitSettings(['commit_enabled' => true, 'conventional_commits' => false])
            ->create(['project_directory' => $this->tempRepoDir]);

        $task = InterrogationBuildTask::create([
            'interrogation_session_id' => $session->id,
            'sequence' => 2,
            'title' => 'Fix something',
        ]);

        file_put_contents($this->tempRepoDir.'/fix.php', '<?php echo "fix";');

        $result = $this->service->commitAfterTask($session, $task);

        $this->assertTrue($result['committed']);
        $this->assertStringStartsWith(sprintf('Build S%d T02:', $session->id), $result['message']);
    }

    public function test_commit_after_task_noop_when_commit_disabled(): void
    {
        $session = InterrogationSession::factory()
            ->for($this->user)
            ->withGitSettings(['commit_enabled' => false])
            ->create(['project_directory' => $this->tempRepoDir]);

        $task = InterrogationBuildTask::create([
            'interrogation_session_id' => $session->id,
            'sequence' => 1,
            'title' => 'Test',
        ]);

        file_put_contents($this->tempRepoDir.'/uncommitted.php', '<?php echo "uncommitted";');

        $result = $this->service->commitAfterTask($session, $task);

        $this->assertFalse($result['committed']);
        $this->assertNull($result['message']);
        $this->assertNull($result['error']);
    }

    public function test_commit_after_task_noop_when_no_changes(): void
    {
        $session = InterrogationSession::factory()
            ->for($this->user)
            ->withGitSettings(['commit_enabled' => true])
            ->create(['project_directory' => $this->tempRepoDir]);

        $task = InterrogationBuildTask::create([
            'interrogation_session_id' => $session->id,
            'sequence' => 1,
            'title' => 'Noop',
        ]);

        $result = $this->service->commitAfterTask($session, $task);

        $this->assertFalse($result['committed']);
        $this->assertNull($result['message']);
        $this->assertNull($result['error']);
    }

    public function test_commit_after_task_noop_when_not_a_git_repo(): void
    {
        $nonGitDir = sys_get_temp_dir().'/agent-no-git-commit-'.uniqid();
        mkdir($nonGitDir, 0777, true);

        $session = InterrogationSession::factory()
            ->for($this->user)
            ->withGitSettings(['commit_enabled' => true])
            ->create(['project_directory' => $nonGitDir]);

        $task = InterrogationBuildTask::create([
            'interrogation_session_id' => $session->id,
            'sequence' => 1,
            'title' => 'Test',
        ]);

        file_put_contents($nonGitDir.'/test.txt', 'test');

        $result = $this->service->commitAfterTask($session, $task);

        $this->assertFalse($result['committed']);

        unlink($nonGitDir.'/test.txt');
        rmdir($nonGitDir);
    }

    public function test_commit_after_task_stages_untracked_files(): void
    {
        $session = InterrogationSession::factory()
            ->for($this->user)
            ->withGitSettings(['commit_enabled' => true, 'conventional_commits' => true])
            ->create(['project_directory' => $this->tempRepoDir]);

        $task = InterrogationBuildTask::create([
            'interrogation_session_id' => $session->id,
            'sequence' => 1,
            'title' => 'Create new module',
        ]);

        // Create new untracked files
        mkdir($this->tempRepoDir.'/src', 0777, true);
        file_put_contents($this->tempRepoDir.'/src/Module.php', '<?php class Module {}');
        file_put_contents($this->tempRepoDir.'/src/Service.php', '<?php class Service {}');

        $result = $this->service->commitAfterTask($session, $task);

        $this->assertTrue($result['committed']);

        // Verify working directory is clean
        $process = proc_open('git status --porcelain', [1 => ['pipe', 'w']], $pipes, $this->tempRepoDir);
        $output = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        proc_close($process);
        $this->assertSame('', trim($output));
    }

    public function test_commit_after_task_infers_correct_conventional_types(): void
    {
        $session = InterrogationSession::factory()
            ->for($this->user)
            ->withGitSettings(['commit_enabled' => true, 'conventional_commits' => true])
            ->create(['project_directory' => $this->tempRepoDir]);

        $testCases = [
            ['title' => 'Fix broken login', 'expected_prefix' => 'fix('],
            ['title' => 'Add test coverage for users', 'expected_prefix' => 'test('],
            ['title' => 'Refactor controller decomposition', 'expected_prefix' => 'refactor('],
            ['title' => 'Update documentation for API', 'expected_prefix' => 'docs('],
            ['title' => 'Apply Pint code style', 'expected_prefix' => 'style('],
            ['title' => 'Create CI workflow files', 'expected_prefix' => 'ci('],
            ['title' => 'Clean up unused imports', 'expected_prefix' => 'chore('],
            ['title' => 'Add new feature module', 'expected_prefix' => 'feat('],
        ];

        foreach ($testCases as $i => $case) {
            $task = InterrogationBuildTask::create([
                'interrogation_session_id' => $session->id,
                'sequence' => $i + 1,
                'title' => $case['title'],
            ]);

            file_put_contents($this->tempRepoDir.'/file-'.$i.'.txt', 'content '.$i);

            $result = $this->service->commitAfterTask($session, $task);

            $this->assertTrue($result['committed'], "Expected commit for: {$case['title']}");
            $this->assertStringStartsWith(
                $case['expected_prefix'],
                $result['message'],
                "Expected prefix '{$case['expected_prefix']}' for: {$case['title']}, got: {$result['message']}"
            );
        }
    }

    // ── enforceAfterTask tests ─────────────────────────────────────────────

    public function test_enforce_noop_when_not_a_git_repo(): void
    {
        $nonGitDir = sys_get_temp_dir().'/agent-no-git-enforce-'.uniqid();
        mkdir($nonGitDir, 0777, true);

        $session = InterrogationSession::factory()
            ->for($this->user)
            ->withGitSettings(['commit_enabled' => true, 'conventional_commits' => true])
            ->create(['project_directory' => $nonGitDir]);

        $task = InterrogationBuildTask::create([
            'interrogation_session_id' => $session->id,
            'sequence' => 1,
            'title' => 'Test',
        ]);

        $result = $this->service->enforceAfterTask($session, $task);

        $this->assertSame([], $result['actions']);
        $this->assertSame([], $result['errors']);

        rmdir($nonGitDir);
    }

    public function test_enforce_commits_uncommitted_changes(): void
    {
        $session = InterrogationSession::factory()
            ->for($this->user)
            ->withGitSettings(['commit_enabled' => true])
            ->create(['project_directory' => $this->tempRepoDir]);

        $task = InterrogationBuildTask::create([
            'interrogation_session_id' => $session->id,
            'sequence' => 1,
            'title' => 'Add feature',
        ]);

        file_put_contents($this->tempRepoDir.'/feature.php', '<?php echo "feature";');

        $result = $this->service->enforceAfterTask($session, $task);

        $this->assertCount(1, $result['actions']);
        $this->assertSame('uncommitted_changes_committed', $result['actions'][0]['type']);
        $this->assertSame([], $result['errors']);
        $this->assertSame('', trim($this->getGitOutput('status --porcelain')));
    }

    public function test_enforce_noop_when_commit_disabled(): void
    {
        $session = InterrogationSession::factory()
            ->for($this->user)
            ->withGitSettings(['commit_enabled' => false])
            ->create(['project_directory' => $this->tempRepoDir]);

        $task = InterrogationBuildTask::create([
            'interrogation_session_id' => $session->id,
            'sequence' => 1,
            'title' => 'Test',
        ]);

        file_put_contents($this->tempRepoDir.'/uncommitted.txt', 'data');

        $result = $this->service->enforceAfterTask($session, $task);

        $this->assertSame([], $result['actions']);
        $this->assertSame([], $result['errors']);
        // File should still be uncommitted
        $this->assertNotSame('', trim($this->getGitOutput('status --porcelain')));
    }

    public function test_enforce_squashes_non_conventional_commits(): void
    {
        $session = InterrogationSession::factory()
            ->for($this->user)
            ->withGitSettings(['commit_enabled' => true, 'conventional_commits' => true])
            ->create(['project_directory' => $this->tempRepoDir]);

        $task = InterrogationBuildTask::create([
            'interrogation_session_id' => $session->id,
            'sequence' => 1,
            'title' => 'Add user auth',
        ]);

        // Record baseline
        $baselineHead = trim($this->getGitOutput('rev-parse HEAD'));

        // Simulate AI making non-conventional commits
        file_put_contents($this->tempRepoDir.'/auth.php', '<?php echo "auth";');
        $this->runGit('add .');
        $this->runGit('commit -m "added auth stuff"');

        file_put_contents($this->tempRepoDir.'/login.php', '<?php echo "login";');
        $this->runGit('add .');
        $this->runGit('commit -m "more work on login"');

        $result = $this->service->enforceAfterTask($session, $task, $baselineHead);

        $this->assertCount(1, $result['actions']);
        $this->assertSame('conventional_commits_squashed', $result['actions'][0]['type']);
        $this->assertStringContainsString('2 commit(s)', $result['actions'][0]['detail']);
        $this->assertSame([], $result['errors']);

        // Verify single commit since baseline with conventional format
        $logOutput = trim($this->getGitOutput('log --oneline '.$baselineHead.'..HEAD'));
        $lines = array_filter(explode("\n", $logOutput));
        $this->assertCount(1, $lines);
        $this->assertMatchesRegularExpression('/^[0-9a-f]+ feat\(/', $lines[0]);
    }

    public function test_enforce_skips_squash_when_all_commits_are_conventional(): void
    {
        $session = InterrogationSession::factory()
            ->for($this->user)
            ->withGitSettings(['commit_enabled' => true, 'conventional_commits' => true])
            ->create(['project_directory' => $this->tempRepoDir]);

        $task = InterrogationBuildTask::create([
            'interrogation_session_id' => $session->id,
            'sequence' => 1,
            'title' => 'Add auth module',
        ]);

        $baselineHead = trim($this->getGitOutput('rev-parse HEAD'));

        // AI made proper conventional commits
        file_put_contents($this->tempRepoDir.'/auth.php', '<?php echo "auth";');
        $this->runGit('add .');
        $this->runGit('commit -m "feat: add authentication module"');

        file_put_contents($this->tempRepoDir.'/test.php', '<?php echo "test";');
        $this->runGit('add .');
        $this->runGit('commit -m "test: add auth tests"');

        $result = $this->service->enforceAfterTask($session, $task, $baselineHead);

        // No squash needed — all commits were conventional
        $squashActions = array_filter($result['actions'], fn ($a) => $a['type'] === 'conventional_commits_squashed');
        $this->assertCount(0, $squashActions);
        $this->assertSame([], $result['errors']);

        // Verify both commits are still present
        $logOutput = trim($this->getGitOutput('log --oneline '.$baselineHead.'..HEAD'));
        $lines = array_filter(explode("\n", $logOutput));
        $this->assertCount(2, $lines);
    }

    public function test_enforce_squash_includes_uncommitted_changes(): void
    {
        $session = InterrogationSession::factory()
            ->for($this->user)
            ->withGitSettings(['commit_enabled' => true, 'conventional_commits' => true])
            ->create(['project_directory' => $this->tempRepoDir]);

        $task = InterrogationBuildTask::create([
            'interrogation_session_id' => $session->id,
            'sequence' => 1,
            'title' => 'Add feature',
        ]);

        $baselineHead = trim($this->getGitOutput('rev-parse HEAD'));

        // Non-conventional commit + uncommitted changes
        file_put_contents($this->tempRepoDir.'/committed.php', '<?php echo "committed";');
        $this->runGit('add .');
        $this->runGit('commit -m "wip"');

        file_put_contents($this->tempRepoDir.'/uncommitted.php', '<?php echo "uncommitted";');

        $result = $this->service->enforceAfterTask($session, $task, $baselineHead);

        // Should squash (which includes uncommitted changes), no separate uncommitted action
        $types = array_column($result['actions'], 'type');
        $this->assertContains('conventional_commits_squashed', $types);
        $this->assertNotContains('uncommitted_changes_committed', $types);
        $this->assertSame([], $result['errors']);

        // Working tree should be clean
        $this->assertSame('', trim($this->getGitOutput('status --porcelain')));

        // Both files should be in the single squashed commit
        $logOutput = trim($this->getGitOutput('log --oneline '.$baselineHead.'..HEAD'));
        $lines = array_filter(explode("\n", $logOutput));
        $this->assertCount(1, $lines);
    }

    public function test_enforce_detects_branch_drift_and_restores(): void
    {
        $session = InterrogationSession::factory()
            ->for($this->user)
            ->withGitSettings(['branching_enabled' => true, 'commit_enabled' => true])
            ->create(['project_directory' => $this->tempRepoDir]);

        $task = InterrogationBuildTask::create([
            'interrogation_session_id' => $session->id,
            'sequence' => 1,
            'title' => 'Add login form',
        ]);

        // Create the expected feature branch
        $prep = $this->service->prepareForTask($session, $task);
        $expectedBranch = $prep['branch'];

        // Simulate AI drifting to a different branch
        $this->runGit('checkout -b rogue-branch');

        $result = $this->service->enforceAfterTask($session, $task, $prep['baseline_head']);

        // Should have restored to expected branch
        $branchActions = array_filter($result['actions'], fn ($a) => $a['type'] === 'branch_restored');
        $this->assertCount(1, $branchActions);

        // Verify current branch is the expected one
        $currentBranch = trim($this->getGitOutput('rev-parse --abbrev-ref HEAD'));
        $this->assertSame($expectedBranch, $currentBranch);
    }

    public function test_enforce_no_branch_action_when_on_correct_branch(): void
    {
        $session = InterrogationSession::factory()
            ->for($this->user)
            ->withGitSettings(['branching_enabled' => true, 'commit_enabled' => true])
            ->create(['project_directory' => $this->tempRepoDir]);

        $task = InterrogationBuildTask::create([
            'interrogation_session_id' => $session->id,
            'sequence' => 1,
            'title' => 'Add login form',
        ]);

        // Create the expected feature branch (and stay on it)
        $prep = $this->service->prepareForTask($session, $task);

        $result = $this->service->enforceAfterTask($session, $task, $prep['baseline_head']);

        // No branch actions needed
        $branchActions = array_filter($result['actions'], fn ($a) => $a['type'] === 'branch_restored');
        $this->assertCount(0, $branchActions);
    }

    public function test_enforce_target_branch_drift_detection(): void
    {
        // Create a develop branch
        $this->runGit('branch develop');

        $session = InterrogationSession::factory()
            ->for($this->user)
            ->withGitSettings(['target_branch' => 'develop', 'commit_enabled' => true])
            ->create(['project_directory' => $this->tempRepoDir]);

        $task = InterrogationBuildTask::create([
            'interrogation_session_id' => $session->id,
            'sequence' => 1,
            'title' => 'Trunk work',
        ]);

        // Prepare puts us on develop
        $prep = $this->service->prepareForTask($session, $task);

        // AI drifts to main
        $this->runGit('checkout -b temp-branch');

        $result = $this->service->enforceAfterTask($session, $task, $prep['baseline_head']);

        $branchActions = array_filter($result['actions'], fn ($a) => $a['type'] === 'branch_restored');
        $this->assertCount(1, $branchActions);

        $currentBranch = trim($this->getGitOutput('rev-parse --abbrev-ref HEAD'));
        $this->assertSame('develop', $currentBranch);
    }

    public function test_enforce_no_baseline_skips_conventional_check(): void
    {
        $session = InterrogationSession::factory()
            ->for($this->user)
            ->withGitSettings(['commit_enabled' => true, 'conventional_commits' => true])
            ->create(['project_directory' => $this->tempRepoDir]);

        $task = InterrogationBuildTask::create([
            'interrogation_session_id' => $session->id,
            'sequence' => 1,
            'title' => 'Add feature',
        ]);

        // Non-conventional commit without baseline — should only commit uncommitted, not squash
        file_put_contents($this->tempRepoDir.'/file.php', '<?php echo "file";');
        $this->runGit('add .');
        $this->runGit('commit -m "wip stuff"');

        file_put_contents($this->tempRepoDir.'/uncommitted.php', '<?php echo "uncommitted";');

        // No baseline_head passed
        $result = $this->service->enforceAfterTask($session, $task);

        // Should commit uncommitted changes but NOT squash (no baseline to reference)
        $types = array_column($result['actions'], 'type');
        $this->assertNotContains('conventional_commits_squashed', $types);
        $this->assertContains('uncommitted_changes_committed', $types);
    }

    // ── buildFeatureBranchName tests ────────────────────────────────────────

    public function test_build_feature_branch_name_matches_create_feature_branch(): void
    {
        $session = InterrogationSession::factory()
            ->for($this->user)
            ->withGitSettings(['branching_enabled' => true, 'branch_prefix' => 'feature'])
            ->create(['project_directory' => $this->tempRepoDir]);

        $task = InterrogationBuildTask::create([
            'interrogation_session_id' => $session->id,
            'sequence' => 3,
            'title' => 'Add user dashboard',
        ]);

        // Build expected name
        $expectedName = $this->service->buildFeatureBranchName($session, $task, 'feature');

        // Create actual branch via prepareForTask
        $result = $this->service->prepareForTask($session, $task);

        $this->assertSame($expectedName, $result['branch']);
    }

    public function test_build_feature_branch_name_without_prefix(): void
    {
        $session = InterrogationSession::factory()
            ->for($this->user)
            ->create(['project_directory' => $this->tempRepoDir]);

        $task = InterrogationBuildTask::create([
            'interrogation_session_id' => $session->id,
            'sequence' => 5,
            'title' => 'Fix login bug',
        ]);

        $name = $this->service->buildFeatureBranchName($session, $task);
        $this->assertStringContainsString(sprintf('s%d-t05', $session->id), $name);
        $this->assertStringContainsString('fix-login-bug', $name);
        $this->assertStringNotContainsString('/', $name);
    }

    // ── isGitRepo tests ─────────────────────────────────────────────────────

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

    // ── Helpers ──────────────────────────────────────────────────────────────

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

    private function getGitOutput(string $args): string
    {
        $process = proc_open(
            "git {$args}",
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            $this->tempRepoDir,
            ['GIT_TERMINAL_PROMPT' => '0']
        );

        if (! $process) {
            return '';
        }

        $output = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);

        return $output !== false ? $output : '';
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
