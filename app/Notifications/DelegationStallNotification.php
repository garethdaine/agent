<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\DelegationGraph;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notification sent when a running delegation graph has stalled.
 *
 * A stalled graph has active tasks that have not progressed within
 * the configured threshold. This is a liveness failure — the graph
 * is not stuck (terminal) but is not making forward progress.
 */
class DelegationStallNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $graphId;

    public ?string $graphName;

    public int $stalledMinutes;

    public int $stalledTaskCount;

    public function __construct(DelegationGraph $graph, int $stalledMinutes, int $stalledTaskCount)
    {
        $this->graphId = $graph->id;
        $this->graphName = $graph->name ?? null;
        $this->stalledMinutes = $stalledMinutes;
        $this->stalledTaskCount = $stalledTaskCount;
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $graphName = $this->graphName ?? "Graph #{$this->graphId}";

        return (new MailMessage)
            ->subject("Delegation Graph Stalled: {$graphName}")
            ->line("A delegation graph has not made progress for {$this->stalledMinutes} minutes.")
            ->line("Graph: {$graphName}")
            ->line("{$this->stalledTaskCount} task(s) are active but not progressing.")
            ->line('The system is attempting automatic recovery by re-dispatching stalled tasks.')
            ->action('View Graph', url("/agent/delegation/{$this->graphId}"))
            ->line('Please review the graph if the stall persists.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'delegation_stall',
            'graph_id' => $this->graphId,
            'graph_name' => $this->graphName,
            'stalled_minutes' => $this->stalledMinutes,
            'stalled_task_count' => $this->stalledTaskCount,
            'detected_at' => now()->toIso8601String(),
        ];
    }
}
