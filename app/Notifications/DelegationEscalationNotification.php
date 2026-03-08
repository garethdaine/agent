<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\DelegationTask;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notification sent to the graph owner when task recovery is exhausted.
 *
 * This notification is triggered when the RecoveryHandler has exhausted
 * all recovery options (retries + re-delegation) and must escalate to
 * the human graph owner for manual intervention.
 *
 * Task and graph data are stored eagerly as scalars to survive model
 * deletion between dispatch and processing (SerializesModels re-fetch).
 */
class DelegationEscalationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $taskId;

    public string $taskName;

    public int $graphId;

    public ?string $graphName;

    /**
     * Create a new notification instance.
     *
     * Captures scalar data eagerly so the notification remains deliverable
     * even if the DelegationTask or its graph is deleted before processing.
     */
    public function __construct(DelegationTask $task)
    {
        $this->taskId = $task->id;
        $this->taskName = $task->name ?? "Task #{$task->id}";
        $this->graphId = $task->graph->id ?? 0;
        $this->graphName = $task->graph->name ?? null;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $graphName = $this->graphName ?? "Graph #{$this->graphId}";

        return (new MailMessage)
            ->subject("Delegation Task Escalation: {$this->taskName}")
            ->line('A delegation task has exhausted all recovery options and requires manual intervention.')
            ->line("Graph: {$graphName}")
            ->line("Task: {$this->taskName}")
            ->line('Reason: Recovery chain exhausted after retries and re-delegation attempts.')
            ->action('View Task', url("/agent/delegation/{$this->graphId}/tasks/{$this->taskId}"))
            ->line('Please review the task and take appropriate action.');
    }

    /**
     * Get the array representation of the notification for database storage.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'delegation_escalation',
            'task_id' => $this->taskId,
            'task_name' => $this->taskName,
            'graph_id' => $this->graphId,
            'graph_name' => $this->graphName,
            'reason' => 'Recovery chain exhausted',
            'escalated_at' => now()->toIso8601String(),
        ];
    }
}
