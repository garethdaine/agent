<?php

namespace App\Messenger\ChatAction\Handlers;

use App\Messenger\ChatAction\ChatActionContext;
use App\Messenger\ChatAction\ChatActionResult;
use App\Models\AgentJobRun;

class RunsListActiveHandler implements ChatActionHandlerInterface
{
    public function handle(ChatActionContext $context): ChatActionResult
    {
        $user = $context->getAuthenticatedUser();

        $runs = AgentJobRun::whereHas('job', fn ($q) => $q->where('user_id', $user->id))
            ->active()
            ->with('job:id,name')
            ->orderByDesc('started_at')
            ->limit(10)
            ->get(['id', 'agent_job_id', 'status', 'started_at', 'pid']);

        if ($runs->isEmpty()) {
            return ChatActionResult::success('No active runs.', ['runs' => []]);
        }

        return ChatActionResult::success(
            $this->formatRunsList($runs),
            ['runs' => $runs->toArray()]
        );
    }

    private function formatRunsList($runs): string
    {
        $lines = ["Active runs ({$runs->count()}):"];
        foreach ($runs as $run) {
            $jobName = $run->job?->name ?? 'Unknown';
            $lines[] = "• [{$run->id}] {$jobName} ({$run->status})";
        }

        return implode("\n", $lines);
    }
}
