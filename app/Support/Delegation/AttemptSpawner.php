<?php

namespace App\Support\Delegation;

use App\Jobs\DelegationAttemptCompletedJob;
use App\Jobs\ExecuteAgentRunJob;
use App\Models\AgentJob;
use App\Models\AgentJobRun;
use App\Models\DelegateeProfile;
use App\Models\DelegationAttempt;
use App\Models\DelegationTask;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\File;

/**
 * Spawns delegation attempts by creating the necessary records and dispatching
 * the execution job chain.
 *
 * Spawn Flow:
 * 1. Use ContractEnforcer to get narrowed config based on profile boundaries
 * 2. Inject scope warnings into task's metadata_json if any scope was narrowed
 * 3. Create DelegationAttempt record with STATUS_RUNNING
 * 4. Create transient AgentJob from DelegateeProfile config with 'delegation' source tag
 * 5. Create AgentJobRun linked to the transient job
 * 6. Link attempt to the AgentJobRun
 * 7. Dispatch job chain: ExecuteAgentRunJob -> DelegationAttemptCompletedJob
 */
class AttemptSpawner
{
    private const ALLOWED_RUNNER_TYPES = ['claude', 'codex', 'custom'];

    public function __construct(
        private readonly ContractEnforcer $enforcer
    ) {}

    /**
     * Spawn a new delegation attempt for a task.
     *
     * @param  DelegationTask  $task  The task to execute
     * @param  DelegateeProfile  $profile  The profile that will execute the task
     * @return DelegationAttempt The created attempt record
     */
    public function spawn(DelegationTask $task, DelegateeProfile $profile): DelegationAttempt
    {
        // Enforce contract and capture warnings
        $enforcement = $this->enforcer->enforce($task->contract_json, $profile);
        if ($enforcement->wasNarrowed()) {
            $task->update([
                'metadata_json' => array_merge($task->metadata_json ?? [], [
                    'scope_warnings' => $enforcement->warnings(),
                ]),
            ]);
        }

        // Create attempt with incremented attempt number
        $attemptNumber = ($task->attempts()->max('attempt_number') ?? 0) + 1;
        $attempt = DelegationAttempt::create([
            'delegation_task_id' => $task->id,
            'delegatee_profile_id' => $profile->id,
            'status' => DelegationAttempt::STATUS_RUNNING,
            'attempt_number' => $attemptNumber,
            'started_at' => now(),
        ]);

        // Create transient AgentJob from DelegateeProfile config
        // Note: cron_expression, timezone, max_runtime_seconds, task_markdown_path are required
        // For delegation jobs, we use placeholder values since they run once, not on schedule
        $runnerType = in_array($profile->runner_type, self::ALLOWED_RUNNER_TYPES, true)
            ? $profile->runner_type
            : 'custom';

        $commandTemplate = $this->ensureAutonomousTemplate($profile->command_template, $runnerType);

        $taskMarkdownPath = $task->contract_json['task_markdown_path']
            ?? $this->generateTaskMarkdown($task, $attempt);

        $job = AgentJob::create([
            'user_id' => $task->graph->user_id,
            'name' => sprintf('Delegation: %s #%d', $task->name, $attemptNumber),
            'runner_type' => $runnerType,
            'command_template' => $commandTemplate,
            'working_directory' => $profile->working_directory,
            'env_json' => $profile->env_json,
            'cron_expression' => '@once',
            'timezone' => config('app.timezone', 'UTC'),
            'max_runtime_seconds' => $task->contract_json['authority_scope']['max_runtime_seconds'] ?? 3600,
            'task_markdown_path' => $taskMarkdownPath,
            'is_enabled' => false,
        ]);

        // Create AgentJobRun with delegation source in metadata
        // Note: trigger_type constraint only allows 'schedule' or 'manual',
        // so we use 'manual' and track 'delegation' source in metadata_json
        $run = AgentJobRun::create([
            'agent_job_id' => $job->id,
            'user_id' => $task->graph->user_id,
            'trigger_type' => AgentJobRun::TRIGGER_MANUAL,
            'status' => AgentJobRun::STATUS_QUEUED,
            'metadata_json' => [
                'source' => 'delegation',
                'task_id' => $task->id,
                'graph_id' => $task->graph->id,
                'attempt_id' => $attempt->id,
            ],
        ]);

        // Link attempt to the run
        $attempt->update(['agent_job_run_id' => $run->id]);

        // Dispatch job chain
        Bus::chain([
            new ExecuteAgentRunJob($run->id),
            new DelegationAttemptCompletedJob($attempt->id),
        ])->onQueue('agent')->dispatch();

        return $attempt;
    }

    private function ensureAutonomousTemplate(string $template, string $runnerType): string
    {
        if ($runnerType === 'custom' && ! str_contains($template, '{{task_markdown_path}}')) {
            $template = trim($template).' {{task_markdown_path}}';
        }

        if ($runnerType === 'claude' && ! str_contains($template, '--dangerously-skip-permissions')) {
            $template = str_replace(' -p ', ' --dangerously-skip-permissions -p ', $template);
        }

        if ($runnerType === 'codex' && ! str_contains($template, '--dangerously-bypass-approvals-and-sandbox')) {
            $template = str_replace(' exec ', ' --dangerously-bypass-approvals-and-sandbox exec ', $template);
        }

        return $template;
    }

    private function generateTaskMarkdown(DelegationTask $task, DelegationAttempt $attempt): string
    {
        $contract = $task->contract_json ?? [];
        $context = $contract['context_inputs'] ?? [];
        $instructions = $contract['phase_config']['instructions'] ?? '';

        $lines = [
            "# {$task->name}",
            '',
        ];

        if ($instructions !== '') {
            $lines[] = $instructions;
            $lines[] = '';
        }

        if (! empty($context)) {
            $lines[] = '## Context';
            $lines[] = '';
            foreach ($context as $key => $value) {
                $display = is_array($value) ? implode(', ', array_map('strval', (array) $value)) : (string) $value;
                $lines[] = "- **{$key}**: {$display}";
            }
            $lines[] = '';
        }

        $lines[] = '## Metadata';
        $lines[] = '';
        $lines[] = "- Graph: {$task->graph->name}";
        $lines[] = "- Task ID: {$task->id}";
        $lines[] = "- Attempt: {$attempt->attempt_number}";
        $lines[] = '';

        $dir = storage_path('app/delegation');

        File::ensureDirectoryExists($dir);

        $filename = sprintf('%s-%s-%d.md', now()->format('Ymd-His'), $task->id, $attempt->attempt_number);
        $path = $dir.'/'.$filename;
        File::put($path, implode("\n", $lines));

        return $path;
    }
}
