<?php

namespace App\Jobs\Org;

use App\Models\OrgRitualRun;
use App\Models\OrgRitualTemplate;
use App\Support\Org\OrgRitualRunService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Job that executes a ritual by creating a run and starting its execution.
 *
 * This job is dispatched either by OrgDispatchDueRitualsJob (for scheduled rituals)
 * or by the API endpoint when a user triggers an immediate run.
 */
class OrgExecuteRitualJob implements ShouldQueue
{
    use Queueable;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var array<int, int>
     */
    public array $backoff = [10, 30, 60];

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 600;

    public function __construct(
        public readonly OrgRitualTemplate $template
    ) {
        $this->onQueue('org-rituals');
    }

    public function handle(OrgRitualRunService $runService): void
    {
        // Create a new run in queued state
        $run = $runService->createRun($this->template);

        // For now, we just create the run.
        // Future integration with DelegationGraph will:
        // 1. Convert phase graph to delegation tasks
        // 2. Start the delegation graph execution
        // 3. Listen for completion events to update run state
    }

    /**
     * Get the tags that should be assigned to the job.
     *
     * @return array<int, string>
     */
    public function tags(): array
    {
        return [
            'org:ritual',
            "template:{$this->template->id}",
            "user:{$this->template->user_id}",
        ];
    }

    /**
     * Determine the time at which the job should timeout.
     */
    public function retryUntil(): \DateTimeInterface
    {
        return now()->addMinutes(30);
    }
}
