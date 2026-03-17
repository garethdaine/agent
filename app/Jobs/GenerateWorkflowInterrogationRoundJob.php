<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Support\WorkflowInterrogator\WorkflowInterrogationExecutionService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class GenerateWorkflowInterrogationRoundJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 1200;

    /**
     * @param  array<int, array<string, mixed>>  $latestAnswers
     */
    public function __construct(
        public int $sessionId,
        public array $latestAnswers = [],
    ) {
        $this->onConnection('redis');
        $this->onQueue('interrogation');
    }

    public function handle(WorkflowInterrogationExecutionService $executionService): void
    {
        $executionService->executeRound($this->sessionId, $this->latestAnswers);
    }
}
