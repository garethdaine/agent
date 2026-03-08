<?php

declare(strict_types=1);

namespace App\Support\Agent;

use App\Models\AgentJob;
use App\Models\AgentJobRun;

class CommandTemplateRenderer
{
    /**
     * @return array<int, string>
     */
    public function renderTokens(AgentJob $job, AgentJobRun $run): array
    {
        $template = trim($job->command_template);

        $tokens = preg_split('/\s+/', $template) ?: [];

        $map = [
            '{{run_id}}' => (string) $run->id,
            '{{job_id}}' => (string) $job->id,
            '{{task_markdown_path}}' => $job->task_markdown_path,
            '{{working_directory}}' => $job->working_directory,
            '{{job_name}}' => $job->name,
        ];

        return array_map(static fn (string $token): string => $map[$token] ?? $token, $tokens);
    }
}
