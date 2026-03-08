<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Models\AgentJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class AgentJobCreateCommandTest extends TestCase
{
    use RefreshDatabase;

    private string $sandboxBase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sandboxBase = storage_path('framework/testing/agent-sandbox');
        @mkdir($this->sandboxBase.'/bin', 0777, true);
        @mkdir($this->sandboxBase.'/tasks', 0777, true);
        @mkdir($this->sandboxBase.'/work', 0777, true);

        $claude = $this->sandboxBase.'/bin/claude';
        $codex = $this->sandboxBase.'/bin/codex';
        $custom = $this->sandboxBase.'/bin/agent-runner';

        file_put_contents($claude, "#!/bin/sh\nexit 0\n");
        file_put_contents($codex, "#!/bin/sh\nexit 0\n");
        file_put_contents($custom, "#!/bin/sh\nexit 0\n");

        chmod($claude, 0755);
        chmod($codex, 0755);
        chmod($custom, 0755);

        config()->set('agent.allowed_task_markdown_bases', [$this->sandboxBase.'/tasks']);
        config()->set('agent.allowed_working_directory_bases', [$this->sandboxBase.'/work']);
        config()->set('agent.runner_executables', [
            'claude' => $claude,
            'codex' => $codex,
            'custom' => $custom,
        ]);
        config()->set('agent.default_templates', [
            'claude' => $claude.' -p {{task_markdown_path}}',
            'codex' => $codex.' exec {{task_markdown_path}}',
        ]);
    }

    public function test_creates_job_with_runner_type_claude(): void
    {
        $user = User::factory()->create(['email' => 'owner@example.com']);
        $taskFile = $this->sandboxBase.'/tasks/cli-job.md';
        file_put_contents($taskFile, "# CLI job\n");

        $exitCode = Artisan::call('agent:job:create', [
            '--name' => 'CLI Claude Job',
            '--cron' => '0 9 * * 1-5',
            '--runner-type' => 'claude',
            '--user' => (string) $user->id,
            '--working-directory' => $this->sandboxBase.'/work',
            '--task-markdown-path' => $taskFile,
            '--timezone' => 'UTC',
            '--max-runtime' => 300,
            '--cooldown' => 0,
        ]);

        $this->assertSame(0, $exitCode);

        $job = AgentJob::query()->where('user_id', $user->id)->where('name', 'CLI Claude Job')->firstOrFail();
        $this->assertSame('claude', $job->runner_type);
        $this->assertSame('CLI Claude Job', $job->name);
        $this->assertSame('0 9 * * 1-5', $job->cron_expression);
        $this->assertTrue($job->is_enabled);
    }

    public function test_creates_job_with_runner_type_codex_default(): void
    {
        $user = User::factory()->create();
        $taskFile = $this->sandboxBase.'/tasks/codex-job.md';
        file_put_contents($taskFile, "# Codex job\n");

        $exitCode = Artisan::call('agent:job:create', [
            '--name' => 'CLI Codex Job',
            '--cron' => '0 0 1 1 1',
            '--user' => $user->email,
            '--working-directory' => $this->sandboxBase.'/work',
            '--task-markdown-path' => $taskFile,
        ]);

        $this->assertSame(0, $exitCode);

        $job = AgentJob::query()->where('user_id', $user->id)->where('name', 'CLI Codex Job')->firstOrFail();
        $this->assertSame('codex', $job->runner_type);
    }

    public function test_creates_job_with_disabled_flag(): void
    {
        $user = User::factory()->create();
        $taskFile = $this->sandboxBase.'/tasks/disabled-job.md';
        file_put_contents($taskFile, "# Disabled\n");

        $exitCode = Artisan::call('agent:job:create', [
            '--name' => 'Disabled CLI Job',
            '--cron' => '0 0 1 1 1',
            '--runner-type' => 'codex',
            '--user' => (string) $user->id,
            '--working-directory' => $this->sandboxBase.'/work',
            '--task-markdown-path' => $taskFile,
            '--disabled' => true,
        ]);

        $this->assertSame(0, $exitCode);

        $job = AgentJob::query()->where('name', 'Disabled CLI Job')->firstOrFail();
        $this->assertFalse($job->is_enabled);
    }

    public function test_fails_when_name_already_exists_for_user(): void
    {
        $user = User::factory()->create();
        AgentJob::factory()->create([
            'user_id' => $user->id,
            'name' => 'Existing Job',
            'runner_type' => 'codex',
        ]);
        $taskFile = $this->sandboxBase.'/tasks/dup.md';
        file_put_contents($taskFile, "# Dup\n");

        $exitCode = Artisan::call('agent:job:create', [
            '--name' => 'Existing Job',
            '--cron' => '0 0 1 1 1',
            '--user' => (string) $user->id,
            '--working-directory' => $this->sandboxBase.'/work',
            '--task-markdown-path' => $taskFile,
        ]);

        $this->assertSame(1, $exitCode);
        $this->assertSame(1, AgentJob::query()->where('user_id', $user->id)->where('name', 'Existing Job')->count());
    }
}
