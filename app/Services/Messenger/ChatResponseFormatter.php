<?php

declare(strict_types=1);

namespace App\Services\Messenger;

use App\DTOs\Messenger\ActionResult;
use App\Enums\Messenger\ChatActionType;
use App\Models\ChatAction;
use App\Models\ConnectorAccount;

class ChatResponseFormatter
{
    /**
     * Format an action result for messenger display.
     */
    public function format(
        ActionResult $result,
        ChatAction $action,
        ConnectorAccount $account
    ): string {
        $verbosity = $account->config['default_verbosity'] ?? 'summary';
        $actionType = ChatActionType::tryFrom($action->action_type);

        if (! $result->success) {
            return $this->formatError(new \RuntimeException($result->error ?? 'Unknown error'), $action, $account);
        }

        if ($actionType === ChatActionType::GENERAL_TASK) {
            return $result->message ?? '';
        }

        return match ($verbosity) {
            'full' => $this->formatFull($result, $actionType),
            'errors_only' => $this->formatErrorsOnly($result, $actionType),
            default => $this->formatSummary($result, $actionType),
        };
    }

    /**
     * Format error response.
     */
    public function formatError(
        \Throwable $error,
        ChatAction $action,
        ConnectorAccount $account
    ): string {
        $actionType = ChatActionType::tryFrom($action->action_type);
        $actionLabel = $actionType?->label() ?? $action->action_type;

        $response = "**Error: {$actionLabel} Failed**\n\n";
        $response .= $error->getMessage();

        // Add suggestions based on error type
        $suggestions = $this->getSuggestionsForError($error, $actionType);
        if ($suggestions !== '') {
            $response .= "\n\n**Suggestions:**\n{$suggestions}";
        }

        return $response;
    }

    /**
     * Format with full verbosity.
     */
    private function formatFull(ActionResult $result, ?ChatActionType $actionType): string
    {
        $response = $this->formatHeader($result, $actionType);

        if ($result->message === null || $result->message === '') {
            $response .= $this->formatDataFull($result->data, $actionType);
        }

        if ($result->hasWarnings()) {
            $response .= $this->formatWarnings($result->warnings);
        }

        $response .= $this->formatUsage($result->usage);

        return $response;
    }

    /**
     * Format with summary verbosity.
     */
    private function formatSummary(ActionResult $result, ?ChatActionType $actionType): string
    {
        $response = $this->formatHeader($result, $actionType);

        if ($result->message === null || $result->message === '') {
            $response .= $this->formatDataSummary($result->data, $actionType);
        }

        if ($result->hasWarnings()) {
            $response .= $this->formatWarnings($result->warnings);
        }

        $response .= $this->formatUsage($result->usage);

        return $response;
    }

    /**
     * Format with errors_only verbosity.
     */
    private function formatErrorsOnly(ActionResult $result, ?ChatActionType $actionType): string
    {
        if (! $result->success) {
            return $this->formatHeader($result, $actionType).$result->error;
        }

        // For successful results, just show the message
        return $result->message ?? 'Action completed successfully.';
    }

    /**
     * Format response header.
     */
    private function formatHeader(ActionResult $result, ?ChatActionType $actionType): string
    {
        $icon = $result->success ? '✓' : '✗';
        $label = $actionType?->label() ?? 'Action';

        if ($result->message !== null) {
            return "**{$icon} {$label}**\n\n{$result->message}\n\n";
        }

        $status = $result->success ? 'completed successfully' : 'failed';

        return "**{$icon} {$label}** {$status}\n\n";
    }

    /**
     * Format data with full verbosity.
     *
     * @param  array<string, mixed>  $data
     */
    private function formatDataFull(array $data, ?ChatActionType $actionType): string
    {
        if (empty($data)) {
            return '';
        }

        return match ($actionType) {
            ChatActionType::JOBS_LIST => $this->formatJobsListFull($data),
            ChatActionType::RUNS_LIST_ACTIVE => $this->formatRunsListFull($data),
            ChatActionType::JOBS_CREATE => $this->formatJobCreateFull($data),
            ChatActionType::RUNS_STOP => $this->formatRunStopFull($data),
            ChatActionType::RUNS_RETRY => $this->formatRunRetryFull($data),
            ChatActionType::RUNS_RUN_NOW => $this->formatRunNowFull($data),
            ChatActionType::GENERAL_TASK, ChatActionType::RUNS_LIST_HISTORY,
            ChatActionType::RUNS_SHOW, ChatActionType::JOBS_SHOW => '',
            default => $this->formatGenericData($data),
        };
    }

    /**
     * Format data with summary verbosity.
     *
     * @param  array<string, mixed>  $data
     */
    private function formatDataSummary(array $data, ?ChatActionType $actionType): string
    {
        if (empty($data)) {
            return '';
        }

        return match ($actionType) {
            ChatActionType::JOBS_LIST => $this->formatJobsListSummary($data),
            ChatActionType::RUNS_LIST_ACTIVE => $this->formatRunsListSummary($data),
            ChatActionType::JOBS_CREATE => $this->formatJobCreateSummary($data),
            ChatActionType::RUNS_STOP => $this->formatRunStopSummary($data),
            ChatActionType::RUNS_RETRY => $this->formatRunRetrySummary($data),
            ChatActionType::RUNS_RUN_NOW => $this->formatRunNowSummary($data),
            ChatActionType::GENERAL_TASK, ChatActionType::RUNS_LIST_HISTORY,
            ChatActionType::RUNS_SHOW, ChatActionType::JOBS_SHOW => '',
            default => $this->formatGenericData($data),
        };
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function formatJobsListFull(array $data): string
    {
        $jobs = $data['jobs'] ?? [];
        $total = $data['total'] ?? count($jobs);

        if (empty($jobs)) {
            return "No jobs found.\n";
        }

        $output = '';
        foreach ($jobs as $job) {
            $enabled = ($job['enabled'] ?? false) ? '✓' : '✗';
            $output .= "**{$job['name']}** (ID: `{$job['id']}`)\n";
            $output .= "  Schedule: `{$job['schedule']}`\n";
            $output .= "  Enabled: {$enabled}\n";
            if (isset($job['last_run'])) {
                $output .= "  Last run: {$job['last_run']}\n";
            }
            $output .= "\n";
        }

        return $output;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function formatJobsListSummary(array $data): string
    {
        $jobs = $data['jobs'] ?? [];
        $total = $data['total'] ?? count($jobs);

        if (empty($jobs)) {
            return "No jobs found.\n";
        }

        $output = '';
        foreach ($jobs as $job) {
            $enabled = ($job['enabled'] ?? false) ? '' : ' (disabled)';
            $output .= "• **{$job['name']}**{$enabled} - `{$job['id']}`\n";
        }

        return $output;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function formatRunsListFull(array $data): string
    {
        $runs = $data['runs'] ?? [];
        $total = $data['total'] ?? count($runs);

        if (empty($runs)) {
            return "No active runs.\n";
        }

        $output = '';
        foreach ($runs as $run) {
            $progress = isset($run['progress']) ? " ({$run['progress']}%)" : '';
            $jobName = $run['job_name'] ?? ($run['job']['name'] ?? 'Unknown');
            $runId = $run['id'] ?? 'N/A';
            $status = $run['status'] ?? 'unknown';
            $startedAt = $run['started_at'] ?? 'N/A';

            $output .= "**{$jobName}** (Run ID: `{$runId}`)\n";
            $output .= "  Status: {$status}{$progress}\n";
            $output .= "  Started: {$startedAt}\n";
            $output .= "\n";
        }

        return $output;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function formatRunsListSummary(array $data): string
    {
        $runs = $data['runs'] ?? [];

        if (empty($runs)) {
            return "No active runs.\n";
        }

        $output = '';
        foreach ($runs as $run) {
            $progress = isset($run['progress']) ? " - {$run['progress']}%" : '';
            $jobName = $run['job_name'] ?? ($run['job']['name'] ?? 'Unknown');
            $runId = $run['id'] ?? 'N/A';
            $output .= "• **{$jobName}**{$progress} - `{$runId}`\n";
        }

        return $output;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function formatJobCreateFull(array $data): string
    {
        return sprintf(
            "**Job Created**\n\nID: `%s`\nName: %s\nSchedule: `%s`\nCommand: `%s`\n",
            $data['job_id'] ?? 'N/A',
            $data['name'] ?? 'N/A',
            $data['schedule'] ?? 'N/A',
            $data['command'] ?? 'N/A'
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function formatJobCreateSummary(array $data): string
    {
        return sprintf(
            "Job **%s** created (ID: `%s`)\n",
            $data['name'] ?? 'N/A',
            $data['job_id'] ?? 'N/A'
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function formatRunStopFull(array $data): string
    {
        return sprintf(
            "Run `%s` has been stopped.\nReason: %s\nStopped at: %s\n",
            $data['run_id'] ?? 'N/A',
            $data['reason'] ?? 'User requested',
            $data['stopped_at'] ?? 'N/A'
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function formatRunStopSummary(array $data): string
    {
        return sprintf("Run `%s` stopped.\n", $data['run_id'] ?? 'N/A');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function formatRunRetryFull(array $data): string
    {
        return sprintf(
            "Run `%s` queued for retry.\nNew run ID: `%s`\nQueued at: %s\n",
            $data['original_run_id'] ?? 'N/A',
            $data['new_run_id'] ?? 'N/A',
            $data['queued_at'] ?? 'N/A'
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function formatRunRetrySummary(array $data): string
    {
        return sprintf(
            "Retrying run `%s` → new run `%s`\n",
            $data['original_run_id'] ?? 'N/A',
            $data['new_run_id'] ?? 'N/A'
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function formatRunNowFull(array $data): string
    {
        return sprintf(
            "Job `%s` queued for immediate execution.\nRun ID: `%s`\nQueued at: %s\n",
            $data['job_id'] ?? 'N/A',
            $data['run_id'] ?? 'N/A',
            $data['queued_at'] ?? 'N/A'
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function formatRunNowSummary(array $data): string
    {
        return sprintf(
            "Job `%s` queued (run `%s`)\n",
            $data['job_id'] ?? 'N/A',
            $data['run_id'] ?? 'N/A'
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function formatGenericData(array $data): string
    {
        $output = '';
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $value = json_encode($value, JSON_PRETTY_PRINT);
            }
            $output .= "**{$key}:** {$value}\n";
        }

        return $output;
    }

    /**
     * @param  array<string>  $warnings
     */
    private function formatWarnings(array $warnings): string
    {
        if (empty($warnings)) {
            return '';
        }

        $output = "\n**Warnings:**\n";
        foreach ($warnings as $warning) {
            $output .= "⚠ {$warning}\n";
        }

        return $output;
    }

    /**
     * @param  array{input_tokens?: int, output_tokens?: int, cost_usd?: float}|null  $usage
     */
    private function formatUsage(?array $usage): string
    {
        if ($usage === null) {
            return '';
        }

        $input = (int) ($usage['input_tokens'] ?? 0);
        $output = (int) ($usage['output_tokens'] ?? 0);
        $cost = $usage['cost_usd'] ?? null;

        if ($input === 0 && $output === 0) {
            return '';
        }

        $parts = [];
        $parts[] = number_format($input).' in';
        $parts[] = number_format($output).' out';

        if (is_float($cost) && $cost > 0) {
            $parts[] = '$'.number_format($cost, 4);
        }

        return "\n`tokens: ".implode(' / ', $parts)."`\n";
    }

    /**
     * Get suggestions based on error type.
     */
    private function getSuggestionsForError(\Throwable $error, ?ChatActionType $actionType): string
    {
        $message = strtolower($error->getMessage());

        if (str_contains($message, 'not found')) {
            return "• Verify the ID is correct\n• Use `list jobs` or `list runs` to see available items";
        }

        if (str_contains($message, 'permission') || str_contains($message, 'unauthorized')) {
            return "• Check that you have permission for this action\n• Contact an administrator if you need access";
        }

        if (str_contains($message, 'invalid') || str_contains($message, 'validation')) {
            return "• Check the command syntax\n• Ensure all required parameters are provided";
        }

        return '';
    }
}
