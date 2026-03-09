<?php

declare(strict_types=1);

namespace Tests\Feature\Interrogation;

use App\Models\InterrogationBuildTask;
use App\Models\InterrogationSession;
use App\Models\User;
use App\Support\Interrogation\BuildTaskRunFactory;
use App\Support\Interrogation\GitOperationsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

class BuildTaskRunFactoryGitTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        $this->user = User::factory()->create();
    }

    /**
     * Mock GitOperationsService to return passthrough defaults (no real git ops).
     */
    private function mockGitOps(array $overrides = []): void
    {
        $mock = Mockery::mock(GitOperationsService::class);
        $mock->shouldReceive('prepareForTask')->andReturn(array_merge([
            'working_directory' => base_path(),
            'env' => [],
            'branch' => null,
            'worktree_path' => null,
            'baseline_head' => null,
        ], $overrides));
        $mock->shouldReceive('cleanupAfterTask')->andReturnNull();

        $this->app->instance(GitOperationsService::class, $mock);
    }

    public function test_uses_original_directory_when_no_git_settings(): void
    {
        $this->mockGitOps(['working_directory' => base_path()]);

        $session = InterrogationSession::factory()
            ->for($this->user)
            ->create(['project_directory' => base_path()]);

        $task = InterrogationBuildTask::create([
            'interrogation_session_id' => $session->id,
            'sequence' => 1,
            'title' => 'No git task',
            'description' => 'Test description.',
        ]);

        $factory = app(BuildTaskRunFactory::class);
        $run = $factory->create($session, $task);

        $this->assertSame(base_path(), $run->job->working_directory);
    }

    public function test_passes_git_env_vars_in_env_json(): void
    {
        $this->mockGitOps([
            'env' => ['GIT_COMMIT_ENABLED' => '1', 'GIT_CONVENTIONAL_COMMITS' => '1'],
        ]);

        $session = InterrogationSession::factory()
            ->for($this->user)
            ->withGitSettings([
                'commit_enabled' => true,
                'conventional_commits' => true,
            ])
            ->create(['project_directory' => base_path()]);

        $task = InterrogationBuildTask::create([
            'interrogation_session_id' => $session->id,
            'sequence' => 1,
            'title' => 'Git env task',
            'description' => 'Test git env.',
        ]);

        $factory = app(BuildTaskRunFactory::class);
        $run = $factory->create($session, $task);

        $envJson = $run->job->env_json;
        $this->assertSame('1', $envJson['GIT_COMMIT_ENABLED'] ?? null);
        $this->assertSame('1', $envJson['GIT_CONVENTIONAL_COMMITS'] ?? null);
    }

    public function test_task_markdown_includes_git_operations_section_when_commit_enabled(): void
    {
        $this->mockGitOps();

        $session = InterrogationSession::factory()
            ->for($this->user)
            ->withGitSettings(['commit_enabled' => true])
            ->create(['project_directory' => base_path()]);

        $task = InterrogationBuildTask::create([
            'interrogation_session_id' => $session->id,
            'sequence' => 1,
            'title' => 'Commit task',
            'description' => 'Test commits.',
        ]);

        $factory = app(BuildTaskRunFactory::class);
        $run = $factory->create($session, $task);

        $markdownPath = $run->job->task_markdown_path;
        $this->assertNotNull($markdownPath);
        $content = file_get_contents($markdownPath);

        $this->assertStringContainsString('## Git Operations', $content);
        $this->assertStringContainsString('MUST commit your work', $content);
    }

    public function test_task_markdown_includes_conventional_commits_instruction(): void
    {
        $this->mockGitOps();

        $session = InterrogationSession::factory()
            ->for($this->user)
            ->withGitSettings([
                'commit_enabled' => true,
                'conventional_commits' => true,
            ])
            ->create(['project_directory' => base_path()]);

        $task = InterrogationBuildTask::create([
            'interrogation_session_id' => $session->id,
            'sequence' => 1,
            'title' => 'Conventional task',
            'description' => 'Test conventional.',
        ]);

        $factory = app(BuildTaskRunFactory::class);
        $run = $factory->create($session, $task);

        $markdownPath = $run->job->task_markdown_path;
        $content = file_get_contents($markdownPath);

        $this->assertStringContainsString('Conventional Commit', $content);
        $this->assertStringContainsString('feat, fix, refactor', $content);
    }

    public function test_task_markdown_omits_git_section_when_all_settings_off(): void
    {
        $this->mockGitOps();

        $session = InterrogationSession::factory()
            ->for($this->user)
            ->create(['project_directory' => base_path()]);

        $task = InterrogationBuildTask::create([
            'interrogation_session_id' => $session->id,
            'sequence' => 1,
            'title' => 'No git task',
            'description' => 'No git.',
        ]);

        $factory = app(BuildTaskRunFactory::class);
        $run = $factory->create($session, $task);

        $markdownPath = $run->job->task_markdown_path;
        $content = file_get_contents($markdownPath);

        $this->assertStringNotContainsString('## Git Operations', $content);
    }

    public function test_task_markdown_includes_trunk_based_instruction(): void
    {
        $this->mockGitOps();

        $session = InterrogationSession::factory()
            ->for($this->user)
            ->withGitSettings([
                'commit_enabled' => true,
                'target_branch' => 'develop',
            ])
            ->create(['project_directory' => base_path()]);

        $task = InterrogationBuildTask::create([
            'interrogation_session_id' => $session->id,
            'sequence' => 1,
            'title' => 'Trunk task',
            'description' => 'Test trunk.',
        ]);

        $factory = app(BuildTaskRunFactory::class);
        $run = $factory->create($session, $task);

        $markdownPath = $run->job->task_markdown_path;
        $content = file_get_contents($markdownPath);

        $this->assertStringContainsString('`develop`', $content);
        $this->assertStringContainsString('trunk-based', $content);
    }

    public function test_task_markdown_includes_branching_instruction(): void
    {
        $this->mockGitOps();

        $session = InterrogationSession::factory()
            ->for($this->user)
            ->withGitSettings([
                'commit_enabled' => true,
                'branching_enabled' => true,
            ])
            ->create(['project_directory' => base_path()]);

        $task = InterrogationBuildTask::create([
            'interrogation_session_id' => $session->id,
            'sequence' => 1,
            'title' => 'Branch task',
            'description' => 'Test branching.',
        ]);

        $factory = app(BuildTaskRunFactory::class);
        $run = $factory->create($session, $task);

        $markdownPath = $run->job->task_markdown_path;
        $content = file_get_contents($markdownPath);

        $this->assertStringContainsString('feature branch', $content);
        $this->assertStringContainsString('gitflow', $content);
    }

    public function test_task_markdown_includes_worktree_instruction(): void
    {
        $this->mockGitOps();

        $session = InterrogationSession::factory()
            ->for($this->user)
            ->withGitSettings(['worktree_enabled' => true])
            ->create(['project_directory' => base_path()]);

        $task = InterrogationBuildTask::create([
            'interrogation_session_id' => $session->id,
            'sequence' => 1,
            'title' => 'Worktree task',
            'description' => 'Test worktree.',
        ]);

        $factory = app(BuildTaskRunFactory::class);
        $run = $factory->create($session, $task);

        $markdownPath = $run->job->task_markdown_path;
        $content = file_get_contents($markdownPath);

        $this->assertStringContainsString('worktree', $content);
    }
}
