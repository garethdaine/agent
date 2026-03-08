<?php

namespace App\Support\Interrogation;

use App\Contracts\OrchestrationPolicyServiceContract;
use App\Jobs\ExecuteAgentRunJob;
use App\Models\AgentJob;
use App\Models\AgentJobRun;
use App\Models\InterrogationBuildTask;
use App\Models\InterrogationSession;
use App\Support\Agent\TaskMarkdownStorage;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class BuildTaskRunFactory
{
    public function __construct(
        private readonly TaskMarkdownStorage $taskMarkdownStorage,
        private readonly OrchestrationPolicyServiceContract $policyService
    ) {}

    public function create(InterrogationSession $session, InterrogationBuildTask $task): AgentJobRun
    {
        $commandTemplate = $this->resolveCommandTemplate($session->runner_type, $session->model);

        if ($commandTemplate === '') {
            throw new RuntimeException(sprintf('No default command template configured for runner [%s].', $session->runner_type));
        }

        // Pre-run policy evaluation: evaluate complexity and requirements
        $complianceMetadata = [];
        if ($this->policyService->isEnabled()) {
            $preRunResult = $this->policyService->evaluatePreRun($task, [
                'task_category' => $task->task_category?->value ?? 'custom',
            ]);
            $complianceMetadata = $preRunResult->metadataPatch;

            // Store compliance metadata in the task
            $task->setComplianceMetadata($complianceMetadata);
            $task->save();
        }

        $markdownPath = $this->taskMarkdownStorage->persistInlineContent(
            $this->buildTaskMarkdown($session, $task),
            (int) $session->user_id,
        );

        $jobName = $this->buildJobName($session, $task);

        /** @var AgentJobRun $run */
        $run = DB::transaction(function () use ($session, $task, $markdownPath, $commandTemplate, $jobName, $complianceMetadata): AgentJobRun {
            $job = $this->resolveReusableJob($session, $task, $jobName) ?? new AgentJob([
                'user_id' => $session->user_id,
            ]);

            $job->name = $jobName;
            $job->description = trim((string) ($task->description ?? '')) !== ''
                ? trim((string) $task->description)
                : 'Auto-generated build task job from interrogation planning flow.';
            $job->cron_expression = '0 0 1 1 0';
            $job->timezone = 'UTC';
            $job->is_enabled = false;
            $job->max_runtime_seconds = 60 * 60;
            $job->cooldown_seconds = 0;
            $job->runner_type = $session->runner_type;
            $job->command_template = $commandTemplate;
            $job->task_markdown_path = $markdownPath;
            $job->working_directory = $session->project_directory;
            $job->env_json = $this->buildIsolatedRunEnvironment($session, $task);
            $job->save();

            // Merge compliance metadata into run metadata
            $runMetadata = array_merge(
                [
                    'source' => 'interrogation_build',
                    'interrogation_session_id' => (int) $session->id,
                    'interrogation_build_task_id' => (int) $task->id,
                ],
                $complianceMetadata
            );

            return AgentJobRun::query()->create([
                'agent_job_id' => $job->id,
                'user_id' => $session->user_id,
                'initiated_by_user_id' => $session->user_id,
                'trigger_type' => AgentJobRun::TRIGGER_MANUAL,
                'status' => AgentJobRun::STATUS_QUEUED,
                'metadata_json' => $runMetadata,
            ]);
        });

        ExecuteAgentRunJob::dispatch((int) $run->id)
            ->onConnection('redis')
            ->onQueue('agent');

        return $run;
    }

    private function buildJobName(InterrogationSession $session, InterrogationBuildTask $task): string
    {
        return sprintf('Interrogation Build S%d T%02d', (int) $session->id, (int) $task->sequence);
    }

    private function resolveReusableJob(
        InterrogationSession $session,
        InterrogationBuildTask $task,
        string $jobName,
    ): ?AgentJob {
        $current = AgentJob::query()
            ->where('user_id', $session->user_id)
            ->where('name', $jobName)
            ->latest('id')
            ->first();

        if ($current !== null) {
            return $current;
        }

        return AgentJob::query()
            ->where('user_id', $session->user_id)
            ->where('name', 'like', sprintf('Interrogation Build S%d T%02d %%', (int) $session->id, (int) $task->sequence))
            ->latest('id')
            ->first();
    }

    private function resolveCommandTemplate(string $runnerType, ?string $sessionModel): string
    {
        $buildTemplate = trim((string) (config('agent.interrogation.build_execution_templates.'.$runnerType) ?: ''));
        if ($buildTemplate === '') {
            $buildTemplate = trim((string) (config('agent.default_templates.'.$runnerType) ?: ''));
        }

        if ($buildTemplate === '' || $sessionModel === null || trim($sessionModel) === '') {
            return $buildTemplate;
        }

        $sessionModel = trim($sessionModel);

        // Replace existing --model / -m flag value with the session-specific model
        if ($runnerType === 'claude') {
            $buildTemplate = preg_replace('/--model\s+\S+/', '--model '.$sessionModel, $buildTemplate) ?? $buildTemplate;
        } elseif ($runnerType === 'codex') {
            $buildTemplate = preg_replace('/-m\s+\S+/', '-m '.$sessionModel, $buildTemplate) ?? $buildTemplate;
        }

        return $buildTemplate;
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

        $techStacks = $session->techStacks()->ordered()->get(['name', 'documentation_url']);
        if ($techStacks->isNotEmpty()) {
            $content[] = '## Tech Stack Context';
            $content[] = '';

            foreach ($techStacks as $stack) {
                $name = trim((string) $stack->name);
                $documentationUrl = trim((string) $stack->documentation_url);

                if ($name === '' || $documentationUrl === '') {
                    continue;
                }

                $content[] = sprintf('- %s: %s', $name, $documentationUrl);
            }

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

        $projectRules = $this->projectRulesFromMetadata($session);
        if ($projectRules !== []) {
            $content[] = '## Project Rules Context';
            $content[] = '';

            foreach ($projectRules as $rule) {
                $source = (string) ($rule['source'] ?? 'manual');
                $filename = trim((string) ($rule['filename'] ?? ''));
                $label = (string) ($rule['title'] ?? 'Rule');
                if ($source === 'uploaded' && $filename !== '') {
                    $content[] = '### '.$label.' (uploaded: '.$filename.')';
                } else {
                    $content[] = '### '.$label;
                }

                $content[] = '';
                $content[] = trim((string) ($rule['markdown'] ?? ''));
                $content[] = '';
            }
        }

        $content[] = '## Runner Mode';
        $content[] = '';
        $content[] = '- This build run is non-interactive and must complete end-to-end in one pass.';
        $content[] = '- Do not pause for user check-ins, confirmations, or "I will do X next" handoffs.';
        $content[] = '- If repository instructions request a check-in before implementation, perform the check internally and continue execution in this same run.';
        $content[] = '- A run that exits after planning only is considered incomplete.';
        $content[] = '';

        $content[] = '## Mandatory Workflow';
        $content[] = '';
        $content[] = '1. State assumptions and scope boundaries before writing code.';
        $content[] = '2. Write or update tests first for the target behavior or refactor.';
        $content[] = '3. Run tests to confirm they fail for the intended reason.';
        $content[] = '4. Implement the smallest, cleanest code change that makes the tests pass.';
        $content[] = '5. Re-run relevant tests and report exact verification results.';
        $content[] = '';
        $content[] = '## Code Field Rules';
        $content[] = '';
        $content[] = '- Do not write code before stating assumptions.';
        $content[] = '- Do not claim correctness you have not verified.';
        $content[] = '- Do not handle only the happy path.';
        $content[] = '- Under what conditions does this work? Document those conditions.';
        $content[] = '- Before coding, identify malicious-caller and tired-maintainer failure modes.';
        $content[] = '- After coding, document what is handled, what is not handled, and known limitations.';
        $content[] = '';
        $content[] = '## Output Expectations';
        $content[] = '';
        $content[] = '- Make concrete changes in the repository that satisfy this task.';
        $content[] = '- Run relevant tests or validation commands when possible.';
        $content[] = '- For this repository, run tests with `php artisan test` (or `composer test` when full preflight is needed). Build runs already pin `APP_ENV=testing` and `DB_CONNECTION=pgsql_testing` to an isolated test database.';
        $content[] = '- Do not emit generic PostgreSQL disclaimers unless you actually ran a test command and captured a failing output that proves the issue.';
        $content[] = '- Include assumptions, conditions for correctness, and explicit non-goals in the final report.';
        $content[] = '- If blocked, report precise blockers and impacted files.';
        $content[] = '';
        $content[] = '## Safety Constraints';
        $content[] = '';
        $content[] = '- Never run destructive database commands (`php artisan migrate:fresh`, `php artisan migrate:refresh`, `php artisan db:wipe`, manual `DROP`/`TRUNCATE`).';
        $content[] = '- Do not override DB environment variables during execution.';
        $content[] = '- Treat the application database as immutable for this task; if a task appears to require destructive DB work, stop and report a blocker instead.';

        return trim(implode("\n", $content))."\n";
    }

    /**
     * @return array<int, array{title:string,markdown:string,source:string,filename:?string}>
     */
    private function projectRulesFromMetadata(InterrogationSession $session): array
    {
        $metadata = is_array($session->metadata_json) ? $session->metadata_json : [];
        $build = is_array($metadata['build'] ?? null) ? $metadata['build'] : [];
        $rules = is_array($build['project_rules'] ?? null) ? $build['project_rules'] : [];
        $normalized = [];

        foreach ($rules as $rule) {
            if (! is_array($rule)) {
                continue;
            }

            $title = trim((string) ($rule['title'] ?? ''));
            $markdown = trim((string) ($rule['markdown'] ?? ''));
            if ($markdown === '') {
                continue;
            }

            $source = strtolower(trim((string) ($rule['source'] ?? 'manual')));
            if (! in_array($source, ['manual', 'uploaded'], true)) {
                $source = 'manual';
            }

            $filename = trim((string) ($rule['filename'] ?? ''));

            $normalized[] = [
                'title' => $title !== '' ? $title : 'Rule '.(count($normalized) + 1),
                'markdown' => $markdown,
                'source' => $source,
                'filename' => $filename !== '' ? $filename : null,
            ];
        }

        return array_values($normalized);
    }

    /**
     * Build interrogation-specific environment overrides for env_json.
     *
     * Database isolation vars (DB_CONNECTION, TEST_DB_*, APP_ENV, QUEUE_CONNECTION)
     * are NOT set here — they are injected by DatabaseIsolationEnvironment::build()
     * at execution time in ExecuteAgentRunJob::mergedEnvironment(). Setting them
     * here would trigger EnvPolicy violations since those keys are forbidden in
     * user-facing env_json to prevent database credential tampering.
     *
     * @return array<string, string>
     */
    private function buildIsolatedRunEnvironment(InterrogationSession $session, InterrogationBuildTask $task): array
    {
        $sandboxDir = storage_path('framework/interrogation-build');
        if (! is_dir($sandboxDir)) {
            @mkdir($sandboxDir, 0777, true);
        }

        $sandboxDatabasePath = $sandboxDir.'/session-'.(int) $session->id.'.sqlite';
        if (! file_exists($sandboxDatabasePath)) {
            @touch($sandboxDatabasePath);
        }

        return [
            'AGENT_JOB_SOURCE' => 'interrogation_build',
            'INTERROGATION_SESSION_ID' => (string) $session->id,
            'INTERROGATION_BUILD_TASK_ID' => (string) $task->id,
            'INTERROGATION_DB_ISOLATED' => '1',
            'INTERROGATION_BUILD_DB_PATH' => $sandboxDatabasePath,
            'CACHE_STORE' => 'array',
            'SESSION_DRIVER' => 'array',
        ];
    }
}
