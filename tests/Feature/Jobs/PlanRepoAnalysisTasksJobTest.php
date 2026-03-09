<?php

declare(strict_types=1);

namespace Tests\Feature\Jobs;

use App\Jobs\RepoAnalysis\PlanRepoAnalysisTasksJob;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PlanRepoAnalysisTasksJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_implements_should_queue(): void
    {
        $this->assertTrue(
            is_subclass_of(PlanRepoAnalysisTasksJob::class, ShouldQueue::class)
            || in_array(ShouldQueue::class, class_implements(PlanRepoAnalysisTasksJob::class) ?: [])
        );
    }

    public function test_can_be_dispatched(): void
    {
        Queue::fake();

        PlanRepoAnalysisTasksJob::dispatch(1);

        Queue::assertPushed(PlanRepoAnalysisTasksJob::class);
    }

    public function test_can_be_dispatched_without_dispatch_next(): void
    {
        Queue::fake();

        PlanRepoAnalysisTasksJob::dispatch(1, false);

        Queue::assertPushed(PlanRepoAnalysisTasksJob::class);
    }
}
