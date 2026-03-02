<?php

namespace App\Messenger\ChatAction\Handlers;

use App\Messenger\ChatAction\ChatActionContext;
use App\Messenger\ChatAction\ChatActionResult;
use App\Models\AgentJob;

class JobsListHandler implements ChatActionHandlerInterface
{
    public function handle(ChatActionContext $context): ChatActionResult
    {
        $user = $context->getAuthenticatedUser();
        $parameters = $context->getParameters();

        $query = AgentJob::where('user_id', $user->id);
        $filter = strtolower(trim((string) ($parameters['filter'] ?? '')));

        if (in_array($filter, ['active', 'enabled'], true)) {
            $query->where('is_enabled', true);
        } elseif (in_array($filter, ['disabled', 'paused'], true)) {
            $query->where('is_enabled', false);
        }

        $jobs = $query
            ->orderBy('updated_at', 'desc')
            ->limit(10)
            ->get(['id', 'name', 'is_enabled', 'cron_expression', 'updated_at']);

        if ($jobs->isEmpty()) {
            return ChatActionResult::success(
                'You have no jobs configured.',
                ['jobs' => []]
            );
        }

        return ChatActionResult::success(
            $this->formatJobsList($jobs),
            ['jobs' => $jobs->toArray()]
        );
    }

    private function formatJobsList($jobs): string
    {
        $lines = ["Your jobs ({$jobs->count()}):"];
        foreach ($jobs as $job) {
            $status = $job->is_enabled ? 'active' : 'paused';
            $lines[] = "- [{$job->id}] {$job->name} ({$status})";
        }

        return implode("\n", $lines);
    }
}
