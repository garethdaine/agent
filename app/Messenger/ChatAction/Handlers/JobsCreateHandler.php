<?php

namespace App\Messenger\ChatAction\Handlers;

use App\Messenger\ChatAction\ChatActionContext;
use App\Messenger\ChatAction\ChatActionResult;
use App\Models\AgentJob;
use Illuminate\Support\Facades\Validator;

class JobsCreateHandler implements ChatActionHandlerInterface
{
    public function handle(ChatActionContext $context): ChatActionResult
    {
        $user = $context->getAuthenticatedUser();
        $params = $context->getParameters();

        $validation = Validator::make($params, [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'schedule' => 'nullable|string|max:255',
        ]);

        if ($validation->fails()) {
            return ChatActionResult::failure(
                'Validation failed: '.$validation->errors()->first()
            );
        }

        $runnerType = $params['runner_type'] ?? 'claude';
        $workingDirectory = $params['working_directory'] ?? '';
        $description = $params['description'] ?? null;
        $taskBrief = $params['task_brief'] ?? $params['command'] ?? null;

        $job = AgentJob::create([
            'user_id' => $user->id,
            'name' => $params['name'],
            'description' => $description,
            'cron_expression' => $params['schedule'] ?? '0 0 * * *',
            'timezone' => 'UTC',
            'is_enabled' => false,
            'max_runtime_seconds' => 3600,
            'cooldown_seconds' => 0,
            'runner_type' => $runnerType,
            'command_template' => '',
            'task_markdown_path' => '',
            'working_directory' => $workingDirectory,
        ]);

        return ChatActionResult::success(
            "Job **{$job->name}** created successfully (ID: `{$job->id}`)",
            [
                'job_id' => $job->id,
                'name' => $job->name,
                'runner_type' => $job->runner_type,
                'schedule' => $job->cron_expression,
            ]
        );
    }
}
