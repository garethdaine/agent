<?php

namespace App\Support\Interrogation;

use App\Jobs\ExecuteAgentRunJob;
use App\Models\AgentJob;
use App\Models\AgentJobRun;
use App\Models\InterrogationBuildTask;
use App\Models\InterrogationSession;
use App\Support\Agent\TaskMarkdownStorage;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class BuildTaskRunFactory
{
    public function __construct(private readonly TaskMarkdownStorage $taskMarkdownStorage) {}

    public function create(InterrogationSession $session, InterrogationBuildTask $task): AgentJobRun
    {
        $commandTemplate = (string) (config('agent.default_templates.'.$session->runner_type) ?: '');

        if ($commandTemplate === '') {
            throw new RuntimeException(sprintf('No default command template configured for runner [%s].', $session->runner_type));
        }

        $markdownPath = $this->taskMarkdownStorage->persistInlineContent(
            $this->buildTaskMarkdown($session, $task),
            (int) $session->user_id,
        );

        $timestamp = CarbonImmutable::now('UTC')->format('YmdHis');

        /** @var AgentJobRun $run */
        $run = DB::transaction(function () use ($session, $task, $markdownPath, $commandTemplate, $timestamp): AgentJobRun {
            $job = AgentJob::query()->create([
                'user_id' => $session->user_id,
                'name' => sprintf('Interrogation Build S%d T%02d %s', (int) $session->id, (int) $task->sequence, $timestamp),
                'description' => trim((string) ($task->description ?? '')) !== ''
                    ? trim((string) $task->description)
                    : 'Auto-generated build task job from interrogation planning flow.',
                'cron_expression' => '0 0 1 1 0',
                'timezone' => 'UTC',
                'is_enabled' => false,
                'max_runtime_seconds' => 60 * 60,
                'cooldown_seconds' => 0,
                'runner_type' => $session->runner_type,
                'command_template' => $commandTemplate,
                'task_markdown_path' => $markdownPath,
                'working_directory' => $session->project_directory,
                'env_json' => [
                    'INTERROGATION_SESSION_ID' => (string) $session->id,
                    'INTERROGATION_BUILD_TASK_ID' => (string) $task->id,
                ],
            ]);

            return AgentJobRun::query()->create([
                'agent_job_id' => $job->id,
                'user_id' => $session->user_id,
                'initiated_by_user_id' => $session->user_id,
                'trigger_type' => AgentJobRun::TRIGGER_MANUAL,
                'status' => AgentJobRun::STATUS_QUEUED,
                'metadata_json' => [
                    'source' => 'interrogation_build',
                    'interrogation_session_id' => (int) $session->id,
                    'interrogation_build_task_id' => (int) $task->id,
                ],
            ]);
        });

        ExecuteAgentRunJob::dispatch((int) $run->id)
            ->onConnection('redis')
            ->onQueue('agent');

        return $run;
    }

    private function buildTaskMarkdown(InterrogationSession $session, InterrogationBuildTask $task): string
    {
        $summary = is_array($session->summary_json) ? $session->summary_json : [];
        $plan = is_array($session->plan_json) ? $session->plan_json : [];
        $taskMetadata = is_array($task->metadata_json) ? $task->metadata_json : [];

        $clarifications = is_array($taskMetadata['clarifications'] ?? null)
            ? array_values($taskMetadata['clarifications'])
            : [];

        $content = [];
        $content[] = '# Build Task';
        $content[] = '';
        $content[] = 'Session ID: '.(string) $session->id;
        $content[] = 'Task Sequence: '.(string) $task->sequence;
        $content[] = 'Task Title: '.trim((string) $task->title);
        $content[] = '';

        $description = trim((string) ($task->description ?? ''));
        if ($description !== '') {
            $content[] = '## Objective';
            $content[] = '';
            $content[] = $description;
            $content[] = '';
        }

        $instructions = trim((string) ($task->instructions_markdown ?? ''));
        if ($instructions !== '') {
            $content[] = '## Instructions';
            $content[] = '';
            $content[] = $instructions;
            $content[] = '';
        }

        $summaryMarkdown = trim((string) ($summary['summary_markdown'] ?? ''));
        if ($summaryMarkdown !== '') {
            $content[] = '## Summary Context';
            $content[] = '';
            $content[] = $summaryMarkdown;
            $content[] = '';
        }

        $planMarkdown = trim((string) ($plan['plan_markdown'] ?? ''));
        if ($planMarkdown !== '') {
            $content[] = '## Plan Context';
            $content[] = '';
            $content[] = $planMarkdown;
            $content[] = '';
        }

        if ($clarifications !== []) {
            $content[] = '## Clarifications';
            $content[] = '';

            foreach ($clarifications as $item) {
                if (! is_array($item)) {
                    continue;
                }

                $message = trim((string) ($item['message'] ?? ''));
                if ($message === '') {
                    continue;
                }

                $at = trim((string) ($item['at'] ?? ''));
                $line = '- '.$message;
                if ($at !== '') {
                    $line .= ' ('.$at.')';
                }

                $content[] = $line;
            }

            $content[] = '';
        }

        $content[] = '## Output Expectations';
        $content[] = '';
        $content[] = '- Make concrete changes in the repository that satisfy this task.';
        $content[] = '- Run relevant tests or validation commands when possible.';
        $content[] = '- If blocked, report precise blockers and impacted files.';

        return trim(implode("\n", $content))."\n";
    }
}
