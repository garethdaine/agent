<?php

declare(strict_types=1);

namespace Tests\Feature\Interrogation;

use App\Models\InterrogationSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class GitBranchesApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private string $tempRepoDir;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        $this->user = User::factory()->create();
        $this->actingAs($this->user);

        // Create a temporary git repository
        $this->tempRepoDir = sys_get_temp_dir().'/agent-git-test-'.uniqid();
        mkdir($this->tempRepoDir, 0777, true);
        $this->runGitCommand('git init');
        $this->runGitCommand('git config user.email "test@example.com"');
        $this->runGitCommand('git config user.name "Test"');
        file_put_contents($this->tempRepoDir.'/README.md', '# Test');
        $this->runGitCommand('git add .');
        $this->runGitCommand('git commit -m "initial commit"');

        // Create a couple of extra branches
        $this->runGitCommand('git branch develop');
        $this->runGitCommand('git branch feature/test');
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tempRepoDir)) {
            $this->recursiveDelete($this->tempRepoDir);
        }

        parent::tearDown();
    }

    public function test_returns_branches_for_valid_git_repo(): void
    {
        $session = InterrogationSession::factory()
            ->for($this->user)
            ->create(['project_directory' => $this->tempRepoDir]);

        $response = $this->getJson("/agent/api/v1/interrogation/sessions/{$session->id}/git-branches")
            ->assertOk();

        $branches = $response->json('data.branches');
        $this->assertIsArray($branches);
        $this->assertContains('develop', $branches);
        $this->assertContains('feature/test', $branches);
    }

    public function test_returns_current_branch(): void
    {
        $session = InterrogationSession::factory()
            ->for($this->user)
            ->create(['project_directory' => $this->tempRepoDir]);

        $response = $this->getJson("/agent/api/v1/interrogation/sessions/{$session->id}/git-branches")
            ->assertOk();

        $currentBranch = $response->json('data.current_branch');
        $this->assertNotNull($currentBranch);
        // Default branch after init is usually "main" or "master"
        $this->assertIsString($currentBranch);
    }

    public function test_returns_project_directory_in_response(): void
    {
        $session = InterrogationSession::factory()
            ->for($this->user)
            ->create(['project_directory' => $this->tempRepoDir]);

        $response = $this->getJson("/agent/api/v1/interrogation/sessions/{$session->id}/git-branches")
            ->assertOk();

        $this->assertSame($this->tempRepoDir, $response->json('data.project_directory'));
    }

    public function test_returns_error_for_non_git_directory(): void
    {
        $nonGitDir = sys_get_temp_dir().'/agent-non-git-'.uniqid();
        mkdir($nonGitDir, 0777, true);

        $session = InterrogationSession::factory()
            ->for($this->user)
            ->create(['project_directory' => $nonGitDir]);

        $this->getJson("/agent/api/v1/interrogation/sessions/{$session->id}/git-branches")
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'NOT_A_GIT_REPO');

        rmdir($nonGitDir);
    }

    public function test_returns_error_for_missing_directory(): void
    {
        $session = InterrogationSession::factory()
            ->for($this->user)
            ->create(['project_directory' => '/tmp/does-not-exist-'.uniqid()]);

        $this->getJson("/agent/api/v1/interrogation/sessions/{$session->id}/git-branches")
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'INVALID_DIRECTORY');
    }

    public function test_requires_authentication(): void
    {
        $session = InterrogationSession::factory()
            ->for($this->user)
            ->create(['project_directory' => $this->tempRepoDir]);

        // Make request as guest
        $this->app['auth']->forgetGuards();
        $this->getJson("/agent/api/v1/interrogation/sessions/{$session->id}/git-branches")
            ->assertUnauthorized();
    }

    public function test_scoped_to_users_sessions(): void
    {
        $otherUser = User::factory()->create();
        $session = InterrogationSession::factory()
            ->for($otherUser)
            ->create(['project_directory' => $this->tempRepoDir]);

        $this->getJson("/agent/api/v1/interrogation/sessions/{$session->id}/git-branches")
            ->assertNotFound();
    }

    private function runGitCommand(string $command): void
    {
        $process = proc_open(
            $command,
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
