<?php

declare(strict_types=1);

namespace App\Support\Notifications;

use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;

class NotificationPresenter
{
    /**
     * @return array<string, mixed>
     */
    public function present(DatabaseNotification $notification): array
    {
        $payload = is_array($notification->data) ? $notification->data : []; // @phpstan-ignore function.alreadyNarrowedType
        $kind = (string) ($payload['type'] ?? class_basename($notification->type));

        $presentation = match ($kind) {
            'delegation_escalation' => $this->presentDelegationEscalation($payload),
            'outbound_message_failed' => $this->presentOutboundMessageFailure($payload),
            default => $this->presentGeneric($kind, $payload),
        };

        return [
            'id' => (string) $notification->id,
            'type' => $kind,
            'title' => $presentation['title'],
            'body' => $presentation['body'],
            'action' => $presentation['action'],
            'data' => $payload,
            'read_at' => $notification->read_at?->toIso8601String(),
            'created_at' => $notification->created_at?->toIso8601String(),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function presentDelegationEscalation(array $payload): array
    {
        $taskId = (int) ($payload['task_id'] ?? 0);
        $graphId = (int) ($payload['graph_id'] ?? 0);
        $taskName = (string) ($payload['task_name'] ?? "Task #{$taskId}");
        $reason = (string) ($payload['reason'] ?? 'Recovery chain exhausted');

        return [
            'title' => 'Delegation task escalated',
            'body' => "{$taskName} needs attention. {$reason}.",
            'action' => $taskId > 0 && $graphId > 0
                ? [
                    'label' => 'Review task',
                    'url' => "/agent/delegation/{$graphId}/tasks/{$taskId}",
                ]
                : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function presentOutboundMessageFailure(array $payload): array
    {
        $reason = trim((string) ($payload['reason'] ?? 'Unknown reason'));
        $sessionId = trim((string) ($payload['session_id'] ?? ''));
        $sessionSuffix = $sessionId !== '' ? " Session {$sessionId}." : '';

        return [
            'title' => 'Outbound message delivery failed',
            'body' => "{$reason}.{$sessionSuffix}",
            'action' => [
                'label' => 'Open dead letters',
                'url' => '/messenger/dead-letters',
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function presentGeneric(string $kind, array $payload): array
    {
        $title = trim((string) ($payload['title'] ?? ''));
        if ($title === '') {
            $title = Str::headline(str_replace(['_', '-'], ' ', $kind));
        }

        $body = trim((string) ($payload['body'] ?? ($payload['message'] ?? ($payload['reason'] ?? ''))));
        $actionUrl = trim((string) ($payload['action_url'] ?? ''));
        $actionLabel = trim((string) ($payload['action_label'] ?? 'Open'));

        return [
            'title' => $title,
            'body' => $body !== '' ? $body : null,
            'action' => $actionUrl !== ''
                ? [
                    'label' => $actionLabel !== '' ? $actionLabel : 'Open',
                    'url' => $actionUrl,
                ]
                : null,
        ];
    }
}
