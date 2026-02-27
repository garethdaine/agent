<?php

namespace Tests\Feature;

use App\Jobs\ExecuteAgentRunJob;
use App\Models\AgentJob;
use App\Models\AgentJobRun;
use App\Models\AgentSystemState;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgentRunnerLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private string $sandboxBase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sandboxBase = storage_path('framework/testing/agent-runner');
        @mkdir($this->sandboxBase.'/bin', 0777, true);
        @mkdir($this->sandboxBase.'/tasks', 0777, true);
        @mkdir($this->sandboxBase.'/work', 0777, true);

        $exec = $this->sandboxBase.'/bin/runner';
        file_put_contents($exec, "#!/bin/sh\nexit 0\n");
        chmod($exec, 0755);

        config()->set('agent.allowed_task_markdown_bases', [$this->sandboxBase.'/tasks']);
        config()->set('agent.allowed_working_directory_bases', [$this->sandboxBase.'/work']);
        config()->set('agent.runner_executables', [
            'claude' => $exec,
            'codex' => $exec,
            'custom' => $exec,
        ]);
        config()->set('agent.default_templates', [
            'claude' => $exec.' -p {{task_markdown_path}}',
            'codex' => $exec.' exec {{task_markdown_path}}',
        ]);
    }

    public function test_job_auto_disables_after_three_scheduled_path_failures(): void
    {
        $user = User::factory()->create();
        $taskFile = $this->sandboxBase.'/tasks/path-failure.md';
        file_put_contents($taskFile, "# Task\n");

        $job = AgentJob::query()->create([
            'user_id' => $user->id,
            'name' => 'Auto Disable',
            'description' => null,
            'cron_expression' => '0 0 1 1 1',
            'timezone' => 'UTC',
            'is_enabled' => true,
            'max_runtime_seconds' => 60,
            'cooldown_seconds' => 0,
            'runner_type' => 'claude',
            'command_template' => config('agent.default_templates.claude'),
            'task_markdown_path' => $taskFile,
            'working_directory' => $this->sandboxBase.'/work',
        ]);

        unlink($taskFile);

        for ($i = 0; $i < 3; $i++) {
            $run = AgentJobRun::query()->create([
                'agent_job_id' => $job->id,
                'user_id' => $user->id,
                'initiated_by_user_id' => null,
                'trigger_type' => AgentJobRun::TRIGGER_SCHEDULE,
                'due_window_utc_minute' => now('UTC')->addMinutes($i + 1),
                'status' => AgentJobRun::STATUS_QUEUED,
                'duration_ms' => 0,
                'stdout_bytes_pre' => 0,
                'stdout_bytes_post' => 0,
                'stderr_bytes_pre' => 0,
                'stderr_bytes_post' => 0,
                'metadata_json' => [
                    'output_truncated' => false,
                    'redaction_count' => 0,
                ],
            ]);

            $this->runExecuteAgentRunJob($run->id);
        }

        $job->refresh();

        $this->assertFalse($job->is_enabled);
        $this->assertSame(3, (int) $job->scheduled_path_failure_streak);
    }

    public function test_manual_path_failures_do_not_increment_streak_and_success_resets_it(): void
    {
        $user = User::factory()->create();
        $taskFile = $this->sandboxBase.'/tasks/manual-path.md';
        file_put_contents($taskFile, "# Task\n");

        $job = AgentJob::query()->create([
            'user_id' => $user->id,
            'name' => 'Manual Path Failures',
            'description' => null,
            'cron_expression' => '0 0 1 1 1',
            'timezone' => 'UTC',
            'is_enabled' => true,
            'max_runtime_seconds' => 60,
            'cooldown_seconds' => 0,
            'runner_type' => 'claude',
            'command_template' => config('agent.default_templates.claude'),
            'task_markdown_path' => $taskFile,
            'working_directory' => $this->sandboxBase.'/work',
            'scheduled_path_failure_streak' => 2,
        ]);

        unlink($taskFile);

        $manualFailure = AgentJobRun::query()->create([
            'agent_job_id' => $job->id,
            'user_id' => $user->id,
            'initiated_by_user_id' => $user->id,
            'trigger_type' => AgentJobRun::TRIGGER_MANUAL,
            'status' => AgentJobRun::STATUS_QUEUED,
            'duration_ms' => 0,
            'stdout_bytes_pre' => 0,
            'stdout_bytes_post' => 0,
            'stderr_bytes_pre' => 0,
            'stderr_bytes_post' => 0,
            'metadata_json' => [
                'output_truncated' => false,
                'redaction_count' => 0,
            ],
        ]);

        $this->runExecuteAgentRunJob($manualFailure->id);

        $job->refresh();
        $this->assertSame(2, (int) $job->scheduled_path_failure_streak);

        file_put_contents($taskFile, "# Task\n");

        $manualSuccess = AgentJobRun::query()->create([
            'agent_job_id' => $job->id,
            'user_id' => $user->id,
            'initiated_by_user_id' => $user->id,
            'trigger_type' => AgentJobRun::TRIGGER_MANUAL,
            'status' => AgentJobRun::STATUS_QUEUED,
            'duration_ms' => 0,
            'stdout_bytes_pre' => 0,
            'stdout_bytes_post' => 0,
            'stderr_bytes_pre' => 0,
            'stderr_bytes_post' => 0,
            'metadata_json' => [
                'output_truncated' => false,
                'redaction_count' => 0,
            ],
        ]);

        $this->runExecuteAgentRunJob($manualSuccess->id);

        $job->refresh();
        $this->assertSame(0, (int) $job->scheduled_path_failure_streak);
    }

    public function test_approval_detection_is_cleared_when_run_reaches_terminal_state(): void
    {
        $approvalExec = $this->sandboxBase.'/bin/approval-runner';
        file_put_contents($approvalExec, "#!/bin/sh\necho \"I need permission to use web tools\" \nexit 0\n");
        chmod($approvalExec, 0755);

        config()->set('agent.runner_executables', [
            'claude' => $approvalExec,
            'codex' => $approvalExec,
            'custom' => $approvalExec,
        ]);
        config()->set('agent.default_templates', [
            'claude' => $approvalExec.' -p {{task_markdown_path}}',
            'codex' => $approvalExec.' exec {{task_markdown_path}}',
        ]);

        $user = User::factory()->create();
        $taskFile = $this->sandboxBase.'/tasks/approval.md';
        file_put_contents($taskFile, "# Approval\n");

        $job = AgentJob::query()->create([
            'user_id' => $user->id,
            'name' => 'Approval Metadata',
            'description' => null,
            'cron_expression' => '0 0 1 1 1',
            'timezone' => 'UTC',
            'is_enabled' => true,
            'max_runtime_seconds' => 60,
            'cooldown_seconds' => 0,
            'runner_type' => 'claude',
            'command_template' => config('agent.default_templates.claude'),
            'task_markdown_path' => $taskFile,
            'working_directory' => $this->sandboxBase.'/work',
        ]);

        $run = AgentJobRun::query()->create([
            'agent_job_id' => $job->id,
            'user_id' => $user->id,
            'initiated_by_user_id' => $user->id,
            'trigger_type' => AgentJobRun::TRIGGER_MANUAL,
            'status' => AgentJobRun::STATUS_QUEUED,
            'duration_ms' => 0,
            'stdout_bytes_pre' => 0,
            'stdout_bytes_post' => 0,
            'stderr_bytes_pre' => 0,
            'stderr_bytes_post' => 0,
            'metadata_json' => [
                'output_truncated' => false,
                'redaction_count' => 0,
                'approval_required' => false,
            ],
        ]);

        $this->runExecuteAgentRunJob($run->id);

        $run->refresh();
        $metadata = (array) ($run->metadata_json ?? []);

        $this->assertSame(AgentJobRun::STATUS_SUCCEEDED, $run->status);
        $this->assertFalse((bool) ($metadata['approval_required'] ?? true));
        $this->assertNotEmpty($metadata['approval_detected_at'] ?? null);
        $this->assertSame(AgentJobRun::STATUS_SUCCEEDED, $metadata['approval_resolution'] ?? null);
    }

    public function test_approval_phrase_inside_escaped_code_snippet_does_not_trigger_detection(): void
    {
        $snippetExec = $this->sandboxBase.'/bin/approval-escaped-snippet-runner';
        $script = <<<'SH'
#!/bin/sh
cat <<'OUT'
$this->assertSame(AgentJobRun::STATUS_SUCCEEDED, $metadata['approval_resolution'] ?? null);\n\npublic function test_permissions_banner(): void {\n    file_put_contents($approvalExec, "#!/bin/sh\necho \"I need permission to use web tools\"\nexit 0\n");
OUT
exit 0
SH;
        file_put_contents($snippetExec, $script);
        chmod($snippetExec, 0755);

        config()->set('agent.runner_executables', [
            'claude' => $snippetExec,
            'codex' => $snippetExec,
            'custom' => $snippetExec,
        ]);
        config()->set('agent.default_templates', [
            'claude' => $snippetExec.' -p {{task_markdown_path}}',
            'codex' => $snippetExec.' exec {{task_markdown_path}}',
        ]);

        $user = User::factory()->create();
        $taskFile = $this->sandboxBase.'/tasks/approval-escaped-snippet.md';
        file_put_contents($taskFile, "# Approval Escaped Snippet\n");

        $job = AgentJob::query()->create([
            'user_id' => $user->id,
            'name' => 'Approval Escaped Snippet',
            'description' => null,
            'cron_expression' => '0 0 1 1 1',
            'timezone' => 'UTC',
            'is_enabled' => true,
            'max_runtime_seconds' => 60,
            'cooldown_seconds' => 0,
            'runner_type' => 'claude',
            'command_template' => config('agent.default_templates.claude'),
            'task_markdown_path' => $taskFile,
            'working_directory' => $this->sandboxBase.'/work',
        ]);

        $run = AgentJobRun::query()->create([
            'agent_job_id' => $job->id,
            'user_id' => $user->id,
            'initiated_by_user_id' => $user->id,
            'trigger_type' => AgentJobRun::TRIGGER_MANUAL,
            'status' => AgentJobRun::STATUS_QUEUED,
            'duration_ms' => 0,
            'stdout_bytes_pre' => 0,
            'stdout_bytes_post' => 0,
            'stderr_bytes_pre' => 0,
            'stderr_bytes_post' => 0,
            'metadata_json' => [
                'output_truncated' => false,
                'redaction_count' => 0,
                'approval_required' => false,
            ],
        ]);

        $this->runExecuteAgentRunJob($run->id);

        $run->refresh();
        $metadata = (array) ($run->metadata_json ?? []);

        $this->assertSame(AgentJobRun::STATUS_SUCCEEDED, $run->status);
        $this->assertFalse((bool) ($metadata['approval_required'] ?? true));
        $this->assertNull($metadata['approval_detected_at'] ?? null);
        $this->assertNull($metadata['approval_resolution'] ?? null);
    }

    public function test_approval_phrase_inside_vue_template_snippet_does_not_trigger_detection(): void
    {
        $snippetExec = $this->sandboxBase.'/bin/approval-vue-template-snippet-runner';
        $script = <<<'SH'
#!/bin/sh
cat <<'OUT'
</button>\n                        </td>\n                        <tr v-if="!loading && runs.length === 0">\n                            <td colspan="5" class="px-4 py-6 text-center text-sm text-gray-500 dark:text-gray-400">No runs in range.</td>\n                        </tr>\n<div class="rounded-lg border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800">\n<div class="event-tail">\n[30rem] overflow-auto bg-gray-950 p-3 font-mono text-xs text-gray-100\">\nApproval likely required in active run output.\n</div>
OUT
exit 0
SH;
        file_put_contents($snippetExec, $script);
        chmod($snippetExec, 0755);

        config()->set('agent.runner_executables', [
            'claude' => $snippetExec,
            'codex' => $snippetExec,
            'custom' => $snippetExec,
        ]);
        config()->set('agent.default_templates', [
            'claude' => $snippetExec.' -p {{task_markdown_path}}',
            'codex' => $snippetExec.' exec {{task_markdown_path}}',
        ]);

        $user = User::factory()->create();
        $taskFile = $this->sandboxBase.'/tasks/approval-vue-template-snippet.md';
        file_put_contents($taskFile, "# Approval Vue Template Snippet\n");

        $job = AgentJob::query()->create([
            'user_id' => $user->id,
            'name' => 'Approval Vue Template Snippet',
            'description' => null,
            'cron_expression' => '0 0 1 1 1',
            'timezone' => 'UTC',
            'is_enabled' => true,
            'max_runtime_seconds' => 60,
            'cooldown_seconds' => 0,
            'runner_type' => 'claude',
            'command_template' => config('agent.default_templates.claude'),
            'task_markdown_path' => $taskFile,
            'working_directory' => $this->sandboxBase.'/work',
        ]);

        $run = AgentJobRun::query()->create([
            'agent_job_id' => $job->id,
            'user_id' => $user->id,
            'initiated_by_user_id' => $user->id,
            'trigger_type' => AgentJobRun::TRIGGER_MANUAL,
            'status' => AgentJobRun::STATUS_QUEUED,
            'duration_ms' => 0,
            'stdout_bytes_pre' => 0,
            'stdout_bytes_post' => 0,
            'stderr_bytes_pre' => 0,
            'stderr_bytes_post' => 0,
            'metadata_json' => [
                'output_truncated' => false,
                'redaction_count' => 0,
                'approval_required' => false,
            ],
        ]);

        $this->runExecuteAgentRunJob($run->id);

        $run->refresh();
        $metadata = (array) ($run->metadata_json ?? []);

        $this->assertSame(AgentJobRun::STATUS_SUCCEEDED, $run->status);
        $this->assertFalse((bool) ($metadata['approval_required'] ?? true));
        $this->assertNull($metadata['approval_detected_at'] ?? null);
        $this->assertNull($metadata['approval_resolution'] ?? null);
    }

    public function test_write_permission_blocker_output_forces_failed_status_even_with_zero_exit_code(): void
    {
        $permissionExec = $this->sandboxBase.'/bin/write-permission-runner';
        file_put_contents($permissionExec, "#!/bin/sh\necho \"I need your permission to write files to app/ and database/.\"\necho \"All file write operations are denied by the permission system.\"\nexit 0\n");
        chmod($permissionExec, 0755);

        config()->set('agent.runner_executables', [
            'claude' => $permissionExec,
            'codex' => $permissionExec,
            'custom' => $permissionExec,
        ]);
        config()->set('agent.default_templates', [
            'claude' => $permissionExec.' -p {{task_markdown_path}}',
            'codex' => $permissionExec.' exec {{task_markdown_path}}',
        ]);

        $user = User::factory()->create();
        $taskFile = $this->sandboxBase.'/tasks/write-blocked.md';
        file_put_contents($taskFile, "# Blocked\n");

        $job = AgentJob::query()->create([
            'user_id' => $user->id,
            'name' => 'Write Permission Blocker',
            'description' => null,
            'cron_expression' => '0 0 1 1 1',
            'timezone' => 'UTC',
            'is_enabled' => true,
            'max_runtime_seconds' => 60,
            'cooldown_seconds' => 0,
            'runner_type' => 'claude',
            'command_template' => config('agent.default_templates.claude'),
            'task_markdown_path' => $taskFile,
            'working_directory' => $this->sandboxBase.'/work',
        ]);

        $run = AgentJobRun::query()->create([
            'agent_job_id' => $job->id,
            'user_id' => $user->id,
            'initiated_by_user_id' => $user->id,
            'trigger_type' => AgentJobRun::TRIGGER_MANUAL,
            'status' => AgentJobRun::STATUS_QUEUED,
            'duration_ms' => 0,
            'stdout_bytes_pre' => 0,
            'stdout_bytes_post' => 0,
            'stderr_bytes_pre' => 0,
            'stderr_bytes_post' => 0,
            'metadata_json' => [
                'output_truncated' => false,
                'redaction_count' => 0,
                'approval_required' => false,
            ],
        ]);

        $this->runExecuteAgentRunJob($run->id);

        $run->refresh();
        $metadata = (array) ($run->metadata_json ?? []);

        $this->assertSame(AgentJobRun::STATUS_FAILED, $run->status);
        $this->assertSame('PERMISSION_REQUIRED', $run->error_code);
        $this->assertTrue((bool) ($metadata['permission_blocker_detected'] ?? false));
        $this->assertNotEmpty($metadata['permission_blocker_detected_at'] ?? null);
        $this->assertSame(AgentJobRun::STATUS_FAILED, $metadata['permission_blocker_resolution'] ?? null);
        $this->assertSame(AgentJobRun::STATUS_FAILED, $metadata['approval_resolution'] ?? null);
    }

    public function test_write_permission_phrase_inside_escaped_code_snippet_does_not_trigger_detection(): void
    {
        $snippetExec = $this->sandboxBase.'/bin/write-permission-escaped-snippet-runner';
        $script = <<<'SH'
#!/bin/sh
cat <<'OUT'
$this->assertSame(AgentJobRun::STATUS_SUCCEEDED, $metadata['approval_resolution'] ?? null);\n\npublic function test_write_permission_blocker_output_forces_failed_status_even_with_zero_exit_code(): void {\n    file_put_contents($permissionExec, "#!/bin/sh\necho \"I need your permission to write files to app/ and database/.\"\necho \"All file write operations are denied by the permission system.\"\nexit 0\n");
OUT
exit 0
SH;
        file_put_contents($snippetExec, $script);
        chmod($snippetExec, 0755);

        config()->set('agent.runner_executables', [
            'claude' => $snippetExec,
            'codex' => $snippetExec,
            'custom' => $snippetExec,
        ]);
        config()->set('agent.default_templates', [
            'claude' => $snippetExec.' -p {{task_markdown_path}}',
            'codex' => $snippetExec.' exec {{task_markdown_path}}',
        ]);

        $user = User::factory()->create();
        $taskFile = $this->sandboxBase.'/tasks/write-permission-escaped-snippet.md';
        file_put_contents($taskFile, "# Write Permission Escaped Snippet\n");

        $job = AgentJob::query()->create([
            'user_id' => $user->id,
            'name' => 'Write Permission Escaped Snippet',
            'description' => null,
            'cron_expression' => '0 0 1 1 1',
            'timezone' => 'UTC',
            'is_enabled' => true,
            'max_runtime_seconds' => 60,
            'cooldown_seconds' => 0,
            'runner_type' => 'claude',
            'command_template' => config('agent.default_templates.claude'),
            'task_markdown_path' => $taskFile,
            'working_directory' => $this->sandboxBase.'/work',
        ]);

        $run = AgentJobRun::query()->create([
            'agent_job_id' => $job->id,
            'user_id' => $user->id,
            'initiated_by_user_id' => $user->id,
            'trigger_type' => AgentJobRun::TRIGGER_MANUAL,
            'status' => AgentJobRun::STATUS_QUEUED,
            'duration_ms' => 0,
            'stdout_bytes_pre' => 0,
            'stdout_bytes_post' => 0,
            'stderr_bytes_pre' => 0,
            'stderr_bytes_post' => 0,
            'metadata_json' => [
                'output_truncated' => false,
                'redaction_count' => 0,
                'approval_required' => false,
            ],
        ]);

        $this->runExecuteAgentRunJob($run->id);

        $run->refresh();
        $metadata = (array) ($run->metadata_json ?? []);

        $this->assertSame(AgentJobRun::STATUS_SUCCEEDED, $run->status);
        $this->assertNull($run->error_code);
        $this->assertFalse((bool) ($metadata['permission_blocker_detected'] ?? false));
        $this->assertNull($metadata['permission_blocker_detected_at'] ?? null);
        $this->assertNull($metadata['permission_blocker_resolution'] ?? null);
    }

    public function test_clarification_request_output_sets_clarification_metadata(): void
    {
        $clarifyExec = $this->sandboxBase.'/bin/clarify-runner';
        file_put_contents($clarifyExec, "#!/bin/sh\necho \"Could you clarify whether we should keep the old policy mapping?\"\nexit 0\n");
        chmod($clarifyExec, 0755);

        config()->set('agent.runner_executables', [
            'claude' => $clarifyExec,
            'codex' => $clarifyExec,
            'custom' => $clarifyExec,
        ]);
        config()->set('agent.default_templates', [
            'claude' => $clarifyExec.' -p {{task_markdown_path}}',
            'codex' => $clarifyExec.' exec {{task_markdown_path}}',
        ]);

        $user = User::factory()->create();
        $taskFile = $this->sandboxBase.'/tasks/clarify.md';
        file_put_contents($taskFile, "# Clarify\n");

        $job = AgentJob::query()->create([
            'user_id' => $user->id,
            'name' => 'Clarification Metadata',
            'description' => null,
            'cron_expression' => '0 0 1 1 1',
            'timezone' => 'UTC',
            'is_enabled' => true,
            'max_runtime_seconds' => 60,
            'cooldown_seconds' => 0,
            'runner_type' => 'claude',
            'command_template' => config('agent.default_templates.claude'),
            'task_markdown_path' => $taskFile,
            'working_directory' => $this->sandboxBase.'/work',
        ]);

        $run = AgentJobRun::query()->create([
            'agent_job_id' => $job->id,
            'user_id' => $user->id,
            'initiated_by_user_id' => $user->id,
            'trigger_type' => AgentJobRun::TRIGGER_MANUAL,
            'status' => AgentJobRun::STATUS_QUEUED,
            'duration_ms' => 0,
            'stdout_bytes_pre' => 0,
            'stdout_bytes_post' => 0,
            'stderr_bytes_pre' => 0,
            'stderr_bytes_post' => 0,
            'metadata_json' => [
                'output_truncated' => false,
                'redaction_count' => 0,
            ],
        ]);

        $this->runExecuteAgentRunJob($run->id);

        $run->refresh();
        $metadata = (array) ($run->metadata_json ?? []);

        $this->assertSame(AgentJobRun::STATUS_SUCCEEDED, $run->status);
        $this->assertTrue((bool) ($metadata['clarification_required'] ?? false));
        $this->assertNotEmpty($metadata['clarification_detected_at'] ?? null);
        $this->assertStringContainsString('Could you clarify', (string) ($metadata['clarification_excerpt'] ?? ''));
    }

    public function test_clarification_phrase_inside_escaped_code_snippet_does_not_trigger_detection(): void
    {
        $snippetExec = $this->sandboxBase.'/bin/clarification-escaped-snippet-runner';
        $script = <<<'SH'
#!/bin/sh
cat <<'OUT'
$this->assertTrue((bool) ($metadata['clarification_required'] ?? false));\n\npublic function test_clarification_request_output_sets_clarification_metadata(): void {\n    file_put_contents($clarifyExec, "#!/bin/sh\necho \"Could you clarify whether we should keep the old policy mapping?\"\nexit 0\n");
OUT
exit 0
SH;
        file_put_contents($snippetExec, $script);
        chmod($snippetExec, 0755);

        config()->set('agent.runner_executables', [
            'claude' => $snippetExec,
            'codex' => $snippetExec,
            'custom' => $snippetExec,
        ]);
        config()->set('agent.default_templates', [
            'claude' => $snippetExec.' -p {{task_markdown_path}}',
            'codex' => $snippetExec.' exec {{task_markdown_path}}',
        ]);

        $user = User::factory()->create();
        $taskFile = $this->sandboxBase.'/tasks/clarification-escaped-snippet.md';
        file_put_contents($taskFile, "# Clarification Escaped Snippet\n");

        $job = AgentJob::query()->create([
            'user_id' => $user->id,
            'name' => 'Clarification Escaped Snippet',
            'description' => null,
            'cron_expression' => '0 0 1 1 1',
            'timezone' => 'UTC',
            'is_enabled' => true,
            'max_runtime_seconds' => 60,
            'cooldown_seconds' => 0,
            'runner_type' => 'claude',
            'command_template' => config('agent.default_templates.claude'),
            'task_markdown_path' => $taskFile,
            'working_directory' => $this->sandboxBase.'/work',
        ]);

        $run = AgentJobRun::query()->create([
            'agent_job_id' => $job->id,
            'user_id' => $user->id,
            'initiated_by_user_id' => $user->id,
            'trigger_type' => AgentJobRun::TRIGGER_MANUAL,
            'status' => AgentJobRun::STATUS_QUEUED,
            'duration_ms' => 0,
            'stdout_bytes_pre' => 0,
            'stdout_bytes_post' => 0,
            'stderr_bytes_pre' => 0,
            'stderr_bytes_post' => 0,
            'metadata_json' => [
                'output_truncated' => false,
                'redaction_count' => 0,
            ],
        ]);

        $this->runExecuteAgentRunJob($run->id);

        $run->refresh();
        $metadata = (array) ($run->metadata_json ?? []);

        $this->assertSame(AgentJobRun::STATUS_SUCCEEDED, $run->status);
        $this->assertFalse((bool) ($metadata['clarification_required'] ?? false));
        $this->assertNull($metadata['clarification_detected_at'] ?? null);
    }

    public function test_clarification_detection_ignores_needs_clarification_heading_text(): void
    {
        $headingExec = $this->sandboxBase.'/bin/clarification-heading-runner';
        file_put_contents($headingExec, "#!/bin/sh\necho \"### Needs Clarification Verdict (Summary Only):\"\nexit 0\n");
        chmod($headingExec, 0755);

        config()->set('agent.runner_executables', [
            'claude' => $headingExec,
            'codex' => $headingExec,
            'custom' => $headingExec,
        ]);
        config()->set('agent.default_templates', [
            'claude' => $headingExec.' -p {{task_markdown_path}}',
            'codex' => $headingExec.' exec {{task_markdown_path}}',
        ]);

        $user = User::factory()->create();
        $taskFile = $this->sandboxBase.'/tasks/clarification-heading.md';
        file_put_contents($taskFile, "# Clarification heading only\n");

        $job = AgentJob::query()->create([
            'user_id' => $user->id,
            'name' => 'Clarification Heading Ignore',
            'description' => null,
            'cron_expression' => '0 0 1 1 1',
            'timezone' => 'UTC',
            'is_enabled' => true,
            'max_runtime_seconds' => 60,
            'cooldown_seconds' => 0,
            'runner_type' => 'claude',
            'command_template' => config('agent.default_templates.claude'),
            'task_markdown_path' => $taskFile,
            'working_directory' => $this->sandboxBase.'/work',
        ]);

        $run = AgentJobRun::query()->create([
            'agent_job_id' => $job->id,
            'user_id' => $user->id,
            'initiated_by_user_id' => $user->id,
            'trigger_type' => AgentJobRun::TRIGGER_MANUAL,
            'status' => AgentJobRun::STATUS_QUEUED,
            'duration_ms' => 0,
            'stdout_bytes_pre' => 0,
            'stdout_bytes_post' => 0,
            'stderr_bytes_pre' => 0,
            'stderr_bytes_post' => 0,
            'metadata_json' => [
                'output_truncated' => false,
                'redaction_count' => 0,
            ],
        ]);

        $this->runExecuteAgentRunJob($run->id);

        $run->refresh();
        $metadata = (array) ($run->metadata_json ?? []);

        $this->assertSame(AgentJobRun::STATUS_SUCCEEDED, $run->status);
        $this->assertFalse((bool) ($metadata['clarification_required'] ?? false));
        $this->assertArrayNotHasKey('clarification_detected_at', $metadata);
    }

    public function test_approval_detection_ignores_codex_banner_lines(): void
    {
        $bannerExec = $this->sandboxBase.'/bin/codex-banner-runner';
        file_put_contents($bannerExec, "#!/bin/sh\necho \"approval: never\"\necho \"sandbox: danger-full-access\"\nexit 0\n");
        chmod($bannerExec, 0755);

        config()->set('agent.runner_executables', [
            'claude' => $bannerExec,
            'codex' => $bannerExec,
            'custom' => $bannerExec,
        ]);
        config()->set('agent.default_templates', [
            'claude' => $bannerExec.' -p {{task_markdown_path}}',
            'codex' => $bannerExec.' exec {{task_markdown_path}}',
        ]);

        $user = User::factory()->create();
        $taskFile = $this->sandboxBase.'/tasks/codex-banner.md';
        file_put_contents($taskFile, "# Banner\n");

        $job = AgentJob::query()->create([
            'user_id' => $user->id,
            'name' => 'Approval Banner Ignore',
            'description' => null,
            'cron_expression' => '0 0 1 1 1',
            'timezone' => 'UTC',
            'is_enabled' => true,
            'max_runtime_seconds' => 60,
            'cooldown_seconds' => 0,
            'runner_type' => 'codex',
            'command_template' => config('agent.default_templates.codex'),
            'task_markdown_path' => $taskFile,
            'working_directory' => $this->sandboxBase.'/work',
        ]);

        $run = AgentJobRun::query()->create([
            'agent_job_id' => $job->id,
            'user_id' => $user->id,
            'initiated_by_user_id' => $user->id,
            'trigger_type' => AgentJobRun::TRIGGER_MANUAL,
            'status' => AgentJobRun::STATUS_QUEUED,
            'duration_ms' => 0,
            'stdout_bytes_pre' => 0,
            'stdout_bytes_post' => 0,
            'stderr_bytes_pre' => 0,
            'stderr_bytes_post' => 0,
            'metadata_json' => [
                'output_truncated' => false,
                'redaction_count' => 0,
                'approval_required' => false,
            ],
        ]);

        $this->runExecuteAgentRunJob($run->id);

        $run->refresh();
        $metadata = (array) ($run->metadata_json ?? []);

        $this->assertSame(AgentJobRun::STATUS_SUCCEEDED, $run->status);
        $this->assertFalse((bool) ($metadata['approval_required'] ?? true));
        $this->assertNull($metadata['approval_detected_at'] ?? null);
        $this->assertNull($metadata['approval_resolution'] ?? null);
    }

    public function test_run_times_out_when_exceeding_max_runtime(): void
    {
        $slowExec = $this->sandboxBase.'/bin/slow-runner';
        file_put_contents($slowExec, "#!/bin/sh\nsleep 2\nexit 0\n");
        chmod($slowExec, 0755);

        config()->set('agent.runner_executables', [
            'claude' => $slowExec,
            'codex' => $slowExec,
            'custom' => $slowExec,
        ]);
        config()->set('agent.default_templates', [
            'claude' => $slowExec.' -p {{task_markdown_path}}',
            'codex' => $slowExec.' exec {{task_markdown_path}}',
        ]);

        $user = User::factory()->create();
        $taskFile = $this->sandboxBase.'/tasks/timeout.md';
        file_put_contents($taskFile, "# Timeout\n");

        $job = AgentJob::query()->create([
            'user_id' => $user->id,
            'name' => 'Timeout Job',
            'description' => null,
            'cron_expression' => '0 0 1 1 1',
            'timezone' => 'UTC',
            'is_enabled' => true,
            'max_runtime_seconds' => 1,
            'cooldown_seconds' => 0,
            'runner_type' => 'claude',
            'command_template' => config('agent.default_templates.claude'),
            'task_markdown_path' => $taskFile,
            'working_directory' => $this->sandboxBase.'/work',
        ]);

        $run = AgentJobRun::query()->create([
            'agent_job_id' => $job->id,
            'user_id' => $user->id,
            'initiated_by_user_id' => $user->id,
            'trigger_type' => AgentJobRun::TRIGGER_MANUAL,
            'status' => AgentJobRun::STATUS_QUEUED,
            'duration_ms' => 0,
            'stdout_bytes_pre' => 0,
            'stdout_bytes_post' => 0,
            'stderr_bytes_pre' => 0,
            'stderr_bytes_post' => 0,
            'metadata_json' => [
                'output_truncated' => false,
                'redaction_count' => 0,
            ],
        ]);

        $this->runExecuteAgentRunJob($run->id);

        $run->refresh();
        $metadata = (array) ($run->metadata_json ?? []);

        $this->assertSame(AgentJobRun::STATUS_TIMED_OUT, $run->status);
        $this->assertSame('timeout', $metadata['termination_mode'] ?? null);
    }

    public function test_silent_run_emits_heartbeat_lifecycle_events_while_active(): void
    {
        $silentExec = $this->sandboxBase.'/bin/silent-runner';
        file_put_contents($silentExec, "#!/bin/sh\nsleep 2\nexit 0\n");
        chmod($silentExec, 0755);

        config()->set('agent.runner_executables', [
            'claude' => $silentExec,
            'codex' => $silentExec,
            'custom' => $silentExec,
        ]);
        config()->set('agent.default_templates', [
            'claude' => $silentExec.' -p {{task_markdown_path}}',
            'codex' => $silentExec.' exec {{task_markdown_path}}',
        ]);
        config()->set('agent.run_output_heartbeat_seconds', 1);

        $user = User::factory()->create();
        $taskFile = $this->sandboxBase.'/tasks/silent-heartbeat.md';
        file_put_contents($taskFile, "# Silent Heartbeat\n");

        $job = AgentJob::query()->create([
            'user_id' => $user->id,
            'name' => 'Silent Heartbeat Job',
            'description' => null,
            'cron_expression' => '0 0 1 1 1',
            'timezone' => 'UTC',
            'is_enabled' => true,
            'max_runtime_seconds' => 10,
            'cooldown_seconds' => 0,
            'runner_type' => 'claude',
            'command_template' => config('agent.default_templates.claude'),
            'task_markdown_path' => $taskFile,
            'working_directory' => $this->sandboxBase.'/work',
        ]);

        $run = AgentJobRun::query()->create([
            'agent_job_id' => $job->id,
            'user_id' => $user->id,
            'initiated_by_user_id' => $user->id,
            'trigger_type' => AgentJobRun::TRIGGER_MANUAL,
            'status' => AgentJobRun::STATUS_QUEUED,
            'duration_ms' => 0,
            'stdout_bytes_pre' => 0,
            'stdout_bytes_post' => 0,
            'stderr_bytes_pre' => 0,
            'stderr_bytes_post' => 0,
            'metadata_json' => [
                'output_truncated' => false,
                'redaction_count' => 0,
            ],
        ]);

        $this->runExecuteAgentRunJob($run->id);

        $run->refresh();
        $heartbeatEvent = $run->events()
            ->where('event_type', 'lifecycle')
            ->where('payload', 'like', '%"type":"heartbeat"%')
            ->first();

        $this->assertSame(AgentJobRun::STATUS_SUCCEEDED, $run->status);
        $this->assertNotNull($heartbeatEvent);
    }

    public function test_rate_limit_output_sets_rate_limited_error_and_job_hold(): void
    {
        $rateLimitedExec = $this->sandboxBase.'/bin/rate-limited-runner';
        file_put_contents($rateLimitedExec, "#!/bin/sh\necho \"You've hit your limit · resets 3pm (Europe/London)\"\nexit 1\n");
        chmod($rateLimitedExec, 0755);

        config()->set('agent.runner_executables', [
            'claude' => $rateLimitedExec,
            'codex' => $rateLimitedExec,
            'custom' => $rateLimitedExec,
        ]);
        config()->set('agent.default_templates', [
            'claude' => $rateLimitedExec.' -p {{task_markdown_path}}',
            'codex' => $rateLimitedExec.' exec {{task_markdown_path}}',
        ]);
        config()->set('agent.rate_limit_default_hold_minutes', 15);

        $user = User::factory()->create();
        $taskFile = $this->sandboxBase.'/tasks/rate-limit.md';
        file_put_contents($taskFile, "# Rate Limit\n");

        $job = AgentJob::query()->create([
            'user_id' => $user->id,
            'name' => 'Rate Limited Job',
            'description' => null,
            'cron_expression' => '0 0 1 1 1',
            'timezone' => 'UTC',
            'is_enabled' => true,
            'max_runtime_seconds' => 60,
            'cooldown_seconds' => 0,
            'runner_type' => 'claude',
            'command_template' => config('agent.default_templates.claude'),
            'task_markdown_path' => $taskFile,
            'working_directory' => $this->sandboxBase.'/work',
        ]);

        $run = AgentJobRun::query()->create([
            'agent_job_id' => $job->id,
            'user_id' => $user->id,
            'initiated_by_user_id' => $user->id,
            'trigger_type' => AgentJobRun::TRIGGER_MANUAL,
            'status' => AgentJobRun::STATUS_QUEUED,
            'duration_ms' => 0,
            'stdout_bytes_pre' => 0,
            'stdout_bytes_post' => 0,
            'stderr_bytes_pre' => 0,
            'stderr_bytes_post' => 0,
            'metadata_json' => [
                'output_truncated' => false,
                'redaction_count' => 0,
            ],
        ]);

        $this->runExecuteAgentRunJob($run->id);

        $run->refresh();
        $metadata = (array) ($run->metadata_json ?? []);
        $state = AgentSystemState::query()->find(sprintf('job_rate_limit_hold_until:%d', $job->id));

        $this->assertSame(AgentJobRun::STATUS_FAILED, $run->status);
        $this->assertSame('RATE_LIMITED', $run->error_code);
        $this->assertTrue((bool) ($metadata['rate_limit_detected'] ?? false));
        $this->assertNotEmpty($metadata['rate_limit_hold_until'] ?? null);
        $this->assertNotNull($state);
    }

    public function test_rate_limit_phrase_inside_escaped_code_snippet_does_not_trigger_detection(): void
    {
        $snippetExec = $this->sandboxBase.'/bin/rate-limit-escaped-code-snippet-runner';
        $script = <<<'SH'
#!/bin/sh
cat <<'OUT'
private function finalizeTerminal(AgentJobRun $run, array $extra): void {\n    if (! isset($extra['error_summary'])) {\n        $extra['error_summary'] = 'Runner hit an upstream usage/rate limit.';\n    }\n}
OUT
exit 0
SH;
        file_put_contents($snippetExec, $script);
        chmod($snippetExec, 0755);

        config()->set('agent.runner_executables', [
            'claude' => $snippetExec,
            'codex' => $snippetExec,
            'custom' => $snippetExec,
        ]);
        config()->set('agent.default_templates', [
            'claude' => $snippetExec.' -p {{task_markdown_path}}',
            'codex' => $snippetExec.' exec {{task_markdown_path}}',
        ]);

        $user = User::factory()->create();
        $taskFile = $this->sandboxBase.'/tasks/rate-limit-escaped-code-snippet.md';
        file_put_contents($taskFile, "# Rate Limit Escaped Code Snippet\n");

        $job = AgentJob::query()->create([
            'user_id' => $user->id,
            'name' => 'Rate Limit Escaped Code Snippet',
            'description' => null,
            'cron_expression' => '0 0 1 1 1',
            'timezone' => 'UTC',
            'is_enabled' => true,
            'max_runtime_seconds' => 60,
            'cooldown_seconds' => 0,
            'runner_type' => 'claude',
            'command_template' => config('agent.default_templates.claude'),
            'task_markdown_path' => $taskFile,
            'working_directory' => $this->sandboxBase.'/work',
        ]);

        $run = AgentJobRun::query()->create([
            'agent_job_id' => $job->id,
            'user_id' => $user->id,
            'initiated_by_user_id' => $user->id,
            'trigger_type' => AgentJobRun::TRIGGER_MANUAL,
            'status' => AgentJobRun::STATUS_QUEUED,
            'duration_ms' => 0,
            'stdout_bytes_pre' => 0,
            'stdout_bytes_post' => 0,
            'stderr_bytes_pre' => 0,
            'stderr_bytes_post' => 0,
            'metadata_json' => [
                'output_truncated' => false,
                'redaction_count' => 0,
            ],
        ]);

        $this->runExecuteAgentRunJob($run->id);

        $run->refresh();
        $metadata = (array) ($run->metadata_json ?? []);
        $state = AgentSystemState::query()->find(sprintf('job_rate_limit_hold_until:%d', $job->id));

        $this->assertSame(AgentJobRun::STATUS_SUCCEEDED, $run->status);
        $this->assertFalse((bool) ($metadata['rate_limit_detected'] ?? false));
        $this->assertNull($metadata['rate_limit_hold_until'] ?? null);
        $this->assertNull($state);
    }

    public function test_rate_limit_phrase_inside_javascript_snippet_does_not_trigger_detection(): void
    {
        $snippetExec = $this->sandboxBase.'/bin/rate-limit-javascript-snippet-runner';
        $script = <<<'SH'
#!/bin/sh
cat <<'OUT'
did not succeed)\n            if (runStatus === 'succeeded' && !holdActive) {\n                return null;\n            }\n            return {\n                excerpt: String(metadata.rate_limit_excerpt ?? 'Upstream usage/rate limit detected.'),\n                holdUntil,\n                holdActive,\n            };\n        }\n    },\n    const openApprovalModal = (run) => {\n        if (approvalRefreshedAt.value == null)\nOUT
exit 0
SH;
        file_put_contents($snippetExec, $script);
        chmod($snippetExec, 0755);

        config()->set('agent.runner_executables', [
            'claude' => $snippetExec,
            'codex' => $snippetExec,
            'custom' => $snippetExec,
        ]);
        config()->set('agent.default_templates', [
            'claude' => $snippetExec.' -p {{task_markdown_path}}',
            'codex' => $snippetExec.' exec {{task_markdown_path}}',
        ]);

        $user = User::factory()->create();
        $taskFile = $this->sandboxBase.'/tasks/rate-limit-javascript-snippet.md';
        file_put_contents($taskFile, "# Rate Limit JavaScript Snippet\n");

        $job = AgentJob::query()->create([
            'user_id' => $user->id,
            'name' => 'Rate Limit JavaScript Snippet',
            'description' => null,
            'cron_expression' => '0 0 1 1 1',
            'timezone' => 'UTC',
            'is_enabled' => true,
            'max_runtime_seconds' => 60,
            'cooldown_seconds' => 0,
            'runner_type' => 'claude',
            'command_template' => config('agent.default_templates.claude'),
            'task_markdown_path' => $taskFile,
            'working_directory' => $this->sandboxBase.'/work',
        ]);

        $run = AgentJobRun::query()->create([
            'agent_job_id' => $job->id,
            'user_id' => $user->id,
            'initiated_by_user_id' => $user->id,
            'trigger_type' => AgentJobRun::TRIGGER_MANUAL,
            'status' => AgentJobRun::STATUS_QUEUED,
            'duration_ms' => 0,
            'stdout_bytes_pre' => 0,
            'stdout_bytes_post' => 0,
            'stderr_bytes_pre' => 0,
            'stderr_bytes_post' => 0,
            'metadata_json' => [
                'output_truncated' => false,
                'redaction_count' => 0,
            ],
        ]);

        $this->runExecuteAgentRunJob($run->id);

        $run->refresh();
        $metadata = (array) ($run->metadata_json ?? []);
        $state = AgentSystemState::query()->find(sprintf('job_rate_limit_hold_until:%d', $job->id));

        $this->assertSame(AgentJobRun::STATUS_SUCCEEDED, $run->status);
        $this->assertFalse((bool) ($metadata['rate_limit_detected'] ?? false));
        $this->assertNull($metadata['rate_limit_hold_until'] ?? null);
        $this->assertNull($state);
    }

    public function test_rate_limit_phrase_inside_structured_user_tool_result_event_does_not_trigger_detection(): void
    {
        $snippetExec = $this->sandboxBase.'/bin/rate-limit-structured-user-tool-result-runner';
        $script = <<<'SH'
#!/bin/sh
cat <<'OUT'
{"type":"user","message":{"role":"user","content":[{"tool_use_id":"tool_123","type":"tool_result","content":"PASS Tests\\Unit\\InterrogationCodexAdapterCommandTest\\nconst excerpt = String(metadata.rate_limit_excerpt ?? 'Upstream usage/rate limit detected.');"}]}}
OUT
exit 0
SH;
        file_put_contents($snippetExec, $script);
        chmod($snippetExec, 0755);

        config()->set('agent.runner_executables', [
            'claude' => $snippetExec,
            'codex' => $snippetExec,
            'custom' => $snippetExec,
        ]);
        config()->set('agent.default_templates', [
            'claude' => $snippetExec.' -p {{task_markdown_path}}',
            'codex' => $snippetExec.' exec {{task_markdown_path}}',
        ]);

        $user = User::factory()->create();
        $taskFile = $this->sandboxBase.'/tasks/rate-limit-structured-user-tool-result.md';
        file_put_contents($taskFile, "# Rate Limit Structured User Tool Result\n");

        $job = AgentJob::query()->create([
            'user_id' => $user->id,
            'name' => 'Rate Limit Structured User Tool Result',
            'description' => null,
            'cron_expression' => '0 0 1 1 1',
            'timezone' => 'UTC',
            'is_enabled' => true,
            'max_runtime_seconds' => 60,
            'cooldown_seconds' => 0,
            'runner_type' => 'claude',
            'command_template' => config('agent.default_templates.claude'),
            'task_markdown_path' => $taskFile,
            'working_directory' => $this->sandboxBase.'/work',
        ]);

        $run = AgentJobRun::query()->create([
            'agent_job_id' => $job->id,
            'user_id' => $user->id,
            'initiated_by_user_id' => $user->id,
            'trigger_type' => AgentJobRun::TRIGGER_MANUAL,
            'status' => AgentJobRun::STATUS_QUEUED,
            'duration_ms' => 0,
            'stdout_bytes_pre' => 0,
            'stdout_bytes_post' => 0,
            'stderr_bytes_pre' => 0,
            'stderr_bytes_post' => 0,
            'metadata_json' => [
                'output_truncated' => false,
                'redaction_count' => 0,
            ],
        ]);

        $this->runExecuteAgentRunJob($run->id);

        $run->refresh();
        $metadata = (array) ($run->metadata_json ?? []);
        $state = AgentSystemState::query()->find(sprintf('job_rate_limit_hold_until:%d', $job->id));

        $this->assertSame(AgentJobRun::STATUS_SUCCEEDED, $run->status);
        $this->assertFalse((bool) ($metadata['rate_limit_detected'] ?? false));
        $this->assertNull($metadata['rate_limit_hold_until'] ?? null);
        $this->assertNull($state);
    }

    public function test_structured_rate_limit_error_event_sets_rate_limit_metadata(): void
    {
        $errorExec = $this->sandboxBase.'/bin/structured-rate-limit-error-runner';
        $script = <<<'SH'
#!/bin/sh
cat <<'OUT'
{"type":"error","error":{"code":"rate_limit_exceeded","message":"429 Too many requests. Retry-After: 60"}}
OUT
exit 1
SH;
        file_put_contents($errorExec, $script);
        chmod($errorExec, 0755);

        config()->set('agent.runner_executables', [
            'claude' => $errorExec,
            'codex' => $errorExec,
            'custom' => $errorExec,
        ]);
        config()->set('agent.default_templates', [
            'claude' => $errorExec.' -p {{task_markdown_path}}',
            'codex' => $errorExec.' exec {{task_markdown_path}}',
        ]);

        $user = User::factory()->create();
        $taskFile = $this->sandboxBase.'/tasks/structured-rate-limit-error.md';
        file_put_contents($taskFile, "# Structured Rate Limit Error\n");

        $job = AgentJob::query()->create([
            'user_id' => $user->id,
            'name' => 'Structured Rate Limit Error',
            'description' => null,
            'cron_expression' => '0 0 1 1 1',
            'timezone' => 'UTC',
            'is_enabled' => true,
            'max_runtime_seconds' => 60,
            'cooldown_seconds' => 0,
            'runner_type' => 'claude',
            'command_template' => config('agent.default_templates.claude'),
            'task_markdown_path' => $taskFile,
            'working_directory' => $this->sandboxBase.'/work',
        ]);

        $run = AgentJobRun::query()->create([
            'agent_job_id' => $job->id,
            'user_id' => $user->id,
            'initiated_by_user_id' => $user->id,
            'trigger_type' => AgentJobRun::TRIGGER_MANUAL,
            'status' => AgentJobRun::STATUS_QUEUED,
            'duration_ms' => 0,
            'stdout_bytes_pre' => 0,
            'stdout_bytes_post' => 0,
            'stderr_bytes_pre' => 0,
            'stderr_bytes_post' => 0,
            'metadata_json' => [
                'output_truncated' => false,
                'redaction_count' => 0,
            ],
        ]);

        $this->runExecuteAgentRunJob($run->id);

        $run->refresh();
        $metadata = (array) ($run->metadata_json ?? []);
        $state = AgentSystemState::query()->find(sprintf('job_rate_limit_hold_until:%d', $job->id));

        $this->assertSame(AgentJobRun::STATUS_FAILED, $run->status);
        $this->assertSame('RATE_LIMITED', $run->error_code);
        $this->assertTrue((bool) ($metadata['rate_limit_detected'] ?? false));
        $this->assertNotEmpty($metadata['rate_limit_hold_until'] ?? null);
        $this->assertNotNull($state);
    }

    public function test_rate_limit_handling_summary_text_does_not_trigger_rate_limit_detection(): void
    {
        $summaryExec = $this->sandboxBase.'/bin/summary-runner';
        file_put_contents($summaryExec, "#!/bin/sh\necho \"Implemented rate limit handling (429 responses with Retry-After support)\"\nexit 0\n");
        chmod($summaryExec, 0755);

        config()->set('agent.runner_executables', [
            'claude' => $summaryExec,
            'codex' => $summaryExec,
            'custom' => $summaryExec,
        ]);
        config()->set('agent.default_templates', [
            'claude' => $summaryExec.' -p {{task_markdown_path}}',
            'codex' => $summaryExec.' exec {{task_markdown_path}}',
        ]);

        $user = User::factory()->create();
        $taskFile = $this->sandboxBase.'/tasks/rate-limit-summary.md';
        file_put_contents($taskFile, "# Rate Limit Summary\n");

        $job = AgentJob::query()->create([
            'user_id' => $user->id,
            'name' => 'Rate Limit Summary Job',
            'description' => null,
            'cron_expression' => '0 0 1 1 1',
            'timezone' => 'UTC',
            'is_enabled' => true,
            'max_runtime_seconds' => 60,
            'cooldown_seconds' => 0,
            'runner_type' => 'claude',
            'command_template' => config('agent.default_templates.claude'),
            'task_markdown_path' => $taskFile,
            'working_directory' => $this->sandboxBase.'/work',
        ]);

        $run = AgentJobRun::query()->create([
            'agent_job_id' => $job->id,
            'user_id' => $user->id,
            'initiated_by_user_id' => $user->id,
            'trigger_type' => AgentJobRun::TRIGGER_MANUAL,
            'status' => AgentJobRun::STATUS_QUEUED,
            'duration_ms' => 0,
            'stdout_bytes_pre' => 0,
            'stdout_bytes_post' => 0,
            'stderr_bytes_pre' => 0,
            'stderr_bytes_post' => 0,
            'metadata_json' => [
                'output_truncated' => false,
                'redaction_count' => 0,
            ],
        ]);

        $this->runExecuteAgentRunJob($run->id);

        $run->refresh();
        $metadata = (array) ($run->metadata_json ?? []);
        $state = AgentSystemState::query()->find(sprintf('job_rate_limit_hold_until:%d', $job->id));

        $this->assertSame(AgentJobRun::STATUS_SUCCEEDED, $run->status);
        $this->assertFalse((bool) ($metadata['rate_limit_detected'] ?? false));
        $this->assertNull($metadata['rate_limit_hold_until'] ?? null);
        $this->assertNull($state);
    }

    public function test_rate_limit_phrase_inside_line_numbered_snippet_does_not_trigger_detection(): void
    {
        $snippetExec = $this->sandboxBase.'/bin/rate-limit-snippet-runner';
        file_put_contents($snippetExec, "#!/bin/sh\necho \"    61→ return ErrorEnvelope::make(\" \necho \"    62→   'RATE_LIMITED',\" \necho \"    63→   'Too many requests. Please retry shortly.',\" \necho \"    64→   429\" \necho \"    65→ );\" \nexit 0\n");
        chmod($snippetExec, 0755);

        config()->set('agent.runner_executables', [
            'claude' => $snippetExec,
            'codex' => $snippetExec,
            'custom' => $snippetExec,
        ]);
        config()->set('agent.default_templates', [
            'claude' => $snippetExec.' -p {{task_markdown_path}}',
            'codex' => $snippetExec.' exec {{task_markdown_path}}',
        ]);

        $user = User::factory()->create();
        $taskFile = $this->sandboxBase.'/tasks/rate-limit-snippet.md';
        file_put_contents($taskFile, "# Rate Limit Snippet\n");

        $job = AgentJob::query()->create([
            'user_id' => $user->id,
            'name' => 'Rate Limit Snippet Job',
            'description' => null,
            'cron_expression' => '0 0 1 1 1',
            'timezone' => 'UTC',
            'is_enabled' => true,
            'max_runtime_seconds' => 60,
            'cooldown_seconds' => 0,
            'runner_type' => 'claude',
            'command_template' => config('agent.default_templates.claude'),
            'task_markdown_path' => $taskFile,
            'working_directory' => $this->sandboxBase.'/work',
        ]);

        $run = AgentJobRun::query()->create([
            'agent_job_id' => $job->id,
            'user_id' => $user->id,
            'initiated_by_user_id' => $user->id,
            'trigger_type' => AgentJobRun::TRIGGER_MANUAL,
            'status' => AgentJobRun::STATUS_QUEUED,
            'duration_ms' => 0,
            'stdout_bytes_pre' => 0,
            'stdout_bytes_post' => 0,
            'stderr_bytes_pre' => 0,
            'stderr_bytes_post' => 0,
            'metadata_json' => [
                'output_truncated' => false,
                'redaction_count' => 0,
            ],
        ]);

        $this->runExecuteAgentRunJob($run->id);

        $run->refresh();
        $metadata = (array) ($run->metadata_json ?? []);

        $this->assertSame(AgentJobRun::STATUS_SUCCEEDED, $run->status);
        $this->assertFalse((bool) ($metadata['rate_limit_detected'] ?? false));
    }

    public function test_rate_limit_phrase_inside_ascii_arrow_line_numbered_snippet_does_not_trigger_detection(): void
    {
        $snippetExec = $this->sandboxBase.'/bin/rate-limit-ascii-snippet-runner';
        file_put_contents($snippetExec, "#!/bin/sh\necho \"  12->use Illuminate\\\\Support\\\\Facades\\\\RateLimiter;\" \necho \"  13->'RATE_LIMITED',\" \necho \"  14->'Too many requests. Please retry shortly.',\" \necho \"  15->429\" \nexit 0\n");
        chmod($snippetExec, 0755);

        config()->set('agent.runner_executables', [
            'claude' => $snippetExec,
            'codex' => $snippetExec,
            'custom' => $snippetExec,
        ]);
        config()->set('agent.default_templates', [
            'claude' => $snippetExec.' -p {{task_markdown_path}}',
            'codex' => $snippetExec.' exec {{task_markdown_path}}',
        ]);

        $user = User::factory()->create();
        $taskFile = $this->sandboxBase.'/tasks/rate-limit-ascii-snippet.md';
        file_put_contents($taskFile, "# Rate Limit ASCII Snippet\n");

        $job = AgentJob::query()->create([
            'user_id' => $user->id,
            'name' => 'Rate Limit ASCII Snippet Job',
            'description' => null,
            'cron_expression' => '0 0 1 1 1',
            'timezone' => 'UTC',
            'is_enabled' => true,
            'max_runtime_seconds' => 60,
            'cooldown_seconds' => 0,
            'runner_type' => 'claude',
            'command_template' => config('agent.default_templates.claude'),
            'task_markdown_path' => $taskFile,
            'working_directory' => $this->sandboxBase.'/work',
        ]);

        $run = AgentJobRun::query()->create([
            'agent_job_id' => $job->id,
            'user_id' => $user->id,
            'initiated_by_user_id' => $user->id,
            'trigger_type' => AgentJobRun::TRIGGER_MANUAL,
            'status' => AgentJobRun::STATUS_QUEUED,
            'duration_ms' => 0,
            'stdout_bytes_pre' => 0,
            'stdout_bytes_post' => 0,
            'stderr_bytes_pre' => 0,
            'stderr_bytes_post' => 0,
            'metadata_json' => [
                'output_truncated' => false,
                'redaction_count' => 0,
            ],
        ]);

        $this->runExecuteAgentRunJob($run->id);

        $run->refresh();
        $metadata = (array) ($run->metadata_json ?? []);

        $this->assertSame(AgentJobRun::STATUS_SUCCEEDED, $run->status);
        $this->assertFalse((bool) ($metadata['rate_limit_detected'] ?? false));
    }

    public function test_rate_limit_phrase_inside_escaped_newline_line_numbered_snippet_does_not_trigger_detection(): void
    {
        $snippetExec = $this->sandboxBase.'/bin/rate-limit-escaped-newline-snippet-runner';
        file_put_contents($snippetExec, "#!/bin/sh\necho \"message:{\\\"content\\\":\\\"1-><?php\\\\n2->use Illuminate\\\\\\\\Cache\\\\\\\\RateLimiting\\\\\\\\Limit;\\\\n3->'Too many requests. Please retry shortly.',\\\\n4->429\\\"}\" \nexit 0\n");
        chmod($snippetExec, 0755);

        config()->set('agent.runner_executables', [
            'claude' => $snippetExec,
            'codex' => $snippetExec,
            'custom' => $snippetExec,
        ]);
        config()->set('agent.default_templates', [
            'claude' => $snippetExec.' -p {{task_markdown_path}}',
            'codex' => $snippetExec.' exec {{task_markdown_path}}',
        ]);

        $user = User::factory()->create();
        $taskFile = $this->sandboxBase.'/tasks/rate-limit-escaped-newline-snippet.md';
        file_put_contents($taskFile, "# Rate Limit Escaped Newline Snippet\n");

        $job = AgentJob::query()->create([
            'user_id' => $user->id,
            'name' => 'Rate Limit Escaped Newline Snippet Job',
            'description' => null,
            'cron_expression' => '0 0 1 1 1',
            'timezone' => 'UTC',
            'is_enabled' => true,
            'max_runtime_seconds' => 60,
            'cooldown_seconds' => 0,
            'runner_type' => 'claude',
            'command_template' => config('agent.default_templates.claude'),
            'task_markdown_path' => $taskFile,
            'working_directory' => $this->sandboxBase.'/work',
        ]);

        $run = AgentJobRun::query()->create([
            'agent_job_id' => $job->id,
            'user_id' => $user->id,
            'initiated_by_user_id' => $user->id,
            'trigger_type' => AgentJobRun::TRIGGER_MANUAL,
            'status' => AgentJobRun::STATUS_QUEUED,
            'duration_ms' => 0,
            'stdout_bytes_pre' => 0,
            'stdout_bytes_post' => 0,
            'stderr_bytes_pre' => 0,
            'stderr_bytes_post' => 0,
            'metadata_json' => [
                'output_truncated' => false,
                'redaction_count' => 0,
            ],
        ]);

        $this->runExecuteAgentRunJob($run->id);

        $run->refresh();
        $metadata = (array) ($run->metadata_json ?? []);

        $this->assertSame(AgentJobRun::STATUS_SUCCEEDED, $run->status);
        $this->assertFalse((bool) ($metadata['rate_limit_detected'] ?? false));
    }

    public function test_rate_limit_phrase_inside_double_escaped_newline_line_numbered_snippet_does_not_trigger_detection(): void
    {
        $snippetExec = $this->sandboxBase.'/bin/rate-limit-double-escaped-newline-snippet-runner';
        $script = <<<'SH'
#!/bin/sh
cat <<'OUT'
message:{"content":"):\\\\n 55->\\\\n 56-> RateLimiter::for(\"agent-mutations\", function (Request $request) {\\\\n 57-> return [\\\\n 58-> \"Too many requests. Please retry shortly.\",\\\\n 59-> ];"}
OUT
exit 0
SH;
        file_put_contents($snippetExec, $script);
        chmod($snippetExec, 0755);

        config()->set('agent.runner_executables', [
            'claude' => $snippetExec,
            'codex' => $snippetExec,
            'custom' => $snippetExec,
        ]);
        config()->set('agent.default_templates', [
            'claude' => $snippetExec.' -p {{task_markdown_path}}',
            'codex' => $snippetExec.' exec {{task_markdown_path}}',
        ]);

        $user = User::factory()->create();
        $taskFile = $this->sandboxBase.'/tasks/rate-limit-double-escaped-newline-snippet.md';
        file_put_contents($taskFile, "# Rate Limit Double Escaped Newline Snippet\n");

        $job = AgentJob::query()->create([
            'user_id' => $user->id,
            'name' => 'Rate Limit Double Escaped Newline Snippet Job',
            'description' => null,
            'cron_expression' => '0 0 1 1 1',
            'timezone' => 'UTC',
            'is_enabled' => true,
            'max_runtime_seconds' => 60,
            'cooldown_seconds' => 0,
            'runner_type' => 'claude',
            'command_template' => config('agent.default_templates.claude'),
            'task_markdown_path' => $taskFile,
            'working_directory' => $this->sandboxBase.'/work',
        ]);

        $run = AgentJobRun::query()->create([
            'agent_job_id' => $job->id,
            'user_id' => $user->id,
            'initiated_by_user_id' => $user->id,
            'trigger_type' => AgentJobRun::TRIGGER_MANUAL,
            'status' => AgentJobRun::STATUS_QUEUED,
            'duration_ms' => 0,
            'stdout_bytes_pre' => 0,
            'stdout_bytes_post' => 0,
            'stderr_bytes_pre' => 0,
            'stderr_bytes_post' => 0,
            'metadata_json' => [
                'output_truncated' => false,
                'redaction_count' => 0,
            ],
        ]);

        $this->runExecuteAgentRunJob($run->id);

        $run->refresh();
        $metadata = (array) ($run->metadata_json ?? []);

        $this->assertSame(AgentJobRun::STATUS_SUCCEEDED, $run->status);
        $this->assertFalse((bool) ($metadata['rate_limit_detected'] ?? false));
    }

    private function runExecuteAgentRunJob(int $runId): void
    {
        $job = new ExecuteAgentRunJob($runId);
        app()->call([$job, 'handle']);
    }
}
