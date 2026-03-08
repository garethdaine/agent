<?php

declare(strict_types=1);

namespace App\Support\Connectors\Pipeline;

use App\Models\AgentConnectorApproval;
use App\Services\Connectors\DualChannelApprovalService;
use App\Support\Connectors\ActionRequest;
use App\Support\Connectors\Exceptions\ApprovalRequiredException;
use Closure;

class ApprovalGate
{
    public function __construct(
        private readonly DualChannelApprovalService $approvalService,
    ) {}

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

        $approval = $this->approvalService->requestApproval(
            connection: $request->connection,
            actionName: $request->action,
            type: AgentConnectorApproval::TYPE_APPROVAL,
            context: $request->parameters,
            runAttemptId: $request->runAttemptId,
        );

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
