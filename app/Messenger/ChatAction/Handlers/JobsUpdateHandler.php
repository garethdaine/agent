<?php

namespace App\Messenger\ChatAction\Handlers;

use App\Messenger\ChatAction\ChatActionContext;
use App\Messenger\ChatAction\ChatActionResult;
use Illuminate\Support\Facades\Validator;

class JobsUpdateHandler implements ChatActionHandlerInterface
{
    public function handle(ChatActionContext $context): ChatActionResult
    {
        $job = $context->getTargetJob();

        if ($job === null) {
            return ChatActionResult::failure('No target job specified');
        }

        $params = $context->getParameters();

        // Remove job_id from params if present
        unset($params['job_id']);

        if (empty($params)) {
            return ChatActionResult::failure('No fields provided to update');
        }

        $validation = Validator::make($params, [
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string|max:1000',
            'schedule' => 'nullable|string|max:255',
            'is_enabled' => 'sometimes|boolean',
        ]);

        if ($validation->fails()) {
            return ChatActionResult::failure(
                'Validation failed: '.$validation->errors()->first()
            );
        }

        $validated = $validation->validated();

        // Map schedule to cron_expression if present
        if (isset($validated['schedule'])) {
            $validated['cron_expression'] = $validated['schedule'];
            unset($validated['schedule']);
        }

        $job->update($validated);

        return ChatActionResult::success(
            "Job '{$job->name}' updated successfully",
            ['job' => $job->fresh()->toArray()]
        );
    }
}
