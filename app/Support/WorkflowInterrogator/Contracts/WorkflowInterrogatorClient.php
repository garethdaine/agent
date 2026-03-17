<?php

declare(strict_types=1);

namespace App\Support\WorkflowInterrogator\Contracts;

use App\Models\WorkflowInterrogationSession;

interface WorkflowInterrogatorClient
{
    /**
     * @param  array<int, array<string, mixed>>  $history
     * @param  array<int, array<string, mixed>>  $latestAnswers
     * @return array<string, mixed>
     */
    public function generateRound(WorkflowInterrogationSession $session, array $history, array $latestAnswers = []): array;

    /**
     * @param  array<int, array<string, mixed>>  $history
     * @param  array<string, mixed>  $summary
     * @return array<string, mixed>
     */
    public function generateActionPlan(WorkflowInterrogationSession $session, array $history, array $summary): array;
}
