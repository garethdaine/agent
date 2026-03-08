<?php

declare(strict_types=1);

namespace App\Services\Connectors;

use App\DTOs\Messenger\SystemNotificationPayload;
use App\Models\AgentConnectorApproval;
use App\Models\AgentConnectorConnection;
use App\Models\User;
use App\Services\Messenger\SystemNotificationDispatcher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DualChannelApprovalService
{
    public function __construct(
        private readonly SystemNotificationDispatcher $notificationDispatcher,
    ) {}

    public function requestApproval(
        AgentConnectorConnection $connection,
        string $actionName,
        string $type,
        array $context,
        ?string $runAttemptId = null,
    ): AgentConnectorApproval {
        $timeoutMinutes = config('connectors.approval_timeout_minutes', 15);

        $approval = AgentConnectorApproval::create([
            'connection_id' => $connection->id,
            'connector_id' => $connection->connector_id,
            'action_name' => $actionName,
            'type' => $type,
            'status' => AgentConnectorApproval::STATUS_PENDING,
            'requested_by_run_id' => $runAttemptId,
            'requested_at' => now(),
            'expires_at' => now()->addMinutes($timeoutMinutes),
            'request_context' => $context,
        ]);

        $this->sendMessengerNotification($connection, $approval);

        return $approval;
    }

    public function resolveApproval(
        AgentConnectorApproval $approval,
        User $user,
        string $channel,
        string $status = AgentConnectorApproval::STATUS_APPROVED,
        ?array $payload = null,
    ): bool {
        return DB::transaction(function () use ($approval, $user, $channel, $status, $payload) {
            $locked = AgentConnectorApproval::where('id', $approval->id)
                ->lockForUpdate()
                ->first();

            if (! $locked) {
                return false;
            }

            if ($locked->status !== AgentConnectorApproval::STATUS_PENDING) {
                return false;
            }

            if ($locked->expires_at->isPast()) {
                return false;
            }

            $locked->update([
                'status' => $status,
                'resolved_by' => $user->id,
                'resolved_at' => now(),
                'resolved_via' => $channel,
                'resolution_payload' => $payload ?? [],
            ]);

            return true;
        });
    }

    public function isApproved(string $approvalId): bool
    {
        return AgentConnectorApproval::where('id', $approvalId)
            ->where('status', AgentConnectorApproval::STATUS_APPROVED)
            ->exists();
    }

    public function isPending(string $approvalId): bool
    {
        return AgentConnectorApproval::where('id', $approvalId)
            ->where('status', AgentConnectorApproval::STATUS_PENDING)
            ->where('expires_at', '>', now())
            ->exists();
    }

    private function sendMessengerNotification(AgentConnectorConnection $connection, AgentConnectorApproval $approval): void
    {
        try {
            $connector = $connection->connector;
            $connectorName = $connector->display_name ?? $connector->name;

            $userId = $connection->connected_by;
            if (! $userId) {
                return;
            }

            $typeLabel = $approval->type === AgentConnectorApproval::TYPE_CLARIFICATION
                ? 'Clarification required'
                : 'Approval required';

            $body = "**Connector:** {$connectorName}\n"
                ."**Action:** `{$approval->action_name}`\n"
                ."**Expires:** {$approval->expires_at->diffForHumans()}\n\n"
                .'Respond via messenger or dashboard.';

            $this->notificationDispatcher->dispatch(new SystemNotificationPayload(
                type: 'connector.approval_requested',
                userId: $userId,
                severity: SystemNotificationPayload::SEVERITY_WARNING,
                title: "{$typeLabel} for \"{$connectorName}\"",
                body: $body,
                context: [
                    'approval_id' => $approval->id,
                    'connector_id' => $connector->id,
                    'connection_id' => $connection->id,
                    'action_name' => $approval->action_name,
                    'type' => $approval->type,
                ],
            ));
        } catch (\Throwable $e) {
            Log::warning('DualChannelApprovalService: Failed to send messenger notification', [
                'approval_id' => $approval->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
