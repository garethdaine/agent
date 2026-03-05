<?php

namespace App\Enums\Messenger;

enum ChatActionType: string
{
    case JOBS_LIST = 'jobs.list';
    case JOBS_CREATE = 'jobs.create';
    case JOBS_UPDATE = 'jobs.update';
    case JOBS_DELETE = 'jobs.delete';
    case JOBS_SHOW = 'jobs.show';
    case RUNS_LIST_ACTIVE = 'runs.list_active';
    case RUNS_LIST_HISTORY = 'runs.list_history';
    case RUNS_SHOW = 'runs.show';
    case RUNS_STOP = 'runs.stop';
    case RUNS_RUN_NOW = 'runs.run_now';
    case RUNS_RETRY = 'runs.retry';
    case RUNS_STEER = 'runs.steer';
    case GENERAL_TASK = 'general.task';

    /**
     * Get all action type values.
     *
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Check if this is a query (read-only) action.
     */
    public function isQuery(): bool
    {
        return in_array($this, [
            self::JOBS_LIST,
            self::JOBS_SHOW,
            self::RUNS_LIST_ACTIVE,
            self::RUNS_LIST_HISTORY,
            self::RUNS_SHOW,
        ], true);
    }

    /**
     * Check if this is a mutation action.
     */
    public function isMutation(): bool
    {
        return ! $this->isQuery();
    }

    /**
     * Check if this action requires confirmation when confirmation is enabled.
     * Destructive actions that modify or delete existing resources require confirmation.
     */
    public function isDestructive(): bool
    {
        return in_array($this, [
            self::RUNS_STOP,
            self::JOBS_UPDATE,
            self::JOBS_DELETE,
        ], true);
    }

    /**
     * Check if this action requires confirmation based on provider configuration.
     *
     * @param  array<string, mixed>  $config  Connector account config
     */
    public function requiresConfirmation(array $config): bool
    {
        if (! ($config['confirmation_required'] ?? false)) {
            return false;
        }

        return $this->isDestructive();
    }

    /**
     * Get the human-readable label for this action type.
     */
    public function label(): string
    {
        return match ($this) {
            self::JOBS_LIST => 'List Jobs',
            self::JOBS_CREATE => 'Create Job',
            self::JOBS_UPDATE => 'Update Job',
            self::JOBS_DELETE => 'Delete Job',
            self::JOBS_SHOW => 'Job Details',
            self::RUNS_LIST_ACTIVE => 'List Active Runs',
            self::RUNS_LIST_HISTORY => 'Run History',
            self::RUNS_SHOW => 'Run Details',
            self::RUNS_STOP => 'Stop Run',
            self::RUNS_RUN_NOW => 'Run Job Now',
            self::RUNS_RETRY => 'Retry Run',
            self::RUNS_STEER => 'Steer Run',
            self::GENERAL_TASK => 'Task',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::JOBS_LIST => 'List all configured jobs',
            self::JOBS_CREATE => 'Create a new scheduled job',
            self::JOBS_UPDATE => 'Update an existing job configuration',
            self::JOBS_DELETE => 'Permanently delete a job',
            self::JOBS_SHOW => 'Show detailed information about a specific job',
            self::RUNS_LIST_ACTIVE => 'List currently active runs',
            self::RUNS_LIST_HISTORY => 'List run history for a job or all jobs',
            self::RUNS_SHOW => 'Show detailed information about a specific run',
            self::RUNS_STOP => 'Stop a running process',
            self::RUNS_RUN_NOW => 'Trigger immediate execution of a job',
            self::RUNS_RETRY => 'Retry a failed or stopped run',
            self::RUNS_STEER => 'Provide guidance to a running process',
            self::GENERAL_TASK => 'Handle a general task or question',
        };
    }

    /**
     * Get the parameter schema for this action type.
     *
     * @return array<string, mixed>
     */
    public function parameterSchema(): array
    {
        return match ($this) {
            self::JOBS_LIST => [
                'type' => 'object',
                'properties' => [
                    'filter' => ['type' => 'string', 'description' => 'Optional filter pattern'],
                    'limit' => ['type' => 'integer', 'description' => 'Maximum number of results'],
                ],
                'required' => [],
            ],
            self::JOBS_CREATE => [
                'type' => 'object',
                'properties' => [
                    'name' => ['type' => 'string', 'description' => 'Job name'],
                    'command' => ['type' => 'string', 'description' => 'Command to execute'],
                    'schedule' => ['type' => 'string', 'description' => 'Cron expression'],
                    'description' => ['type' => 'string', 'description' => 'Job description'],
                    'runner_type' => ['type' => 'string', 'description' => 'Runner type: claude, codex, or custom'],
                    'working_directory' => ['type' => 'string', 'description' => 'Working directory for the job'],
                    'task_brief' => ['type' => 'string', 'description' => 'Task description/brief for the agent'],
                ],
                'required' => ['name'],
            ],
            self::JOBS_UPDATE => [
                'type' => 'object',
                'properties' => [
                    'job_id' => ['type' => 'string', 'description' => 'Job identifier'],
                    'name' => ['type' => 'string', 'description' => 'New job name'],
                    'command' => ['type' => 'string', 'description' => 'New command'],
                    'schedule' => ['type' => 'string', 'description' => 'New cron expression'],
                    'enabled' => ['type' => 'boolean', 'description' => 'Enable or disable job'],
                ],
                'required' => ['job_id'],
            ],
            self::JOBS_DELETE => [
                'type' => 'object',
                'properties' => [
                    'job_id' => ['type' => 'string', 'description' => 'Job identifier'],
                ],
                'required' => ['job_id'],
            ],
            self::JOBS_SHOW => [
                'type' => 'object',
                'properties' => [
                    'job_id' => ['type' => 'string', 'description' => 'Job identifier'],
                ],
                'required' => ['job_id'],
            ],
            self::RUNS_LIST_ACTIVE => [
                'type' => 'object',
                'properties' => [
                    'job_id' => ['type' => 'string', 'description' => 'Filter by job ID'],
                    'limit' => ['type' => 'integer', 'description' => 'Maximum number of results'],
                ],
                'required' => [],
            ],
            self::RUNS_LIST_HISTORY => [
                'type' => 'object',
                'properties' => [
                    'job_id' => ['type' => 'string', 'description' => 'Filter by job ID'],
                    'limit' => ['type' => 'integer', 'description' => 'Maximum number of results'],
                    'status' => ['type' => 'string', 'description' => 'Filter by status: succeeded, failed, killed, timed_out'],
                ],
                'required' => [],
            ],
            self::RUNS_SHOW => [
                'type' => 'object',
                'properties' => [
                    'run_id' => ['type' => 'string', 'description' => 'Run identifier'],
                ],
                'required' => ['run_id'],
            ],
            self::RUNS_STOP => [
                'type' => 'object',
                'properties' => [
                    'run_id' => ['type' => 'string', 'description' => 'Run identifier'],
                    'reason' => ['type' => 'string', 'description' => 'Reason for stopping'],
                ],
                'required' => ['run_id'],
            ],
            self::RUNS_RUN_NOW => [
                'type' => 'object',
                'properties' => [
                    'job_id' => ['type' => 'string', 'description' => 'Job identifier'],
                ],
                'required' => ['job_id'],
            ],
            self::RUNS_RETRY => [
                'type' => 'object',
                'properties' => [
                    'run_id' => ['type' => 'string', 'description' => 'Run identifier'],
                    'with_modifications' => ['type' => 'boolean', 'description' => 'Apply modifications before retry'],
                ],
                'required' => ['run_id'],
            ],
            self::RUNS_STEER => [
                'type' => 'object',
                'properties' => [
                    'run_id' => ['type' => 'string', 'description' => 'Run identifier'],
                    'guidance' => ['type' => 'string', 'description' => 'Guidance message for the running process'],
                ],
                'required' => ['run_id', 'guidance'],
            ],
            self::GENERAL_TASK => [
                'type' => 'object',
                'properties' => [
                    'task_description' => ['type' => 'string', 'description' => 'The task to perform'],
                    'context' => ['type' => 'string', 'description' => 'Additional context'],
                ],
                'required' => ['task_description'],
            ],
        };
    }
}
