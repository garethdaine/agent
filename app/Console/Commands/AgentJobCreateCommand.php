<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use App\Rules\NumericCronExpression;
use App\Support\Agent\CommandPolicy;
use App\Support\Agent\PathPolicy;
use App\Support\Agent\TaskMarkdownStorage;
use App\Support\Agent\WorkflowKey;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class AgentJobCreateCommand extends Command
{
    protected $signature = 'agent:job:create
        {--name= : Job name (min 3 chars)}
        {--cron= : Cron expression (e.g. "0 9 * * 1-5")}
        {--runner-type=codex : Runner type (claude, codex, custom)}
        {--user= : User ID or email to own the job (default: first user)}
        {--working-directory= : Absolute working directory path}
        {--task-markdown-path= : Absolute path to task .md file}
        {--task-file= : Path to .md file to read and persist as inline content}
        {--timezone=UTC : Timezone (e.g. UTC, America/New_York)}
        {--max-runtime=300 : Max run time in seconds (10-86400)}
        {--cooldown=0 : Cooldown between runs in seconds (0-86400)}
        {--description= : Optional description}
        {--disabled : Create job disabled}';

    protected $description = 'Create an agent job with optional runner type (claude, codex, custom)';

    public function handle(
        CommandPolicy $commandPolicy,
        PathPolicy $pathPolicy,
        TaskMarkdownStorage $taskMarkdownStorage
    ): int {
        $name = trim((string) $this->option('name'));
        $cron = trim((string) $this->option('cron'));
        $runnerType = $this->normalizeRunnerType(trim((string) $this->option('runner-type')));
        $workingDirectory = trim((string) $this->option('working-directory'));
        $taskMarkdownPath = trim((string) $this->option('task-markdown-path'));
        $taskFile = trim((string) $this->option('task-file'));
        $timezone = trim((string) $this->option('timezone'));
        $maxRuntime = (int) $this->option('max-runtime');
        $cooldown = (int) $this->option('cooldown');

        $taskMarkdownContent = '';
        if ($taskFile !== '') {
            $path = realpath($taskFile);
            if ($path === false || ! is_file($path)) {
                $this->error("Task file not found or not a file: {$taskFile}");

                return self::FAILURE;
            }
            $content = @file_get_contents($path);
            $taskMarkdownContent = is_string($content) ? trim($content) : '';
            if ($taskMarkdownContent === '') {
                $this->error('Task file is empty.');

                return self::FAILURE;
            }
        }

        $rules = [
            'name' => ['required', 'string', 'min:3', 'max:120', 'regex:/^[^\p{C}]+$/u'],
            'cron_expression' => ['required', 'string', 'max:100', new NumericCronExpression],
            'runner_type' => ['required', Rule::in(['claude', 'codex', 'custom'])],
            'working_directory' => ['required', 'string', 'max:1024'],
            'timezone' => ['required', 'string', 'max:64', 'timezone'],
            'max_runtime_seconds' => ['required', 'integer', 'between:10,86400'],
            'cooldown_seconds' => ['required', 'integer', 'between:0,86400'],
        ];

        $validator = Validator::make([
            'name' => $name,
            'cron_expression' => $cron,
            'runner_type' => $runnerType,
            'working_directory' => $workingDirectory,
            'timezone' => $timezone,
            'max_runtime_seconds' => $maxRuntime,
            'cooldown_seconds' => $cooldown,
        ], $rules);

        if ($taskMarkdownPath === '' && $taskMarkdownContent === '') {
            $validator->errors()->add('task', 'Either --task-markdown-path or --task-file is required.');
        }
        if ($taskMarkdownPath !== '' && $taskMarkdownContent !== '') {
            $validator->errors()->add('task', 'Use only one of --task-markdown-path or --task-file.');
        }

        $workingDirError = $pathPolicy->validateWorkingDirectory($workingDirectory);
        if ($workingDirError !== null) {
            $validator->errors()->add('working_directory', $workingDirError);
        }

        if ($taskMarkdownPath !== '') {
            $taskError = $pathPolicy->validateTaskMarkdownPath($taskMarkdownPath);
            if ($taskError !== null) {
                $validator->errors()->add('task_markdown_path', $taskError);
            }
        }

        $commandResult = $commandPolicy->validateForSave($runnerType, null);
        if ($commandResult['errors'] !== []) {
            foreach ($commandResult['errors'] as $field => $message) {
                $validator->errors()->add($field, $message);
            }
        }

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        $user = $this->resolveUser(trim((string) $this->option('user')));
        if ($user === null) {
            $this->error('Could not resolve user. Use --user=<id|email> or ensure at least one user exists.');

            return self::FAILURE;
        }

        $existing = $user->agentJobs()
            ->whereNull('deleted_at')
            ->where('name', $name)
            ->exists();
        if ($existing) {
            $this->error("A job with name \"{$name}\" already exists for this user.");

            return self::FAILURE;
        }

        $workflowKey = WorkflowKey::resolve(null, $name);
        $existingKey = $user->agentJobs()
            ->whereNull('deleted_at')
            ->where('workflow_key', $workflowKey)
            ->exists();
        if ($existingKey) {
            $this->error("A job with workflow_key \"{$workflowKey}\" already exists for this user.");

            return self::FAILURE;
        }

        $resolvedTaskPath = $taskMarkdownPath;
        if ($taskMarkdownContent !== '') {
            $resolvedTaskPath = $taskMarkdownStorage->persistInlineContent($taskMarkdownContent, (int) $user->id);
        }

        $normalizedTemplate = $commandResult['normalized_template'] ?? config('agent.default_templates.'.$runnerType);
        $resolvedExecutablePath = $commandResult['resolved_executable_path'];

        /** @var \App\Models\AgentJob $job */
        $job = $user->agentJobs()->create([
            'name' => $name,
            'workflow_key' => $workflowKey,
            'description' => trim((string) $this->option('description')) ?: null,
            'cron_expression' => $cron,
            'timezone' => $timezone,
            'is_enabled' => ! $this->option('disabled'),
            'max_runtime_seconds' => $maxRuntime,
            'cooldown_seconds' => $cooldown,
            'runner_type' => $runnerType,
            'command_template' => $normalizedTemplate,
            'task_markdown_path' => $resolvedTaskPath,
            'working_directory' => $workingDirectory,
            'env_json' => null,
            'active_hours_config' => null,
            'last_validated_executable_path' => $resolvedExecutablePath,
            'star_preamble_enabled' => null,
            'targeted_retry_enabled' => null,
            'max_retries' => null,
        ]);
        $this->info("Agent job created: id={$job->id} name=\"{$job->name}\" runner_type={$job->runner_type}");

        return self::SUCCESS;
    }

    private function normalizeRunnerType(string $value): string
    {
        $v = strtolower($value);
        if (in_array($v, ['claude', 'codex', 'custom'], true)) {
            return $v;
        }

        return 'codex';
    }

    private function resolveUser(string $option): ?User
    {
        if ($option !== '') {
            if (is_numeric($option)) {
                return User::query()->find((int) $option);
            }

            return User::query()->where('email', $option)->first();
        }

        return User::query()->orderBy('id')->first();
    }
}
