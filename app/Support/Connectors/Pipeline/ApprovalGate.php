<?php

declare(strict_types=1);

namespace App\Support\Connectors\Pipeline;

use App\Models\AgentConnectorApproval;
use App\Support\Connectors\ActionRequest;
use App\Support\Connectors\Exceptions\ApprovalRequiredException;
use Closure;

class ApprovalGate
{
    public function handle(ActionRequest $request, Closure $next): mixed
    {
        if (! $this->requiresApproval($request)) {
            return $next($request);
        }

        $existing = AgentConnectorApproval::where('connection_id', $request->connection->id)
            ->where('connector_id', $request->connector->id)
            ->where('action_name', $request->action)
            ->where('status', AgentConnectorApproval::STATUS_PENDING)
            ->where('expires_at', '>', now())
            ->first();

        if ($existing) {
            throw new ApprovalRequiredException($existing);
        }

        $approved = AgentConnectorApproval::where('connection_id', $request->connection->id)
            ->where('connector_id', $request->connector->id)
            ->where('action_name', $request->action)
            ->where('status', AgentConnectorApproval::STATUS_APPROVED)
            ->where('resolved_at', '>', now()->subMinutes(config('connectors.approval_timeout_minutes', 15)))
            ->first();

        if ($approved) {
            return $next($request);
        }

        $approval = AgentConnectorApproval::create([
            'connection_id' => $request->connection->id,
            'connector_id' => $request->connector->id,
            'action_name' => $request->action,
            'type' => AgentConnectorApproval::TYPE_APPROVAL,
            'status' => AgentConnectorApproval::STATUS_PENDING,
            'requested_by_run_id' => $request->runAttemptId,
            'requested_at' => now(),
            'expires_at' => now()->addMinutes(config('connectors.approval_timeout_minutes', 15)),
            'request_context' => $request->parameters,
        ]);

        throw new ApprovalRequiredException($approval);
    }

    private function requiresApproval(ActionRequest $request): bool
    {
        $actions = $request->connector->actions ?? [];

        foreach ($actions as $action) {
            if (($action['name'] ?? '') === $request->action) {
                return ! empty($action['requires_approval']);
            }
        }

        return false;
    }
}
