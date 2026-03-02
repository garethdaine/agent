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

        $job = AgentJob::create([
            'user_id' => $user->id,
            'name' => $params['name'],
            'description' => $params['description'] ?? null,
            'cron_expression' => $params['schedule'] ?? '0 0 * * *',
            'timezone' => 'UTC',
            'is_enabled' => false,
            'max_runtime_seconds' => 3600,
            'cooldown_seconds' => 0,
            'runner_type' => 'claude',
            'command_template' => '',
            'task_markdown_path' => '',
            'working_directory' => '',
        ]);

        return ChatActionResult::success(
            "Job '{$job->name}' created successfully (ID: {$job->id})",
            ['job' => $job->toArray()]
        );
    }
}
