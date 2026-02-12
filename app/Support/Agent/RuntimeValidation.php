<?php

namespace App\Support\Agent;

use App\Models\AgentJob;

class RuntimeValidation
{
    /**
     * @return array{ok:bool,error_code:?string,error_summary:?string,resolved_executable_path:?string}
     */
    public function validate(AgentJob $job): array
    {
        $pathPolicy = app(PathPolicy::class);
        $commandPolicy = app(CommandPolicy::class);

        $taskError = $pathPolicy->validateTaskMarkdownPath($job->task_markdown_path);
        if ($taskError !== null) {
            return [
                'ok' => false,
                'error_code' => 'RUN_PATH_NOT_FOUND',
                'error_summary' => $taskError,
                'resolved_executable_path' => null,
            ];
        }

        $workingError = $pathPolicy->validateWorkingDirectory($job->working_directory);
        if ($workingError !== null) {
            return [
                'ok' => false,
                'error_code' => 'RUN_PATH_NOT_FOUND',
                'error_summary' => $workingError,
                'resolved_executable_path' => null,
            ];
        }

        $commandCheck = $commandPolicy->validateForSave($job->runner_type, $job->command_template);
        if (! empty($commandCheck['errors'])) {
            return [
                'ok' => false,
                'error_code' => 'BINARY_NOT_ALLOWED',
                'error_summary' => reset($commandCheck['errors']) ?: 'Executable validation failed.',
                'resolved_executable_path' => null,
            ];
        }

        return [
            'ok' => true,
            'error_code' => null,
            'error_summary' => null,
            'resolved_executable_path' => $commandCheck['resolved_executable_path'],
        ];
    }
}
