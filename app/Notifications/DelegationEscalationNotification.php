<?php

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
 */
class DelegationEscalationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public readonly DelegationTask $task
    ) {}

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
        $graphName = $this->task->graph->name ?? "Graph #{$this->task->graph->id}";
        $taskName = $this->task->name ?? "Task #{$this->task->id}";

        return (new MailMessage)
            ->subject("Delegation Task Escalation: {$taskName}")
            ->line('A delegation task has exhausted all recovery options and requires manual intervention.')
            ->line("Graph: {$graphName}")
            ->line("Task: {$taskName}")
            ->line('Reason: Recovery chain exhausted after retries and re-delegation attempts.')
            ->action('View Task', url("/agent/delegation/{$this->task->graph->id}/tasks/{$this->task->id}"))
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
            'task_id' => $this->task->id,
            'task_name' => $this->task->name,
            'graph_id' => $this->task->graph->id,
            'graph_name' => $this->task->graph->name ?? null,
            'reason' => 'Recovery chain exhausted',
            'escalated_at' => now()->toIso8601String(),
        ];
    }
}
