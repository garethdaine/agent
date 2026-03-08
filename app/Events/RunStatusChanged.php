<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\AgentJobRun;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RunStatusChanged implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public readonly int $userId,
        public readonly int $runId,
        public readonly int $jobId,
        public readonly string $jobName,
        public readonly string $status,
    ) {}

    public static function fromRun(AgentJobRun $run, string $status): self
    {
        return new self(
            userId: (int) $run->user_id,
            runId: (int) $run->id,
            jobId: (int) $run->agent_job_id,
            jobName: $run->job->name ?? 'Unknown',
            status: $status,
        );
    }

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('user.'.$this->userId);
    }

    public function broadcastAs(): string
    {
        return 'run.status_changed';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'run_id' => $this->runId,
            'job_id' => $this->jobId,
            'job_name' => $this->jobName,
            'status' => $this->status,
        ];
    }
}
